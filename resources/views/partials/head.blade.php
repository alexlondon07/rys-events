<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Grupo RYS usa un tema claro fijo. Forzamos la preferencia antes de que
     Flux la lea para que nunca aplique el tema oscuro del sistema operativo. --}}
<script>
    (function () {
        try { window.localStorage.setItem('flux.appearance', 'light'); } catch (error) {}
    })();
</script>
@fluxAppearance
