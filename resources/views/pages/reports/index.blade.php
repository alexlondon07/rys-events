<?php

use App\Models\Department;
use App\Models\Report;
use App\Models\ReportItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Informes')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'por', except: 10)]
    public int $perPage = 10;

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status', 'department', 'sort', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'department', 'sort');
        $this->resetPage();
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Report> */
    #[Computed]
    public function reports()
    {
        return Report::query()
            ->with('municipality.department')
            ->withCount('items')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('contract_number', 'like', $term)
                        ->orWhere('event_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhereHas('municipality', fn ($query) => $query->where('name', 'like', $term));
                });
            })
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->department !== '', fn ($query) => $query->whereHas(
                'municipality',
                fn ($query) => $query->where('department_id', (int) $this->department),
            ))
            ->when($this->sort === 'recent', fn ($query) => $query->latest('updated_at'))
            ->when($this->sort === 'contract', fn ($query) => $query->orderBy('contract_number'))
            ->when($this->sort === 'event', fn ($query) => $query->orderByDesc('event_start'))
            ->paginate($this->perPage);
    }

    /** @return array{total:int, drafts:int, finals:int, items:int} */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Report::query()->count(),
            'drafts' => Report::query()->where('status', 'draft')->count(),
            'finals' => Report::query()->where('status', 'final')->count(),
            'items' => ReportItem::query()->count(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Department> */
    #[Computed]
    public function departments()
    {
        return Department::query()
            ->whereHas('municipalities.reports')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== ''
            || $this->status !== 'all'
            || $this->department !== ''
            || $this->sort !== 'recent';
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 py-4">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-medium text-[#7F5C12]">Informes de actividades</p>
            <h1 class="font-display mt-1 text-3xl font-semibold text-[#17150F]">Informes</h1>
            <p class="mt-2 text-[#5F584A]">Cree un informe nuevo o complételo desde la plantilla oficial.</p>
        </div>
        <flux:button :href="route('reports.import')" icon="arrow-up-tray" variant="primary" wire:navigate>
            Importar desde Excel
        </flux:button>
    </header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Total', $this->stats['total'], 'document-text'],
            ['Borradores', $this->stats['drafts'], 'pencil-square'],
            ['Finalizados', $this->stats['finals'], 'check-badge'],
            ['Ítems cargados', $this->stats['items'], 'rectangle-stack'],
        ] as [$label, $value, $icon])
            <article class="flex items-center gap-3 rounded-xl border border-[#E3DED3] bg-white p-4 shadow-sm">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                    <flux:icon :name="$icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="font-display text-2xl font-bold leading-none text-[#17150F]">{{ $value }}</p>
                    <p class="mt-1 truncate text-xs text-[#5F584A]">{{ $label }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section class="rounded-xl border border-[#E3DED3] bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Buscar contrato, evento o municipio" />

            <flux:select wire:model.live="status">
                <flux:select.option value="all">Todos los estados</flux:select.option>
                <flux:select.option value="draft">Borradores</flux:select.option>
                <flux:select.option value="final">Finalizados</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="department">
                <flux:select.option value="">Todos los departamentos</flux:select.option>
                @foreach ($this->departments as $department)
                    <flux:select.option value="{{ $department->id }}">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex gap-3">
                <flux:select wire:model.live="sort" class="flex-1">
                    <flux:select.option value="recent">Más recientes</flux:select.option>
                    <flux:select.option value="contract">Por contrato</flux:select.option>
                    <flux:select.option value="event">Por fecha del evento</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-28">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->hasFilters())
            <div class="mt-3 flex items-center justify-between gap-3 border-t border-[#EDE9E0] pt-3">
                <p class="text-xs text-[#5F584A]">{{ $this->reports->total() }} resultado(s) con los filtros aplicados.</p>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </section>

    @if ($this->reports->isEmpty())
        <div class="rounded-xl border border-[#E3DED3] bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                <flux:icon.document-text class="size-6" />
            </div>
            <h2 class="font-display mt-4 text-lg font-semibold">
                {{ $this->hasFilters() ? 'Sin resultados' : 'Aún no hay informes' }}
            </h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-[#5F584A]">
                {{ $this->hasFilters() ? 'Pruebe con otros filtros o limpielos para ver todo.' : 'Empiece cargando la plantilla llena. La vista previa mostrará cada cambio antes de guardar.' }}
            </p>
        </div>
    @else
        {{-- Tarjetas en móvil --}}
        <div class="grid gap-3 lg:hidden">
            @foreach ($this->reports as $report)
                <a href="{{ route('reports.show', $report) }}" wire:navigate class="rounded-xl border border-[#E3DED3] bg-white p-4 shadow-sm transition hover:border-[#C9A043]">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-display font-bold text-[#17150F]">{{ $report->contract_number }}</p>
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-[#E7F3EC] text-[#2C7549]' => $report->status === 'final',
                            'bg-[#F6EEDB] text-[#7F5C12]' => $report->status !== 'final',
                        ])>{{ $report->status === 'draft' ? 'Borrador' : 'Finalizado' }}</span>
                    </div>
                    <p class="mt-1 truncate text-sm font-medium text-[#17150F]">{{ $report->event_name ?: 'Evento por completar' }}</p>
                    <p class="mt-1 text-xs text-[#5F584A]">
                        {{ $report->municipality?->name ?? 'Pendiente' }} · {{ $report->items_count }} ítems · Paso {{ $report->inferredCurrentStep() }}/6
                    </p>
                </a>
            @endforeach
        </div>

        {{-- Tabla en escritorio --}}
        <div class="hidden overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-[#E3DED3] bg-[#FAF9F6] text-xs font-semibold uppercase tracking-wide text-[#5F584A]">
                        <tr>
                            <th class="px-5 py-3">Contrato</th>
                            <th class="px-5 py-3">Evento</th>
                            <th class="px-5 py-3">Municipio</th>
                            <th class="px-5 py-3">Periodo</th>
                            <th class="px-5 py-3">Avance</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EDE9E0]">
                        @foreach ($this->reports as $report)
                            <tr class="text-[#17150F] transition hover:bg-[#FBFAF6]">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $report->contract_number }}</td>
                                <td class="max-w-64 px-5 py-4">
                                    <span class="block truncate font-medium">{{ $report->event_name ?: 'Evento por completar' }}</span>
                                    <span class="mt-0.5 block text-xs text-[#5F584A]">{{ $report->items_count }} ítems</span>
                                </td>
                                <td class="px-5 py-4">
                                    {{ $report->municipality?->name ?? 'Pendiente' }}
                                    @if ($report->municipality?->department)
                                        <span class="block text-xs text-[#5F584A]">{{ $report->municipality->department->name }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-[#5F584A]">
                                    {{ $report->period_start?->format('d/m/Y') ?? '—' }} – {{ $report->period_end?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="min-w-36 px-5 py-4">
                                    @php($step = $report->inferredCurrentStep())
                                    <div class="flex items-center justify-between text-xs font-semibold text-[#5F584A]">
                                        <span>Paso {{ $step }} de 6</span>
                                        <span>{{ round(($step / 6) * 100) }}%</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#ECE8E0]">
                                        <div class="h-full rounded-full bg-[#C9A043]" style="width: {{ ($step / 6) * 100 }}%"></div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-[#E7F3EC] text-[#2C7549]' => $report->status === 'final',
                                        'bg-[#F6EEDB] text-[#7F5C12]' => $report->status !== 'final',
                                    ])>{{ $report->status === 'draft' ? 'Borrador' : 'Finalizado' }}</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <flux:button :href="route('reports.show', $report)" size="sm" variant="primary" icon="eye" wire:navigate>
                                            Revisar
                                        </flux:button>
                                        <flux:button :href="route('reports.preview', $report)" size="sm" variant="ghost" icon="document-magnifying-glass">
                                            Vista previa
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $this->reports->links() }}
        </div>
    @endif
</div>
