<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ __('Slovnik') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap">
    <style>
        /* The app's look (app/src/css/app.css), for the one page served from here. */
        :root { --bg: #f5fbf8; --ink: #171d1b; --muted: #3f4946; --outline: #6f7976; --primary: #0e6b5e; --tonal: #cce8e1; --danger: #ba1a1a; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: 'Onest', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 440px; min-height: 100vh; margin: 0 auto; padding: 72px 24px 28px; display: flex; flex-direction: column; }
        .badge { width: 64px; height: 64px; border-radius: 32px; background: var(--tonal); color: var(--primary); display: flex; align-items: center; justify-content: center; }
        h1 { margin: 24px 0 8px; font-size: 28px; line-height: 36px; font-weight: 700; letter-spacing: -0.01em; }
        p { margin: 0; font-size: 15px; line-height: 22px; color: var(--muted); }
        form { display: flex; flex-direction: column; gap: 24px; margin-top: 32px; }
        .field { position: relative; }
        .field label { position: absolute; top: -9px; left: 12px; padding: 0 4px; background: var(--bg); font-size: 12px; line-height: 16px; font-weight: 500; color: var(--muted); }
        .field input { width: 100%; height: 56px; padding: 0 16px; border: 1px solid var(--outline); border-radius: 6px; background: transparent; font: inherit; font-size: 16px; color: var(--ink); outline: none; }
        .field input:focus { border: 2px solid var(--primary); padding-left: 15px; }
        .field:focus-within label { color: var(--primary); font-weight: 600; }
        .field.error input { border: 2px solid var(--danger); padding-left: 15px; }
        .field.error label { color: var(--danger); }
        .help { display: block; margin-top: 4px; padding-left: 16px; font-size: 12px; line-height: 16px; color: var(--muted); }
        .field.error + .help { color: var(--danger); }
        button { height: 52px; border: none; border-radius: 26px; background: var(--primary); color: #fff; font: inherit; font-size: 16px; font-weight: 600; cursor: pointer; }
        button:hover { background: #0a5248; }
    </style>
</head>
<body>
<main>
    @yield('content')
</main>
</body>
</html>
