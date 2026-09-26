<?php

use App\Models\ReportImport;
use App\Services\ReportImport\ReportImportApplier;
use App\Services\ReportImport\ReportImportPreviewer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Importar desde Excel')] class extends Component {
    use WithFileUploads;

    public $file = null;

    public ?int $importId = null;

    /** @var array<string, mixed> */
    public array $preview = [];

    /** @var array<string, string> */
    public array $resolutions = [];

    /** @var array<string, mixed> */
    public array $result = [];

    public bool $duplicateFile = false;

    public function generatePreview(ReportImportPreviewer $previewer): void
    {
        $this->resetErrorBag();
        $validated = $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'file.required' => 'Seleccione la plantilla que desea revisar.',
            'file.mimes' => 'La plantilla debe ser un archivo .xlsx.',
            'file.max' => 'El archivo no puede superar 10 MB.',
        ]);

        $originalName = $validated['file']->getClientOriginalName();
        $path = $validated['file']->storeAs(
            'report-imports/'.now()->format('Y/m'),
            Str::uuid().'.xlsx',
            'local',
        );

        try {
            $preview = $previewer->preview(Storage::disk('local')->path($path));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);
            $this->addError('file', $exception->getMessage());

            return;
        }

        $hash = hash_file('sha256', Storage::disk('local')->path($path)) ?: null;
        $duplicate = $hash !== null && ReportImport::query()
            ->where('report_id', $preview['report_id'])
            ->where('file_hash', $hash)
            ->where('status', 'applied')
            ->exists();

        // Descarta las vistas previas pendientes del mismo contrato: al subir de
        // nuevo, solo queda la última en revisión.
        ReportImport::query()
            ->where('user_id', Auth::id())
            ->where('status', 'previewing')
            ->get()
            ->filter(fn (ReportImport $pending): bool => ($pending->summary['preview']['contract_number'] ?? null) === $preview['contract_number'])
            ->each(function (ReportImport $pending): void {
                if ($pending->file_path) {
                    Storage::disk('local')->delete($pending->file_path);
                }

                $pending->delete();
            });

        $import = ReportImport::create([
            'report_id' => $preview['report_id'],
            'user_id' => Auth::id(),
            'original_name' => $originalName,
            'file_path' => $path,
            'status' => 'previewing',
            'file_hash' => $hash,
            'summary' => ['preview' => $preview, 'duplicate' => $duplicate],
            'errors' => $preview['issues'],
        ]);

        $this->importId = $import->id;
        $this->preview = $preview;
        $this->duplicateFile = $duplicate;
        $this->resolutions = [];
        $this->result = [];
        $this->reset('file');
    }

    public function applyImport(ReportImportApplier $applier): void
    {
        $this->resetErrorBag();
        $import = ReportImport::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->importId);

        try {
            $this->result = $applier->apply($import, $this->resolutions);
            $this->preview = [];
            Flux::toast(variant: 'success', text: 'La importación se aplicó correctamente.');
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('apply', $exception->getMessage());
        }
    }

    public function reviewAnotherFile(): void
    {
        $this->reset(['file', 'importId', 'preview', 'resolutions', 'result']);
        $this->resetErrorBag();
    }

    #[Computed]
    public function history()
    {
        return ReportImport::query()
            ->with('report')
            ->where('user_id', Auth::id())
            // Oculta las cargas aplicadas cuyo informe ya no existe (borrado):
            // el historial refleja el estado actual, no informes eliminados.
            ->where(fn ($query) => $query
                ->where('status', '!=', 'applied')
                ->orWhereHas('report'))
            ->latest()
            ->limit(8)
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 py-4">
        <header>
            <p class="text-sm font-medium text-[#7F5C12]">Informes</p>
            <h1 class="font-display mt-1 text-3xl font-semibold text-[#17150F]">Importar desde Excel</h1>
            <p class="mt-2 text-[#5F584A]">
                {{ $result ? 'Cambios aplicados. Puede continuar completando el informe.' : ($preview ? 'Revise lo que va a cambiar. Nada se guarda hasta que confirme.' : 'Suba la plantilla oficial para crear o actualizar un informe por partes.') }}
            </p>
        </header>

        <ol class="grid overflow-hidden rounded-xl border border-[#D3CBBB] bg-white sm:grid-cols-3">
            @foreach ([['Subir archivo', 1], ['Revisar cambios', 2], ['Aplicar', 3]] as [$label, $step])
                @php($current = $result ? 3 : ($preview ? 2 : 1))
                <li class="flex items-center gap-3 border-b border-[#E3DED3] px-5 py-4 last:border-0 sm:border-b-0 sm:border-e">
                    <span @class([
                        'flex size-7 items-center justify-center rounded-full text-xs font-bold',
                        'bg-[#17150F] text-white' => $current === $step,
                        'bg-[#E4F1E8] text-[#2C7549]' => $current > $step,
                        'bg-[#ECE8E0] text-[#5F584A]' => $current < $step,
                    ])>{{ $current > $step ? '✓' : $step }}</span>
                    <span class="text-sm font-semibold">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        @if (! $preview && ! $result)
            <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm sm:p-8">
                <div class="grid gap-6 lg:grid-cols-[1fr_260px]">
                    <form wire:submit="generatePreview" class="space-y-5">
                        <label class="flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#D3CBBB] bg-[#F9F8F4] px-6 text-center transition hover:border-[#C9A043] hover:bg-[#F6EEDB]/40">
                            <span class="flex size-12 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                                <flux:icon.arrow-up-tray class="size-6" />
                            </span>
                            <span class="font-display mt-4 text-lg font-semibold">Seleccione la plantilla llena</span>
                            <span class="mt-2 text-sm text-[#5F584A]">Archivo .xlsx · máximo 10 MB</span>
                            <input wire:model="file" type="file" accept=".xlsx" class="sr-only">
                            @if ($file)
                                <span class="mt-4 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-[#17150F] shadow-sm">{{ $file->getClientOriginalName() }}</span>
                            @endif
                        </label>

                        @error('file')
                            <p class="rounded-lg bg-[#F9E3E0] px-4 py-3 text-sm text-[#A8261D]">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <a href="{{ route('reports.template') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#7F5C12] hover:underline">
                                <flux:icon.arrow-down-tray class="size-4" />
                                Descargar plantilla oficial
                            </a>
                            <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="generatePreview">Generar vista previa</span>
                                <span wire:loading wire:target="generatePreview">Leyendo Excel…</span>
                            </flux:button>
                        </div>
                    </form>

                    <aside class="rounded-xl border border-[#E8D6A8] bg-[#F6EEDB] p-5">
                        <h2 class="font-display font-semibold text-[#7F5C12]">Carga por partes</h2>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-[#5F584A]">
                            <li>El contrato identifica el informe.</li>
                            <li>La referencia identifica cada ítem.</li>
                            <li>Una celda vacía no borra información.</li>
                            <li>Los ítems ausentes no se eliminan.</li>
                        </ul>
                    </aside>
                </div>
            </section>
        @endif

        @if ($preview)
            <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#7F5C12]">{{ $preview['report_action'] === 'create' ? 'Crea un informe' : 'Actualiza un informe existente' }}</p>
                        <h2 class="font-display mt-1 text-xl font-semibold">{{ $preview['contract_number'] }} · {{ $preview['report_label'] }}</h2>
                        <p class="mt-1 text-sm text-[#5F584A]">Plantilla {{ $preview['version'] }}. Las celdas vacías no modifican lo guardado.</p>
                    </div>
                    <flux:button wire:click="reviewAnotherFile" variant="ghost" icon="arrow-path">Cambiar archivo</flux:button>
                </div>
            </section>

            @if ($duplicateFile)
                <div class="flex items-start gap-3 rounded-xl border border-[#E8D6A8] bg-[#FBEEDA] px-4 py-3 text-sm text-[#8F520A]">
                    <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                    <span>Este archivo ya se cargó antes en este informe. Puede aplicarlo igual si hizo cambios, o cancelar para evitar duplicar información.</span>
                </div>
            @endif

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['new', 'Ítems nuevos', '#E4F1E8', '#2C7549'],
                    ['changed', 'Ítems que cambian', '#F6EEDB', '#7F5C12'],
                    ['unchanged', 'Sin cambios', '#ECE8E0', '#5F584A'],
                    ['conflicts', 'Conflictos', '#FBEEDA', '#8F520A'],
                    ['issues', 'Errores y avisos', '#F9E3E0', '#A8261D'],
                ] as [$key, $label, $background, $color])
                    <article class="rounded-xl border border-[#E3DED3] bg-white p-4">
                        <strong class="font-display text-2xl" style="color: {{ $color }}">{{ $preview['counts'][$key] }}</strong>
                        <p class="mt-1 text-sm text-[#5F584A]">{{ $label }}</p>
                    </article>
                @endforeach
            </section>

            <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                <div class="border-b border-[#E3DED3] px-5 py-4">
                    <h2 class="font-display text-lg font-semibold">Cambios detectados</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-[#F3F1EC] text-xs uppercase tracking-wide text-[#5F584A]">
                            <tr><th class="px-5 py-3">Ref.</th><th class="px-5 py-3">Ítem</th><th class="px-5 py-3">Campo</th><th class="px-5 py-3">Antes → después</th><th class="px-5 py-3">Tipo</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[#E3DED3]">
                            @foreach ($preview['report_changes'] as $change)
                                <tr>
                                    <td class="px-5 py-4 font-semibold">Informe</td>
                                    <td class="px-5 py-4">{{ $preview['contract_number'] }}</td>
                                    <td class="px-5 py-4">{{ $change['label'] }}</td>
                                    <td class="px-5 py-4"><span class="text-[#5F584A]">{{ Str::limit($change['before'], 90) }}</span><span class="mx-2 text-[#C9A043]">→</span>{{ Str::limit($change['after'], 90) }}</td>
                                    <td class="px-5 py-4">
                                        @if ($preview['report_action'] === 'create')
                                            <span class="rounded-full bg-[#E4F1E8] px-2.5 py-1 text-xs font-semibold text-[#2C7549]">Nuevo</span>
                                        @else
                                            <span class="rounded-full bg-[#F6EEDB] px-2.5 py-1 text-xs font-semibold text-[#7F5C12]">Actualiza</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($preview['items'] as $item)
                                @if ($item['action'] === 'unchanged')
                                    @continue
                                @endif
                                @foreach ($item['changes'] ?: [['label' => 'Todo el ítem', 'before' => '(vacío)', 'after' => 'Se agrega al informe']] as $change)
                                    <tr>
                                        <td class="px-5 py-4 font-semibold">{{ $item['ref'] }}</td>
                                        <td class="px-5 py-4">{{ Str::limit($item['name'], 55) }}</td>
                                        <td class="px-5 py-4">{{ $change['label'] }}</td>
                                        <td class="px-5 py-4"><span class="text-[#5F584A]">{{ Str::limit($change['before'], 90) }}</span><span class="mx-2 text-[#C9A043]">→</span>{{ Str::limit($change['after'], 90) }}</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-[#E4F1E8] text-[#2C7549]' => $item['action'] === 'create',
                                                'bg-[#F6EEDB] text-[#7F5C12]' => $item['action'] === 'update',
                                            ])>{{ $item['action'] === 'create' ? 'Nuevo' : 'Actualiza' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                            @if (count($preview['report_changes']) === 0 && $preview['counts']['new'] === 0 && $preview['counts']['changed'] === 0)
                                <tr><td colspan="5" class="px-5 py-8 text-center text-[#5F584A]">El archivo no contiene cambios nuevos.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($preview['conflicts'])
                <section class="rounded-xl border border-[#E8D6A8] bg-[#FBEEDA] p-5 sm:p-6">
                    <h2 class="font-display text-lg font-semibold text-[#8F520A]">Conflictos por resolver</h2>
                    <p class="mt-1 text-sm text-[#5F584A]">Estos campos se editaron en la aplicación después de la última carga.</p>
                    <div class="mt-5 space-y-4">
                        @foreach ($preview['conflicts'] as $conflict)
                            <article class="rounded-xl border border-[#E8D6A8] bg-white p-4">
                                <p class="font-semibold">{{ $conflict['label'] }}</p>
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <label class="flex cursor-pointer gap-3 rounded-lg border border-[#D3CBBB] p-3">
                                        <input type="radio" wire:model="resolutions.{{ $conflict['id'] }}" value="app" class="mt-1 accent-[#17150F]">
                                        <span><strong class="block text-sm">Conservar aplicación</strong><span class="mt-1 block text-sm text-[#5F584A]">{{ Str::limit($conflict['before'], 180) }}</span></span>
                                    </label>
                                    <label class="flex cursor-pointer gap-3 rounded-lg border border-[#D3CBBB] p-3">
                                        <input type="radio" wire:model="resolutions.{{ $conflict['id'] }}" value="excel" class="mt-1 accent-[#17150F]">
                                        <span><strong class="block text-sm">Usar Excel</strong><span class="mt-1 block text-sm text-[#5F584A]">{{ Str::limit($conflict['after'], 180) }}</span></span>
                                    </label>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($preview['issues'])
                <section class="rounded-xl border border-[#E3DED3] bg-white p-5 sm:p-6">
                    <h2 class="font-display text-lg font-semibold">Errores y avisos</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($preview['issues'] as $issue)
                            <div @class([
                                'rounded-lg border px-4 py-3 text-sm',
                                'border-[#E8D6A8] bg-[#FBEEDA] text-[#8F520A]' => $issue['severity'] === 'warning',
                                'border-[#F1C4BE] bg-[#F9E3E0] text-[#A8261D]' => $issue['severity'] === 'error',
                            ])>
                                <strong>{{ $issue['sheet'] }}{{ $issue['row'] ? ', fila '.$issue['row'] : '' }}:</strong> {{ $issue['message'] }}
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @error('apply')
                <p class="rounded-lg bg-[#F9E3E0] px-4 py-3 text-sm text-[#A8261D]">{{ $message }}</p>
            @enderror

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <flux:button wire:click="reviewAnotherFile" variant="ghost">Cancelar</flux:button>
                <flux:button wire:click="applyImport" variant="primary" icon="check" :disabled="! $preview['can_apply']" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="applyImport">Aplicar cambios</span>
                    <span wire:loading wire:target="applyImport">Aplicando…</span>
                </flux:button>
            </div>
        @endif

        @if ($result)
            <section class="rounded-xl border border-[#BBD8C5] bg-white p-6 shadow-sm sm:p-8">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#E4F1E8] text-[#2C7549]">
                    <flux:icon.check class="size-6" />
                </div>
                <p class="mt-5 text-sm font-semibold uppercase tracking-wide text-[#2C7549]">Cambios aplicados</p>
                <h2 class="font-display mt-1 text-2xl font-semibold">{{ $result['contract_number'] }}</h2>
                <p class="mt-2 text-[#5F584A]">Se crearon {{ $result['created'] }} ítems, se actualizaron {{ $result['updated'] }} y {{ $result['unchanged'] }} quedaron sin cambios.</p>

                @if ($result['photos_pending'])
                    <p class="mt-4 rounded-lg bg-[#FBEEDA] px-4 py-3 text-sm text-[#8F520A]">{{ $result['photos_pending'] }} fotos de Drive quedaron registradas como pendientes. La descarga se implementará en la siguiente etapa.</p>
                @endif

                <div class="mt-6 flex flex-wrap gap-3">
                    <flux:button :href="route('reports.show', $result['report_id'])" variant="primary" icon="eye">Ver informe importado</flux:button>
                    <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>Ir a informes</flux:button>
                    <flux:button wire:click="reviewAnotherFile" variant="ghost" icon="arrow-up-tray">Importar otro archivo</flux:button>
                </div>
            </section>
        @endif

        @if ($this->history->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                <div class="border-b border-[#E3DED3] px-5 py-4">
                    <h2 class="font-display text-lg font-semibold">Historial de cargas</h2>
                </div>
                <div class="divide-y divide-[#E3DED3]">
                    @foreach ($this->history as $historyItem)
                        <div class="flex flex-col justify-between gap-2 px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <p class="font-semibold">{{ $historyItem->report?->contract_number ?? $historyItem->original_name }}</p>
                                <p class="mt-1 text-sm text-[#5F584A]">{{ $historyItem->created_at->format('d/m/Y H:i') }} · {{ $historyItem->original_name }}</p>
                            </div>
                            <span @class([
                                'w-fit rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-[#E4F1E8] text-[#2C7549]' => $historyItem->status === 'applied',
                                'bg-[#F6EEDB] text-[#7F5C12]' => $historyItem->status === 'previewing',
                                'bg-[#F9E3E0] text-[#A8261D]' => $historyItem->status === 'failed',
                            ])>{{ match ($historyItem->status) { 'applied' => 'Aplicada', 'failed' => 'Fallida', default => 'En revisión' } }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
</div>
