<?php

use App\Models\Report;
use App\Models\ReportImport;
use App\Services\ReportImport\ReportExcelGrid;
use App\Services\ReportImport\TemplateReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Visor del Excel')] class extends Component {
    #[Url(as: 'informe', except: null)]
    public ?int $reportId = null;

    #[Url(as: 'carga', except: null)]
    public ?int $importId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $sheet = 'Informe';

    public function mount(): void
    {
        if ($this->reportId) {
            Gate::authorize('view', Report::findOrFail($this->reportId));
        }
    }

    public function selectReport(int $reportId): void
    {
        Gate::authorize('view', Report::findOrFail($reportId));

        $this->reportId = $reportId;
        $this->importId = null;
        $this->sheet = 'Informe';
        unset($this->imports, $this->activeImport, $this->grid);
    }

    public function selectImport(int $importId): void
    {
        $this->importId = $importId;
        $this->sheet = 'Informe';
        unset($this->grid);
    }

    public function updatedSearch(): void
    {
        unset($this->reports);
    }

    /** @return Collection<int, Report> */
    #[Computed]
    public function reports(): Collection
    {
        return Report::query()
            ->with('municipality.department')
            ->withCount('imports')
            ->has('imports')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('contract_number', 'like', $term)
                        ->orWhere('event_name', 'like', $term)
                        ->orWhereHas('municipality', fn ($query) => $query->where('name', 'like', $term));
                });
            })
            ->orderByDesc('updated_at')
            ->limit(60)
            ->get();
    }

    #[Computed]
    public function selectedReport(): ?Report
    {
        return $this->reportId
            ? Report::with('municipality.department')->find($this->reportId)
            : null;
    }

    /** @return Collection<int, ReportImport> */
    #[Computed]
    public function imports(): Collection
    {
        return $this->selectedReport
            ? $this->selectedReport->imports()->orderByDesc('version')->orderByDesc('id')->get()
            : collect();
    }

    #[Computed]
    public function activeImport(): ?ReportImport
    {
        return $this->importId
            ? ($this->imports->firstWhere('id', $this->importId) ?? $this->imports->first())
            : $this->imports->first();
    }

    #[Computed]
    public function grid(): ?ReportExcelGrid
    {
        $import = $this->activeImport;

        if (! $import || ! $import->file_path) {
            return null;
        }

        $path = Storage::disk('local')->path($import->file_path);

        if (! is_file($path)) {
            return null;
        }

        try {
            $sheets = app(TemplateReader::class)->readSheets($path);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        return new ReportExcelGrid($sheets, $import->summary['preview'] ?? []);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 py-4">
    <header>
        <p class="text-sm font-medium text-[#7F5C12]">Informes</p>
        <h1 class="font-display mt-1 text-3xl font-semibold text-[#17150F]">Visor del Excel</h1>
        <p class="mt-2 max-w-3xl text-[#5F584A]">
            Vea el Excel de cada carga tal como se subió: la rejilla real por hoja, el color de cada fila según lo que
            hará el sistema y la explicación de cada columna. Es la misma información que se usa al importar, sin
            adivinar.
        </p>
    </header>

    @if ($this->reports->isEmpty())
        <section class="rounded-xl border border-[#E3DED3] bg-white p-8 text-center shadow-sm">
            <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                <flux:icon.table-cells class="size-6" />
            </span>
            <h2 class="font-display mt-4 text-lg font-semibold">Aún no hay Excel cargados</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-[#5F584A]">
                Cuando suba una plantilla desde “Importar desde Excel”, aquí podrá analizar el archivo de cada informe.
            </p>
            <flux:button :href="route('reports.import')" variant="primary" icon="arrow-up-tray" class="mt-5" wire:navigate>
                Importar desde Excel
            </flux:button>
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="flex min-w-0 flex-col gap-4">
                <div class="rounded-xl border border-[#E3DED3] bg-white p-4 shadow-sm">
                    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Buscar contrato o municipio" size="sm" />
                </div>

                <div class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                    <p class="border-b border-[#E3DED3] bg-[#FBFAF6] px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-[#8A8274]">
                        Informes con Excel ({{ $this->reports->count() }})
                    </p>
                    <div class="max-h-[70vh] divide-y divide-[#EDE9E0] overflow-y-auto">
                        @foreach ($this->reports as $report)
                            @php($selected = $report->id === $this->reportId)
                            <button type="button" wire:key="report-{{ $report->id }}" wire:click="selectReport({{ $report->id }})"
                                @class([
                                    'flex w-full flex-col gap-1 px-4 py-3 text-left transition',
                                    'bg-[#F6EEDB]' => $selected,
                                    'hover:bg-[#FBFAF6]' => ! $selected,
                                ])>
                                <span class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-[11px] font-bold text-[#7F5C12]">{{ $report->contract_number }}</span>
                                    <span class="rounded-full border border-[#E3DED3] bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-[#5F584A]">{{ $report->imports_count }} v.</span>
                                </span>
                                <span class="truncate text-sm font-semibold text-[#17150F]">{{ $report->event_name ?: 'Evento sin nombre' }}</span>
                                <span class="truncate text-xs text-[#8A8274]">{{ $report->municipality ? $report->municipality->name.', '.$report->municipality->department->name : 'Sin municipio' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-col gap-5">
                @if (! $this->selectedReport)
                    <section class="flex min-h-64 flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#D3CBBB] bg-[#FBFAF6] p-8 text-center">
                        <flux:icon.cursor-arrow-rays class="size-7 text-[#8A8274]" />
                        <p class="mt-3 font-semibold text-[#5F584A]">Elija un informe de la lista</p>
                        <p class="mt-1 text-sm text-[#8A8274]">Verá sus cargas de Excel y podrá analizar cada hoja.</p>
                    </section>
                @else
                    @php($report = $this->selectedReport)
                    <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold text-[#7F5C12]">{{ $report->contract_number }}</p>
                                <h2 class="font-display mt-1 truncate text-xl font-semibold">{{ $report->event_name ?: 'Evento sin nombre' }}</h2>
                                <p class="mt-0.5 text-sm text-[#8A8274]">{{ $report->municipality ? $report->municipality->name.', '.$report->municipality->department->name : 'Sin municipio' }}</p>
                            </div>
                            <flux:button :href="route('reports.show', $report)" variant="ghost" size="sm" icon="eye" wire:navigate>Ver informe</flux:button>
                        </div>

                        @if ($this->imports->isNotEmpty())
                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#EDE9E0] pt-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#8A8274]">Cargas</span>
                                @foreach ($this->imports as $import)
                                    @php($active = $this->activeImport?->id === $import->id)
                                    <button type="button" wire:key="import-{{ $import->id }}" wire:click="selectImport({{ $import->id }})"
                                        @class([
                                            'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                            'border-[#17150F] bg-[#17150F] text-white' => $active,
                                            'border-[#D3CBBB] bg-white text-[#5F584A] hover:border-[#C9A043]' => ! $active,
                                        ])>
                                        {{ $import->version ? 'v'.$import->version : 'En revisión' }}
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-[#6FCF97]' => $import->status === 'applied',
                                            'bg-[#E0B34B]' => $import->status === 'previewing',
                                            'bg-[#E57368]' => $import->status === 'failed',
                                        ])></span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($this->activeImport && $this->activeImport->file_path)
                        <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-[#E3DED3] bg-white px-5 py-3 text-xs shadow-sm">
                            <span class="font-semibold text-[#17150F]">{{ $this->activeImport->original_name }}</span>
                            <span class="text-[#8A8274]">{{ $this->activeImport->created_at->format('d/m/Y H:i') }}</span>
                            @if ($this->activeImport->user)
                                <span class="text-[#8A8274]">{{ $this->activeImport->user->name }}</span>
                            @endif
                            <span class="text-[#5F584A]">{{ $this->activeImport->status === 'applied' ? $this->activeImport->summaryLine() : 'En revisión (no aplicada)' }}</span>
                            <a href="{{ route('reports.imports.download', [$report, $this->activeImport]) }}" class="ml-auto font-semibold text-[#7F5C12] hover:underline">Descargar archivo</a>
                        </section>
                    @endif

                    @if (! $this->grid)
                        <section class="rounded-xl border border-[#E8D6A8] bg-[#FBEEDA] p-5 text-sm text-[#8F520A]">
                            <p class="font-semibold">No se pudo leer el archivo de esta carga.</p>
                            <p class="mt-1">Puede que el archivo se haya movido o eliminado del almacenamiento. Descárguelo para revisarlo.</p>
                        </section>
                    @else
                        @php($grid = $this->grid)
                        @php($sheetNames = $grid->sheetNames())
                        @php($activeSheet = in_array($this->sheet, $sheetNames, true) ? $this->sheet : ($sheetNames[0] ?? ''))
                        @php($tones = [
                            'success' => ['row' => 'bg-[#F3F9F5]', 'chip' => 'bg-[#E4F1E8] text-[#2C7549]', 'dot' => 'bg-[#2C7549]'],
                            'gold' => ['row' => 'bg-[#FCF8EF]', 'chip' => 'bg-[#F6EEDB] text-[#7F5C12]', 'dot' => 'bg-[#C9A043]'],
                            'muted' => ['row' => '', 'chip' => 'bg-[#ECE8E0] text-[#5F584A]', 'dot' => 'bg-[#B7AE9C]'],
                            'warning' => ['row' => 'bg-[#FDF7EA]', 'chip' => 'bg-[#FBEEDA] text-[#8F520A]', 'dot' => 'bg-[#C9A043]'],
                            'danger' => ['row' => 'bg-[#FCF1EF]', 'chip' => 'bg-[#F9E3E0] text-[#A8261D]', 'dot' => 'bg-[#A8261D]'],
                        ])

                        <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-[#E3DED3] bg-white px-5 py-3 text-xs shadow-sm">
                            <span class="font-semibold uppercase tracking-wide text-[#8A8274]">Estado de cada fila</span>
                            @foreach ($grid->legend() as $item)
                                <span class="inline-flex items-center gap-1.5 text-[#5F584A]">
                                    <span class="size-2 rounded-full {{ $tones[$item['tone']]['dot'] }}"></span>{{ $item['label'] }}
                                </span>
                            @endforeach
                        </section>

                        <div x-data="{ sheet: @js($activeSheet) }" wire:key="grid-{{ $this->activeImport->id }}" class="flex min-w-0 flex-col gap-4">
                            <div class="flex flex-wrap gap-1.5 rounded-xl border border-[#E3DED3] bg-white p-1.5 shadow-sm">
                                @foreach ($sheetNames as $name)
                                    <button type="button" @click="sheet = @js($name)"
                                        :class="sheet === @js($name) ? 'bg-[#17150F] text-white' : 'text-[#5F584A] hover:bg-[#F3F1EC]'"
                                        class="rounded-lg px-3.5 py-1.5 text-sm font-semibold transition">
                                        {{ $name }}
                                    </button>
                                @endforeach
                            </div>

                            @foreach ($sheetNames as $name)
                                @php($guide = $grid->guide($name))
                                <div x-show="sheet === @js($name)" x-cloak class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
                                    <div class="min-w-0 overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                                        <div class="flex items-center justify-between gap-3 border-b border-[#E3DED3] bg-[#FBFAF6] px-4 py-2.5">
                                            <h3 class="text-sm font-semibold text-[#17150F]">Hoja {{ $name }}</h3>
                                            <span class="text-[11px] text-[#8A8274]">{{ count($grid->rows($name)) }} filas · {{ count($guide) }} columnas</span>
                                        </div>
                                        <div class="max-h-[70vh] overflow-auto">
                                            <table class="w-full min-w-max border-collapse text-left text-xs">
                                                <tbody class="divide-y divide-[#EDE9E0]">
                                                    @foreach ($grid->rows($name) as $row)
                                                        @php($status = $row['status'])
                                                        <tr wire:key="row-{{ $name }}-{{ $row['number'] }}" class="{{ $status ? $tones[$status['tone']]['row'] : '' }}">
                                                            <td class="sticky left-0 z-10 border-e border-[#EDE9E0] bg-inherit px-2 py-1.5 text-center font-mono text-[10px] text-[#8A8274]">{{ $row['number'] }}</td>
                                                            <td class="border-e border-[#EDE9E0] px-2 py-1.5">
                                                                @if ($status)
                                                                    <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $tones[$status['tone']]['chip'] }}">{{ $status['label'] }}</span>
                                                                @else
                                                                    <span class="text-[10px] text-[#C6BFAE]">—</span>
                                                                @endif
                                                            </td>
                                                            @foreach ($row['cells'] as $cell)
                                                                <td @class([
                                                                    'max-w-[320px] px-2.5 py-1.5 align-top',
                                                                    'whitespace-nowrap font-mono text-[10px] text-[#8F520A]' => $row['kind'] === 'keys',
                                                                    'font-semibold text-[#17150F]' => $row['kind'] === 'labels',
                                                                    'italic text-[#8A8274]' => $row['kind'] === 'help',
                                                                    'text-[#3D382E]' => $row['kind'] === 'data',
                                                                ])>
                                                                    <span class="line-clamp-3 whitespace-pre-line">{{ $cell !== '' ? $cell : '—' }}</span>
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <aside class="min-w-0">
                                        <div class="xl:sticky xl:top-20 overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                                            <p class="border-b border-[#E3DED3] bg-[#FBFAF6] px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-[#8A8274]">
                                                {{ $name === 'Informe' ? 'Qué significa cada campo' : 'Qué significa cada columna' }}
                                            </p>
                                            <ol class="max-h-[70vh] divide-y divide-[#EDE9E0] overflow-y-auto">
                                                @foreach ($guide as $column)
                                                    <li class="px-4 py-3">
                                                        <p class="font-mono text-[11px] font-bold text-[#7F5C12]">{{ $column['key'] }}</p>
                                                        <p class="mt-0.5 text-sm font-semibold text-[#17150F]">{{ $column['title'] }}</p>
                                                        @if ($column['help'])
                                                            <p class="mt-1 text-xs leading-5 text-[#5F584A]">{{ $column['help'] }}</p>
                                                        @endif
                                                        @if ($column['field'])
                                                            <p class="mt-1 text-[11px] text-[#8A8274]">Campo: <span class="font-mono">{{ $column['field'] }}</span></p>
                                                        @endif
                                                        @if ($column['example'] !== '')
                                                            <p class="mt-1 line-clamp-2 text-[11px] text-[#8A8274]">Ej.: {{ $column['example'] }}</p>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    </aside>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
