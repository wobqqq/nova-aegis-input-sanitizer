<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('aegis-input-sanitizer::input-sanitizer.blocked.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; }
        p { margin: 0; color: #475569; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('aegis-input-sanitizer::input-sanitizer.blocked.title') }}</h1>
        <p>{{ __('aegis-input-sanitizer::input-sanitizer.blocked.message') }}</p>
    </main>
</body>
</html>
