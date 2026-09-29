@php
    $artisticItems = $report->items->where('type', 'artistic')->values();
    $technicalItems = $report->items->where('type', 'technical')->values();
    $photoCount = $report->items->sum(fn ($item) => $item->photos->count());
    $missingPhotos = $report->items->filter(fn ($item) => $item->photos->isEmpty());
    $missingNarratives = $report->items->filter(fn ($item) => blank($item->narrative));
    $latestAiRun = config('reports.ai.enabled') ? $report->aiRuns->first() : null;
    $aiResult = $latestAiRun?->result ?? [];
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

        <div x-data="{
            status: @js($report->pdf_status ?: 'idle'),
            error: @js($report->pdf_error),
            timer: null,
            get busy() { return this.status === 'queued' || this.status === 'processing'; },
            init() { if (this.busy) this.start(); },
            start() { this.timer = setInterval(() => this.check(), 4000); },
            async check() {
                try {
                    const response = await fetch(@js(route('reports.pdf.status', $report)), { headers: { 'Accept': 'application/json' } });
                    if (! response.ok) return;
                    const data = await response.json();
                    this.status = data.status;
                    this.error = data.error;
                    if (data.status === 'ready' || data.status === 'failed') {
                        clearInterval(this.timer);
                        window.location.reload();
                    }
                } catch (exception) {}
            },
        }">
        <header class="flex flex-col justify-between gap-5 border-b border-[#D8D1C2] pb-6 lg:flex-row lg:items-end">
            <div class="border-l-2 border-[#C9A043] pl-4">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#7F5C12] hover:text-[#17150F]" wire:navigate>
                    <flux:icon.chevron-left class="size-4" /> Informes
                </a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="font-display text-3xl font-bold tracking-[-0.03em] text-[#17150F]">{{ $report->contract_number }}</h1>
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
                    <flux:button :href="route('reports.pdf.download', $report)" variant="primary" icon="arrow-down-tray" x-show="!busy">
                        Descargar PDF
                    </flux:button>
                @endif
                <form method="POST" action="{{ route('reports.pdf.generate', $report) }}" @submit="if (busy) { $event.preventDefault(); }">
                    @csrf
                    <flux:button type="submit" variant="outline" icon="document-arrow-down" x-bind:disabled="busy">
                        <span x-show="!busy">{{ $report->pdf_path ? 'Regenerar PDF' : 'Generar PDF' }}</span>
                        <span x-show="busy" x-cloak>Generando…</span>
                    </flux:button>
                </form>
                @if (config('reports.ai.enabled'))
                    <form method="POST" action="{{ route('reports.ai.generate', $report) }}">
                        @csrf
                        <flux:button type="submit" variant="outline" icon="sparkles"
                            :disabled="in_array($latestAiRun?->status, ['queued', 'processing'], true)">
                            {{ in_array($latestAiRun?->status, ['queued', 'processing'], true) ? 'Analizando…' : 'Generar borrador IA' }}
                        </flux:button>
                    </form>
                @endif
                <flux:button :href="route('reports.excel', $report)" variant="outline" icon="table-cells">
                    Descargar Excel
                </flux:button>
                @if ($report->status === 'final')
                    @if ($report->public_share_token)
                        <a href="{{ route('reports.public.show', $report->public_share_token) }}" target="_blank" class="inline-flex items-center gap-2 border border-[#C9A043] bg-[#F6EEDB] px-4 py-2 text-sm font-semibold text-[#7F5C12]">
                            <flux:icon.arrow-top-right-on-square class="size-4" /> Portal del cliente
                        </a>
                        <form id="revoke-public-report-{{ $report->id }}" method="POST" action="{{ route('reports.public.revoke', $report) }}">
                            @csrf @method('DELETE')
                            <x-confirm-action name="revoke-public-report-modal-{{ $report->id }}" action="" form="revoke-public-report-{{ $report->id }}" title="Revocar enlace público" message="El cliente ya no podrá consultar este informe con el enlace actual." confirm-label="Revocar enlace">
                                <x-slot:trigger><flux:button type="button" variant="ghost" icon="no-symbol">Revocar enlace</flux:button></x-slot:trigger>
                            </x-confirm-action>
                        </form>
                    @else
                        <form method="POST" action="{{ route('reports.public.create', $report) }}">
                            @csrf
                            <flux:button type="submit" variant="outline" icon="share">Crear enlace para cliente</flux:button>
                        </form>
                    @endif
                @else
                    <span class="inline-flex items-center gap-2 border border-dashed border-[#D3CBBB] bg-[#FAF9F6] px-4 py-2 text-sm font-semibold text-[#5F584A]" title="Finalice el informe para generar un enlace privado para el cliente.">
                        <flux:icon.share class="size-4 text-[#7F5C12]" /> Portal del cliente · disponible al finalizar
                    </span>
                @endif
                @if (app(\App\Services\Drive\DriveClient::class)->isConfigured())
                    <form method="POST" action="{{ route('reports.drive.sync', $report) }}">
                        @csrf
                        <flux:button type="submit" variant="outline" icon="arrow-down-tray" title="Trae al sistema las fotos de Drive de todos los ítems">
                            Sincronizar Drive
                        </flux:button>
                    </form>
                @endif
            </div>
        </header>

        <div x-show="busy || status === 'failed'" x-cloak class="rounded-xl border px-5 py-4 shadow-sm"
            :class="status === 'failed' ? 'border-[#F1C4BE] bg-[#F9E3E0]' : 'border-[#E8D6A8] bg-[#FBEEDA]'">
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full" :class="status === 'failed' ? 'bg-[#F3C9C3] text-[#A8261D]' : 'bg-[#F3E2BE] text-[#8F520A]'">
                    <template x-if="status === 'failed'"><flux:icon.exclamation-triangle class="size-5" /></template>
                    <template x-if="status !== 'failed'"><flux:icon.arrow-path class="size-5 animate-spin" /></template>
                </span>
                <div class="min-w-0">
                    <p class="font-semibold" :class="status === 'failed' ? 'text-[#A8261D]' : 'text-[#8F520A]'"
                        x-text="status === 'failed' ? 'No se pudo generar el PDF' : 'Generando el PDF en segundo plano…'"></p>
                    <p class="mt-1 text-sm text-[#5F584A]" x-show="status === 'failed'" x-text="error || 'Vuelva a intentarlo. Verifique que Chrome esté disponible.'"></p>
                    <p class="mt-1 text-sm text-[#5F584A]" x-show="status !== 'failed'">Puede seguir trabajando; la descarga se habilita sola al terminar.</p>
                </div>
            </div>
        </div>
        </div>

        <section class="overflow-hidden border border-[#D3CBBB] bg-white">
            <div class="grid sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($steps as $index => [$label, $complete, $detail])
                    <article class="relative border-b border-[#E3DED3] p-4 last:border-0 sm:border-e xl:border-b-0">
                        <div class="flex items-center gap-2.5">
                            <span @class([
                            'flex size-7 shrink-0 items-center justify-center text-xs font-bold',
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
                <section class="border border-[#D3CBBB] bg-white p-6">
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

                @if (config('reports.ai.enabled'))
                    <section x-data="{
                        status: @js($latestAiRun?->status ?? 'idle'),
                        timer: null,
                        get busy() { return this.status === 'queued' || this.status === 'processing'; },
                        init() { if (this.busy) this.start(); },
                        start() {
                            if (this.timer) return;
                            this.timer = setInterval(() => this.check(), 4000);
                        },
                        async check() {
                            try {
                                const response = await fetch(@js(route('reports.ai.status', $report)), { headers: { 'Accept': 'application/json' } });
                                if (! response.ok) return;
                                const data = await response.json();
                                this.status = data.status;
                                if (['ready', 'failed', 'cancelled'].includes(data.status)) {
                                    clearInterval(this.timer);
                                    window.location.reload();
                                }
                            } catch (exception) {}
                        },
                    }" class="overflow-hidden border border-[#D7C7F2] bg-[#FCFAFF]">
                        <div class="flex flex-col justify-between gap-4 border-b border-[#E8DDF7] px-6 py-5 sm:flex-row sm:items-start">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#7042A5]">Agente IA de informes</p>
                                <h2 class="font-display mt-2 text-xl font-bold text-[#17150F]">Borrador técnico revisable</h2>
                                <p class="mt-1 max-w-2xl text-sm text-[#5F584A]">Revisa redacción, pendientes, hallazgos y recomendaciones. Nada se aplica al informe hasta que un usuario lo apruebe.</p>
                            </div>
                            <span class="w-fit rounded-full bg-[#EEE5FA] px-3 py-1.5 text-xs font-semibold text-[#7042A5]">{{ $latestAiRun?->model ?? config('reports.ai.model') }}</span>
                        </div>

                        @if ($latestAiRun?->status === 'queued' || $latestAiRun?->status === 'processing')
                            <div class="flex flex-col justify-between gap-4 px-6 py-5 sm:flex-row sm:items-center" x-show="busy">
                                <div class="flex items-center gap-3 text-sm font-semibold text-[#7042A5]">
                                    <flux:icon.arrow-path class="size-5 animate-spin" />
                                    @if ($latestAiRun->status === 'queued')
                                        En cola: esperando a que un trabajador inicie el borrador…
                                    @else
                                        El agente está redactando y revisando el informe…
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('reports.ai.cancel', [$report, $latestAiRun]) }}">
                                    @csrf
                                    <flux:button type="submit" variant="outline" icon="x-mark">Cancelar generación</flux:button>
                                </form>
                            </div>
                            <p class="px-6 pb-4 text-xs text-[#8A8274]">Si la solicitud ya llegó al proveedor, puede terminar y generar costo; el resultado se descartará.</p>
                        @elseif ($latestAiRun?->status === 'failed')
                            <div class="m-5 flex items-start gap-3 rounded-lg border border-[#F1C4BE] bg-[#F9E3E0] p-4 text-sm text-[#A8261D]">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                                <div><p class="font-semibold">No se pudo generar el borrador IA.</p><p class="mt-1">{{ $latestAiRun->error }}</p></div>
                            </div>
                        @elseif ($latestAiRun?->status === 'cancelled')
                            <div class="m-5 rounded-lg border border-[#E3DED3] bg-[#F7F5F0] p-4 text-sm text-[#5F584A]">
                                Generación cancelada. Puede iniciar un nuevo borrador cuando lo desee.
                            </div>
                        @elseif ($latestAiRun?->status === 'ready' || $latestAiRun?->status === 'approved')
                            <div class="space-y-5 p-6">
                                <div class="rounded-lg border border-[#D7C7F2] bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#7042A5]">Resumen ejecutivo</p>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#3F3A32]">{{ $aiResult['executive_summary'] ?? 'Sin resumen disponible.' }}</p>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-3">
                                    @foreach ([
                                        'introduction' => 'Introducción propuesta',
                                        'event_description' => 'Descripción propuesta',
                                        'conclusion' => 'Conclusión propuesta',
                                    ] as $field => $label)
                                        <article class="rounded-lg border border-[#E8DDF7] bg-white p-4">
                                            <p class="text-xs font-bold uppercase tracking-[0.08em] text-[#7042A5]">{{ $label }}</p>
                                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#3F3A32]">{{ data_get($aiResult, "report.{$field}") ?: 'Sin propuesta.' }}</p>
                                        </article>
                                    @endforeach
                                </div>

                                @if (! empty($aiResult['items']))
                                    <details class="rounded-lg border border-[#E8DDF7] bg-white">
                                        <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-[#17150F]">
                                            Propuestas de redacción por ítem ({{ count($aiResult['items']) }})
                                        </summary>
                                        <div class="divide-y divide-[#E8DDF7] border-t border-[#E8DDF7]">
                                            @foreach (array_slice((array) $aiResult['items'], 0, 20) as $suggestion)
                                                <article class="space-y-2 px-4 py-4 text-sm">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="font-mono font-bold text-[#7042A5]">{{ $suggestion['ref'] ?? 'Sin referencia' }}</span>
                                                        <span class="rounded-full bg-[#F3F1EC] px-2 py-0.5 text-xs font-semibold text-[#5F584A]">{{ $suggestion['priority'] ?? 'media' }}</span>
                                                    </div>
                                                    <p class="text-[#3F3A32]"><strong>Texto:</strong> {{ $suggestion['revised_narrative'] ?? 'Sin propuesta.' }}</p>
                                                    <p class="text-[#5F584A]"><strong>Hallazgo:</strong> {{ $suggestion['finding'] ?? 'Sin hallazgo.' }}</p>
                                                    <p class="text-[#5F584A]"><strong>Recomendación:</strong> {{ $suggestion['recommendation'] ?? 'Sin recomendación.' }}</p>
                                                </article>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif

                                @if (! empty($aiResult['quality_issues']))
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <h3 class="text-sm font-bold text-[#17150F]">Revisiones detectadas</h3>
                                            <span class="rounded-full bg-[#FBEEDA] px-2.5 py-1 text-xs font-semibold text-[#8F520A]">{{ count($aiResult['quality_issues']) }}</span>
                                        </div>
                                        <ul class="space-y-2">
                                            @foreach ($aiResult['quality_issues'] as $issue)
                                                <li class="rounded-lg border border-[#E8D6A8] bg-[#FFFAEC] p-3 text-sm text-[#5F584A]"><span class="font-bold text-[#8F520A]">{{ strtoupper($issue['priority'] ?? 'media') }}</span> · {{ $issue['message'] ?? '' }} <span class="text-xs">({{ $issue['source_ref'] ?? 'informe' }})</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#E8DDF7] pt-4">
                                    <p class="text-xs text-[#8A8274]">
                                        Ejecución {{ $latestAiRun->created_at?->format('d/m/Y H:i') }} ·
                                        {{ $latestAiRun->input_tokens ?? '—' }} tokens de entrada ·
                                        {{ $latestAiRun->output_tokens ?? '—' }} de salida
                                        @if ($latestAiRun->started_at && $latestAiRun->completed_at)
                                            · {{ $latestAiRun->started_at->diffInSeconds($latestAiRun->completed_at) }} s
                                        @endif
                                        · costo estimado USD {{ $latestAiRun->cost_usd ?? '—' }} · prompt {{ $latestAiRun->prompt_version }}
                                    </p>
                                    @if ($latestAiRun->status === 'ready')
                                        <form method="POST" action="{{ route('reports.ai.approve', [$report, $latestAiRun]) }}">
                                            @csrf
                                            <flux:button type="submit" variant="primary" icon="check">Aprobar y aplicar textos</flux:button>
                                        </form>
                                    @else
                                        <span class="rounded-full bg-[#E7F3EC] px-3 py-1.5 text-xs font-semibold text-[#2C7549]">Aprobado</span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-3 px-6 py-5 text-sm text-[#5F584A]">
                                <flux:icon.sparkles class="size-5 text-[#7042A5]" /> Genere un borrador para revisar el informe con IA.
                            </div>
                        @endif
                    </section>
                @endif

                @foreach ([['Programación artística', $artisticItems, '#C9A043'], ['Técnico y logística', $technicalItems, '#17150F']] as [$sectionTitle, $sectionItems, $sectionAccent])
                    <section class="overflow-hidden border border-[#D3CBBB] bg-white">
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
                                        <a href="{{ route('reports.show', ['report' => $report, 'item' => $item->ref]) }}#historial" class="text-[11px] font-semibold text-[#7F5C12] hover:underline" title="Ver cambios de este ítem">Cambios</a>
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

                <section class="overflow-hidden border border-[#D3CBBB] bg-white">
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

                <section id="historial" class="min-w-0 rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-display font-bold">Historial de cambios</h2>
                            @if ($selectedItem)
                                <p class="mt-0.5 text-xs text-[#8A8274]">Filtrando por <span class="font-semibold text-[#7F5C12]">{{ $selectedItem }}</span></p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($selectedItem)
                                <a href="{{ route('reports.show', $report) }}#historial" class="text-xs font-semibold text-[#7F5C12] hover:underline">Ver todo</a>
                            @endif
                            <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">{{ $activityCount }}</span>
                        </div>
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
