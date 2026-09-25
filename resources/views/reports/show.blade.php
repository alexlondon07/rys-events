@php
    $artisticItems = $report->items->where('type', 'artistic')->values();
    $technicalItems = $report->items->where('type', 'technical')->values();
    $photoCount = $report->items->sum(fn ($item) => $item->photos->count());
    $missingPhotos = $report->items->filter(fn ($item) => $item->photos->isEmpty());
    $missingNarratives = $report->items->filter(fn ($item) => blank($item->narrative));
    $step = $report->inferredCurrentStep();
    $steps = [
        ['Contrato', filled($report->contract_number) && filled($report->municipality_id), $report->contract_number],
        ['Evento', filled($report->event_name), $report->event_name ?: 'Por completar'],
        ['Programación artística', $artisticItems->isNotEmpty(), $artisticItems->count().' ítems'],
        ['Técnico y logística', $technicalItems->isNotEmpty(), $technicalItems->count().' ítems'],
        ['Cierre', filled($report->conclusion) && filled($report->signer_name), filled($report->conclusion) && filled($report->signer_name) ? 'Completo' : 'Por completar'],
        ['Revisión y PDF', false, ($missingPhotos->count() + $missingNarratives->count()).' avisos'],
    ];
@endphp

<x-layouts::app :title="'Informe '.$report->contract_number">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 py-4">
        @if (session('status'))
            <div class="flex items-center gap-3 rounded-lg border border-[#BBD8C5] bg-[#F1F8F3] px-4 py-3 text-sm font-semibold text-[#2C7549]">
                <flux:icon.check-circle class="size-5 shrink-0" /> {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="flex items-center gap-3 rounded-lg border border-[#F1C4BE] bg-[#F9E3E0] px-4 py-3 text-sm font-semibold text-[#A8261D]">
                <flux:icon.exclamation-triangle class="size-5 shrink-0" /> {{ session('error') }}
            </div>
        @endif

        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#7F5C12] hover:text-[#17150F]" wire:navigate>
                    <flux:icon.chevron-left class="size-4" /> Informes
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="font-display text-3xl font-bold tracking-tight text-[#17150F]">{{ $report->contract_number }}</h1>
                    <span class="rounded-full bg-[#F6EEDB] px-3 py-1 text-xs font-semibold text-[#7F5C12]">
                        {{ $report->status === 'draft' ? 'Borrador' : 'Finalizado' }}
                    </span>
                </div>
                <p class="mt-2 text-base text-[#5F584A]">
                    {{ $report->municipality?->name ?? 'Municipio pendiente' }}, {{ $report->municipality?->department?->name ?? 'departamento pendiente' }}
                    @if ($report->event_name) · {{ $report->event_name }} @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('reports.import') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#D3CBBB] bg-white px-4 py-2 text-sm font-semibold text-[#17150F] shadow-sm transition hover:border-[#C9A043] hover:bg-[#F6EEDB]" wire:navigate>
                    <flux:icon.arrow-up-tray class="size-4" /> Importar actualización
                </a>
                <flux:button :href="route('reports.edit', $report)" variant="primary" icon="pencil-square" wire:navigate>
                    Editar informe
                </flux:button>
                <flux:button :href="route('reports.preview', $report)" variant="ghost" icon="document-magnifying-glass">
                    Vista previa
                </flux:button>
                @if ($report->pdf_path)
                    <flux:button :href="route('reports.pdf.download', $report)" variant="primary" icon="arrow-down-tray">
                        Descargar PDF
                    </flux:button>
                @endif
                <form method="POST" action="{{ route('reports.pdf.generate', $report) }}" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <flux:button type="submit" variant="outline" icon="document-arrow-down" x-bind:disabled="busy">
                        <span x-show="!busy">{{ $report->pdf_path ? 'Regenerar PDF' : 'Generar PDF' }}</span>
                        <span x-show="busy" x-cloak>Generando…</span>
                    </flux:button>
                </form>
                <flux:button :href="route('reports.excel', $report)" variant="outline" icon="table-cells">
                    Descargar Excel
                </flux:button>
            </div>
        </header>

        <section class="overflow-hidden rounded-xl border border-[#D3CBBB] bg-white shadow-sm">
            <div class="grid sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($steps as $index => [$label, $complete, $detail])
                    <article class="relative border-b border-[#E3DED3] p-4 last:border-0 sm:border-e xl:border-b-0">
                        <div class="flex items-center gap-2.5">
                            <span @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                'bg-[#2C7549] text-white' => $complete,
                                'bg-[#17150F] text-white' => $index + 1 === $step,
                                'bg-[#ECE8E0] text-[#5F584A]' => ! $complete && $index + 1 !== $step,
                            ])>{{ $complete ? '✓' : $index + 1 }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $label }}</p>
                                <p class="mt-0.5 truncate text-xs text-[#5F584A]">{{ $detail }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
            <div class="space-y-6">
                <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#7F5C12]">Revisión antes de generar</p>
                            <h2 class="font-display mt-2 text-xl font-bold">Contenido importado</h2>
                            <p class="mt-1 text-sm text-[#5F584A]">Revise el informe tal como quedará organizado antes de imprimir el borrador.</p>
                        </div>
                        <span class="w-fit rounded-full bg-[#FBEEDA] px-3 py-1.5 text-xs font-semibold text-[#8F520A]">
                            {{ $missingPhotos->count() + $missingNarratives->count() }} avisos
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @if ($missingPhotos->isNotEmpty())
                            <div class="flex items-start gap-3 rounded-lg border border-[#E8D6A8] bg-[#FFFAEC] p-4">
                                <flux:icon.photo class="mt-0.5 size-5 shrink-0 text-[#8F520A]" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#17150F]">{{ $missingPhotos->count() }} ítems sin evidencia fotográfica</p>
                                    <p class="mt-1 text-sm text-[#5F584A]">{{ $missingPhotos->take(4)->pluck('ref')->join(' · ') }}{{ $missingPhotos->count() > 4 ? ' · …' : '' }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($missingNarratives->isNotEmpty())
                            <div class="flex items-start gap-3 rounded-lg border border-[#E8D6A8] bg-[#FFFAEC] p-4">
                                <flux:icon.pencil-square class="mt-0.5 size-5 shrink-0 text-[#8F520A]" />
                                <div>
                                    <p class="font-semibold text-[#17150F]">{{ $missingNarratives->count() }} ítems sin actividad ejecutada</p>
                                    <p class="mt-1 text-sm text-[#5F584A]">El borrador puede visualizarse, pero estos textos deben completarse antes de finalizar.</p>
                                </div>
                            </div>
                        @endif

                        @if ($missingPhotos->isEmpty() && $missingNarratives->isEmpty())
                            <div class="flex items-center gap-3 rounded-lg border border-[#BBD8C5] bg-[#F1F8F3] p-4 text-[#2C7549]">
                                <flux:icon.check-circle class="size-5" />
                                <p class="font-semibold">El contenido importado no tiene pendientes.</p>
                            </div>
                        @endif
                    </div>
                </section>

                @foreach ([['Programación artística', $artisticItems, '#C9A043'], ['Técnico y logística', $technicalItems, '#17150F']] as [$sectionTitle, $sectionItems, $sectionAccent])
                    <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-[#E3DED3] bg-[#FBFAF6] px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="h-4 w-1 rounded-full" style="background-color: {{ $sectionAccent }}"></span>
                                <h2 class="text-[15px] font-semibold tracking-tight text-[#17150F]">{{ $sectionTitle }}</h2>
                            </div>
                            <span class="rounded-full border border-[#E3DED3] bg-white px-2.5 py-0.5 text-[11px] font-semibold tabular-nums text-[#5F584A]">{{ $sectionItems->count() }} ítems</span>
                        </div>
                        <div class="divide-y divide-[#EDE9E0]">
                            @forelse ($sectionItems as $item)
                                @php($complete = filled($item->narrative) && $item->photos->isNotEmpty())
                                <article class="grid gap-2 px-5 py-3 transition hover:bg-[#FBFAF6] md:grid-cols-[72px_minmax(0,1fr)_auto] md:items-center">
                                    <span class="font-mono text-[11px] font-bold tracking-tight text-[#7F5C12]">{{ $item->ref }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold leading-tight text-[#17150F]">{{ $item->artist_name ?: $item->category_label ?: 'Ítem sin nombre' }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-[#8A8274]">{{ $item->specification ?: 'Sin requerimiento registrado' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 md:justify-end">
                                        <span class="rounded-full bg-[#F3F1EC] px-2 py-0.5 text-[11px] font-medium tabular-nums text-[#5F584A]">{{ $item->photos->count() }} fotos</span>
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            'bg-[#E7F3EC] text-[#2C7549]' => $complete,
                                            'bg-[#FBEEDA] text-[#8F520A]' => ! $complete,
                                        ])>{{ $complete ? 'Completo' : 'Pendiente' }}</span>
                                    </div>
                                </article>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-[#5F584A]">No hay ítems en esta sección.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach

                <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-[#E3DED3] bg-[#FBFAF6] px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="h-4 w-1 rounded-full bg-[#17150F]"></span>
                            <div>
                                <h2 class="text-[15px] font-semibold tracking-tight text-[#17150F]">Cargas de Excel</h2>
                                <p class="text-[11px] text-[#8A8274]">Historial versionado del archivo base.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('reports.excel.viewer', ['informe' => $report->id]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#7F5C12] hover:underline">
                                <flux:icon.table-cells class="size-4" /> Visor del Excel
                            </a>
                            <span class="rounded-full border border-[#E3DED3] bg-white px-2.5 py-0.5 text-[11px] font-semibold tabular-nums text-[#5F584A]">{{ $report->imports->count() }}</span>
                        </div>
                    </div>
                    <div class="divide-y divide-[#EDE9E0]">
                        @forelse ($report->imports as $import)
                            <article class="px-5 py-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-[#17150F] px-2.5 py-1 text-xs font-bold text-white">{{ $import->version ? 'v'.$import->version : 'Borrador' }}</span>
                                            <span @class([
                                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-[#E4F1E8] text-[#2C7549]' => $import->status === 'applied',
                                                'bg-[#F6EEDB] text-[#7F5C12]' => $import->status === 'previewing',
                                                'bg-[#F9E3E0] text-[#A8261D]' => $import->status === 'failed',
                                            ])>{{ match ($import->status) { 'applied' => 'Aplicada', 'failed' => 'Fallida', default => 'En revisión' } }}</span>
                                            <span class="truncate text-sm font-semibold">{{ $import->original_name }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-[#5F584A]">
                                            {{ $import->created_at->format('d/m/Y H:i') }} · {{ $import->user?->name ?? 'Sistema' }}
                                            @if ($import->status === 'applied') · {{ $import->summaryLine() }} @endif
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-3">
                                        <a href="{{ route('reports.excel.viewer', ['informe' => $report->id, 'carga' => $import->id]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#5F584A] hover:underline">
                                            <flux:icon.magnifying-glass class="size-4" /> Analizar
                                        </a>
                                        <a href="{{ route('reports.imports.download', [$report, $import]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#7F5C12] hover:underline">
                                            <flux:icon.arrow-down-tray class="size-4" /> Excel
                                        </a>
                                    </div>
                                </div>

                                @if ($import->activityLogs->isNotEmpty())
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-[#5F584A]">Ver qué cambió en esta carga ({{ $import->activityLogs->count() }})</summary>
                                        <ul class="mt-2 space-y-1.5">
                                            @foreach ($import->activityLogs->take(40) as $log)
                                                <li class="break-words text-xs text-[#5F584A]">
                                                    @if ($log->action === 'created')
                                                        <span class="font-semibold text-[#2C7549]">Se creó</span> {{ $log->item_ref ?: 'el informe' }}
                                                    @elseif ($log->action === 'deleted')
                                                        <span class="font-semibold text-[#A8261D]">Se eliminó</span> {{ $log->item_ref ?: 'el informe' }}
                                                    @else
                                                        <span class="font-semibold text-[#17150F]">{{ $log->label }}</span>@if ($log->item_ref) en {{ $log->item_ref }}@endif:
                                                        <span class="line-through">{{ $log->old_value ?? '(vacío)' }}</span> → {{ $log->new_value ?? '(vacío)' }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </article>
                        @empty
                            <p class="px-5 py-8 text-center text-sm text-[#5F584A]">Aún no se ha cargado ningún Excel.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                <section class="rounded-xl border border-[#17150F] bg-[#17150F] p-6 text-white shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#E2C274]">Informe de actividades</p>
                    <p class="font-display mt-3 text-2xl font-bold">{{ $report->contract_number }}</p>
                    <p class="mt-1 text-sm text-[#CFC8B8]">{{ $report->municipality?->name ?? 'Municipio pendiente' }}</p>

                    <dl class="mt-6 grid grid-cols-3 gap-3 border-y border-[#343028] py-5 text-center">
                        <div><dt class="text-xs text-[#A59D8C]">Ítems</dt><dd class="font-display mt-1 text-xl font-bold">{{ $report->items->count() }}</dd></div>
                        <div><dt class="text-xs text-[#A59D8C]">Fotos</dt><dd class="font-display mt-1 text-xl font-bold">{{ $photoCount }}</dd></div>
                        <div><dt class="text-xs text-[#A59D8C]">Paso</dt><dd class="font-display mt-1 text-xl font-bold">{{ $step }}/6</dd></div>
                    </dl>

                    <flux:button :href="route('reports.preview', $report)" class="mt-5 w-full" variant="primary" icon="eye">
                        Ver documento completo
                    </flux:button>
                    <p class="mt-3 text-center text-xs leading-5 text-[#A59D8C]">La vista incluye portada, contrato, contenidos, ítems y cierre.</p>
                </section>

                <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                    <h2 class="font-display font-bold">Datos del contrato</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#5F584A]">Periodo</dt><dd class="mt-1 font-medium">{{ $report->period_start?->format('d/m/Y') ?? '—' }} – {{ $report->period_end?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#5F584A]">Fecha del informe</dt><dd class="mt-1 font-medium">{{ $report->report_date?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#5F584A]">Última importación</dt><dd class="mt-1 font-medium">{{ $report->imports->first()?->applied_at?->format('d/m/Y H:i') ?? 'Sin registro' }}</dd></div>
                    </dl>
                </section>

                <section class="min-w-0 rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display font-bold">Historial de cambios</h2>
                        <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">{{ $activityCount }}</span>
                    </div>

                    <ol class="mt-4 space-y-4">
                        @forelse ($recentActivity as $log)
                            <li class="min-w-0 border-s-2 border-[#E3DED3] ps-3">
                                <p class="break-words text-xs text-[#5F584A]">
                                    {{ $log->created_at?->format('d/m/Y H:i') }} · {{ $log->user?->name ?? 'Sistema' }} ·
                                    <span class="font-semibold text-[#7F5C12]">{{ ['app' => 'Aplicación', 'excel' => 'Carga Excel', 'drive' => 'Drive', 'system' => 'Sistema'][$log->source] ?? $log->source }}</span>
                                </p>
                                <p class="mt-1 break-words text-sm text-[#17150F]">
                                    @if ($log->action === 'created')
                                        Se creó <span class="font-semibold">{{ $log->item_ref ?: 'el informe' }}</span>
                                    @elseif ($log->action === 'deleted')
                                        Se eliminó <span class="font-semibold">{{ $log->item_ref ?: 'el informe' }}</span>
                                    @else
                                        <span class="font-semibold">{{ $log->label }}</span>@if ($log->item_ref) en {{ $log->item_ref }}@endif
                                        <span class="mt-0.5 block break-words text-xs text-[#5F584A]"><span class="line-through">{{ $log->old_value ?? '(vacío)' }}</span> → {{ $log->new_value ?? '(vacío)' }}</span>
                                    @endif
                                </p>
                            </li>
                        @empty
                            <p class="text-sm text-[#5F584A]">Todavía no hay cambios registrados.</p>
                        @endforelse
                    </ol>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
