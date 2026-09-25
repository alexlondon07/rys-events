<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ config('app.name', 'Grupo RYS') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #F3F1EC; color: #17150F; font-family: Figtree, ui-sans-serif, system-ui, -apple-system, sans-serif; padding: 24px; }
        .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #E3DED3; border-radius: 16px; padding: 40px; text-align: center; box-shadow: 0 8px 30px rgba(23, 21, 15, .06); }
        .badge { display: inline-grid; place-items: center; width: 56px; height: 56px; border-radius: 9999px; border: 1px solid #C9A043; background: #1E1C17; color: #E2C274; font-weight: 700; }
        .code { font-size: 44px; font-weight: 800; margin: 24px 0 0; }
        h1 { font-size: 20px; margin: 8px 0 0; }
        p { color: #5F584A; font-size: 14px; line-height: 1.6; margin: 8px 0 0; }
        a { display: inline-block; margin-top: 24px; background: #17150F; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">RYS</span>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">Volver al inicio</a>
    </div>
</body>
</html>
