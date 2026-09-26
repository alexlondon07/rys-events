<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vista previa · {{ $report->contract_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .report-page { width: 210mm; min-height: 296mm; margin: 0 auto 24px; background: white; box-shadow: 0 8px 30px rgba(23, 21, 15, .12); position: relative; overflow: hidden; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .report-watermark { position: absolute; inset: 0; display: none; place-items: center; pointer-events: none; font: 800 64px Archivo, sans-serif; letter-spacing: .16em; color: rgba(143, 82, 10, .065); transform: rotate(-28deg); }
        .report-is-draft .report-watermark { display: grid; }
        .report-header { height: 20mm; background: #0F0E0B; color: white; display: flex; align-items: center; justify-content: space-between; padding: 0 18mm; border-bottom: 2px solid #C9A043; }
        .report-footer { position: absolute; bottom: 0; left: 0; right: 0; height: 13mm; background: #0F0E0B; border-top: 2px solid #C9A043; color: #CFC8B8; display: flex; align-items: center; justify-content: space-between; padding: 0 18mm; font-size: 10px; }
        .report-content { padding: 18mm 18mm 27mm; }
        .cover-photo { position: absolute; inset: 0 0 auto auto; width: 78%; height: 61%; object-fit: cover; object-position: center 42%; clip-path: polygon(31% 0, 100% 0, 100% 100%, 0 94%); }
        .cover-brand { position: absolute; inset: 0 auto auto 0; width: 48%; height: 42%; background: #050504; clip-path: polygon(0 0, 100% 0, 67% 100%, 0 65%); }
        .cover-brand::after { content: ''; position: absolute; left: -8%; bottom: 21%; width: 110%; height: 2px; background: #C9A043; transform: rotate(33deg); transform-origin: left center; }
        .cover-logo { position: absolute; left: 22mm; top: 10mm; display: grid; width: 46mm; height: 46mm; place-items: center; border: 2px solid #C9A043; border-radius: 9999px; color: #E2C274; font: 700 30px Archivo, sans-serif; letter-spacing: -.04em; }
        .cover-copy { position: absolute; left: 10mm; right: 14mm; top: 188mm; z-index: 2; text-align: left; }
        .cover-bottom { position: absolute; inset: auto 0 0; height: 27mm; background: #050504; clip-path: polygon(0 82%, 100% 30%, 100% 100%, 0 100%); }
        .cover-bottom::before { content: ''; position: absolute; left: -2%; right: -2%; top: 47%; height: 2px; background: #C9A043; transform: rotate(-4deg); }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: white !important; }
            main { padding: 0 !important; }
            .preview-toolbar { display: none !important; }
            .report-page { margin: 0; box-shadow: none; break-after: page; }
            .report-page:last-child { break-after: auto; }
        }
    </style>
</head>
<body class="bg-[#E9E5DC] text-[#17150F] {{ $report->status === 'draft' ? 'report-is-draft' : '' }}">
    <div class="preview-toolbar sticky top-0 z-50 border-b border-[#D3CBBB] bg-[#F3F1EC]/95 px-5 py-3 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <a href="{{ route('reports.show', $report) }}" class="text-sm font-semibold text-[#7F5C12]">← Volver a revisión</a>
                <p class="mt-0.5 text-sm text-[#5F584A]">Vista previa {{ $report->status === 'draft' ? 'del borrador' : 'del informe final' }} · {{ $report->contract_number }}</p>
            </div>
            <button onclick="window.print()" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#17150F] px-5 text-sm font-semibold text-white hover:bg-[#2A2720]">
                Imprimir o guardar como PDF
            </button>
        </div>
    </div>

    <main class="py-8">
        <section class="report-page bg-white">
            <div class="report-watermark">BORRADOR</div>
            @php($coverImage = $report->coverImageUrl() ?? (strtolower((string) $report->municipality?->name) === 'guadalupe' ? asset('images/reports/guadalupe-cover-photo.png') : null))
            @if ($coverImage)
                <img src="{{ $coverImage }}" alt="Foto del evento" class="cover-photo" referrerpolicy="no-referrer">
            @else
                <div class="cover-photo bg-[#DED8CC]"></div>
            @endif
            <div class="cover-brand">
                <div class="cover-logo">RYS</div>
            </div>
            <div class="cover-copy">
                <h1 class="font-display text-[46px] font-extrabold leading-[.96] tracking-[-.035em] text-black">INFORME DE<br>ACTIVIDADES</h1>
                <p class="font-display mt-5 text-[24px] font-bold uppercase leading-tight">NO - {{ $report->contract_number }}</p>
                <p class="font-display mt-4 max-w-[155mm] text-[20px] font-bold uppercase leading-tight">
                    MUNICIPIO DE {{ $report->municipality?->name ?? 'MUNICIPIO PENDIENTE' }} -<br>
                    {{ $report->municipality?->department?->name ?? 'DEPARTAMENTO PENDIENTE' }}
                </p>
            </div>
            <div class="cover-bottom"></div>
        </section>

        <section class="report-page">
            <div class="report-watermark">BORRADOR</div>
            @include('reports.partials.preview-header')
            <div class="report-content">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">01 · Información contractual</p>
                <h2 class="font-display mt-3 text-3xl font-bold">Ficha del contrato</h2>
                <div class="mt-8 grid grid-cols-2 border border-[#D3CBBB]">
                    @foreach ([
                        ['Número de contrato', $report->contract_number],
                        ['Fecha del informe', $report->report_date?->format('d/m/Y') ?? '—'],
                        ['Periodo desde', $report->period_start?->format('d/m/Y') ?? '—'],
                        ['Periodo hasta', $report->period_end?->format('d/m/Y') ?? '—'],
                        ['Municipio', ($report->municipality?->name ?? '—').', '.($report->municipality?->department?->name ?? '—')],
                        ['Asunto', $report->subject ?: 'Informe de actividades'],
                    ] as [$label, $value])
                        <div class="border-b border-e border-[#E3DED3] p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#5F584A]">{{ $label }}</p>
                            <p class="mt-1.5 text-sm font-semibold">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <h3 class="font-display mt-8 text-lg font-bold">Objeto del contrato</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-[#3E392F]">{{ $report->contract_object ?: 'Pendiente por completar.' }}</p>
            </div>
            @include('reports.partials.preview-footer', ['pageLabel' => 'Ficha del contrato'])
        </section>

        <section class="report-page">
            <div class="report-watermark">BORRADOR</div>
            @include('reports.partials.preview-header')
            <div class="report-content">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">02 · Evento</p>
                <h2 class="font-display mt-3 text-3xl font-bold">{{ $report->event_name ?: 'Evento por completar' }}</h2>
                <p class="mt-2 text-sm font-semibold text-[#5F584A]">{{ $report->event_start?->format('d/m/Y') ?? '—' }} – {{ $report->event_end?->format('d/m/Y') ?? '—' }}</p>
                <h3 class="font-display mt-9 text-lg font-bold">Introducción</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-[#3E392F]">{{ $report->introduction ?: 'Pendiente por completar.' }}</p>
                <h3 class="font-display mt-9 text-lg font-bold">Descripción del evento</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-[#3E392F]">{{ $report->event_description ?: 'Pendiente por completar.' }}</p>
            </div>
            @include('reports.partials.preview-footer', ['pageLabel' => 'Evento'])
        </section>

        @foreach ($report->items as $item)
            @php($layout = $item->photo_layout ?: 'pair')
            @php($gridClass = $layout === 'single' ? 'grid-cols-1' : 'grid-cols-2')
            @php($imageClass = $layout === 'single' ? 'h-[140mm]' : 'h-64')
            @php($evidence = $item->evidenceLink())

            <section class="report-page">
                <div class="report-watermark">BORRADOR</div>
                @include('reports.partials.preview-header')
                <div class="report-content">
                    <div class="flex items-start justify-between gap-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">{{ $item->type === 'artistic' ? 'Programación artística' : 'Técnico y logística' }} · {{ $item->ref }}</p>
                            <h2 class="font-display mt-3 text-2xl font-bold">{{ $item->artist_name ?: $item->category_label ?: 'Ítem sin nombre' }}</h2>
                        </div>
                        @if ($item->quantity)
                            <div class="rounded-lg bg-[#F6EEDB] px-4 py-3 text-center"><strong class="block text-xl text-[#7F5C12]">{{ (float) $item->quantity }}</strong><span class="text-xs text-[#5F584A]">{{ $item->unit }}</span></div>
                        @endif
                    </div>

                    <div class="mt-7 overflow-hidden rounded-lg border border-[#D3CBBB]">
                        <div class="grid grid-cols-[160px_1fr] border-b border-[#E3DED3]"><p class="bg-[#F3F1EC] p-4 text-xs font-bold uppercase text-[#5F584A]">Categoría</p><p class="p-4 text-sm font-semibold">{{ $item->category_label ?: '—' }}</p></div>
                        <div class="grid grid-cols-[160px_1fr] border-b border-[#E3DED3]"><p class="bg-[#F3F1EC] p-4 text-xs font-bold uppercase text-[#5F584A]">Requerimiento</p><p class="whitespace-pre-line p-4 text-sm leading-6">{{ $item->specification ?: '—' }}</p></div>
                        <div class="grid grid-cols-[160px_1fr]">
                            <p class="bg-[#F3F1EC] p-4 text-xs font-bold uppercase text-[#5F584A]">Actividad ejecutada</p>
                            <div class="p-4 text-sm leading-6">
                                <p class="whitespace-pre-line">{{ $item->narrative ?: 'Pendiente por completar.' }}</p>
                                @if (! empty($standardTexts[$item->id] ?? ''))
                                    <p class="mt-3 whitespace-pre-line text-[#3E392F]">{{ $standardTexts[$item->id] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($item->photos->isEmpty() && ! $evidence)
                        <div class="mt-6 flex h-32 flex-col items-center justify-center rounded-lg border-2 border-dashed border-[#D3CBBB] bg-[#FBFAF6] text-center">
                            <p class="font-semibold text-[#5F584A]">Sin evidencia fotográfica</p>
                            <p class="mt-1 text-xs text-[#8A8274]">Agregue las fotos en el asistente de edición.</p>
                        </div>
                    @endif
                </div>
                @include('reports.partials.preview-footer', ['pageLabel' => $item->ref])
            </section>

            @if ($item->photos->isNotEmpty())
                @foreach ($item->photos->chunk($item->photosPerPage()) as $chunkIndex => $photoChunk)
                    @php($collage = ($collages ?? [])[$item->id][$chunkIndex] ?? null)
                    <section class="report-page">
                        <div class="report-watermark">BORRADOR</div>
                        @include('reports.partials.preview-header')
                        <div class="report-content">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">{{ $item->ref }} · Evidencia fotográfica</p>
                            <h2 class="font-display mt-2 text-xl font-bold">{{ $item->artist_name ?: $item->category_label ?: 'Ítem' }}</h2>
                            @if ($layout === 'collage' && $collage)
                                @php($legend = $photoChunk->pluck('caption')->filter()->implode(' · '))
                                <figure class="mt-5 overflow-hidden rounded-lg border border-[#E3DED3]">
                                    <img src="{{ $collage }}" alt="Collage de evidencia" class="w-full object-contain" referrerpolicy="no-referrer">
                                    <figcaption class="p-3 text-xs text-[#5F584A]">{{ $legend ?: 'Sin título' }}</figcaption>
                                </figure>
                            @else
                                <div class="mt-5 grid {{ $gridClass }} gap-4">
                                    @foreach ($photoChunk as $photo)
                                        <figure class="overflow-hidden rounded-lg border border-[#E3DED3]">
                                            @if ($photo->fullUrl())
                                                <img src="{{ $photo->fullUrl() }}" alt="{{ $photo->caption }}" class="{{ $imageClass }} w-full object-cover" referrerpolicy="no-referrer">
                                            @endif
                                            <figcaption class="p-3 text-xs text-[#5F584A]">{{ $photo->caption ?: 'Sin título' }}</figcaption>
                                        </figure>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @include('reports.partials.preview-footer', ['pageLabel' => $item->ref.' · fotos'])
                    </section>
                @endforeach
            @endif

            @if ($evidence)
                <section class="report-page">
                    <div class="report-watermark">BORRADOR</div>
                    @include('reports.partials.preview-header')
                    <div class="report-content">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">{{ $item->ref }} · Evidencia ({{ $evidence->label() }})</p>
                        <h2 class="font-display mt-2 text-xl font-bold">{{ $item->artist_name ?: $item->category_label ?: 'Ítem' }}</h2>
                        @if ($evidence->isServerSafe() && $evidence->imageUrl())
                            <figure class="mt-5 overflow-hidden rounded-lg border border-[#D3CBBB]">
                                <img src="{{ $evidence->imageUrl() }}" alt="Evidencia de {{ $item->ref }}" class="h-[210mm] w-full object-contain" referrerpolicy="no-referrer">
                            </figure>
                        @elseif ($evidence->isServerSafe() && $evidence->embedUrl())
                            <div class="mt-5 overflow-hidden rounded-lg border border-[#D3CBBB]">
                                <iframe src="{{ $evidence->embedUrl() }}" class="h-[210mm] w-full" loading="lazy"></iframe>
                            </div>
                        @else
                            <div class="mt-5 rounded-lg border border-[#D3CBBB] bg-[#FBFAF6] p-5 text-sm text-[#5F584A]">
                                <p class="font-semibold text-[#17150F]">Evidencia por enlace externo</p>
                                <p class="mt-1">Por seguridad, este enlace no se incrusta en el informe. Ábralo directamente:</p>
                                <p class="mt-2 break-all font-mono text-xs text-[#7F5C12]">{{ $evidence->openUrl() }}</p>
                            </div>
                        @endif
                    </div>
                    @include('reports.partials.preview-footer', ['pageLabel' => $item->ref.' · evidencia'])
                </section>
            @endif
        @endforeach

        <section class="report-page">
            <div class="report-watermark">BORRADOR</div>
            @include('reports.partials.preview-header')
            <div class="report-content">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#7F5C12]">Cierre</p>
                <h2 class="font-display mt-3 text-3xl font-bold">Conclusión</h2>
                <p class="mt-7 whitespace-pre-line text-sm leading-7 text-[#3E392F]">{{ $report->conclusion ?: 'Pendiente por completar.' }}</p>

                @if (! empty($company?->signature_path))
                    <img src="{{ asset('storage/'.$company->signature_path) }}" alt="Firma" class="mt-16 h-20 w-auto object-contain">
                @endif

                <div class="mt-8 border-t border-[#17150F] pt-4">
                    <p class="font-display text-lg font-bold">{{ $report->signer_name ?: ($company?->legal_rep_name ?: 'Firmante pendiente') }}</p>
                    <p class="mt-1 text-sm text-[#5F584A]">{{ $company?->legal_rep_title ?: 'Representante Legal' }} · {{ $company?->name ?: 'Grupo RYS S.A.S.' }}</p>
                </div>
            </div>
            @include('reports.partials.preview-footer', ['pageLabel' => 'Cierre'])
        </section>
    </main>
</body>
</html>
