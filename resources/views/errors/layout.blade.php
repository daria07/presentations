{{--
   Общая обложка для страниц ошибок.

   Нарочно без @vite и без Inertia: половина этих страниц показывается
   как раз тогда, когда приложение собрать или запустить не удалось.
   Стили здесь свои, но цвета и шрифт — те же, что на лендингах
   (тёплая бумага и терракотовый знак), чтобы страница не выглядела
   чужой.
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        :root {
            --paper: #faf8f4;
            --ink: #16140f;
            --muted: #6f6a60;
            --rule: #e2dcd1;
            --card: #ffffff;
            --brand: hsl(17 62% 44%);
            --brand-ink: hsl(17 66% 34%);
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: 'Onest', 'Golos Text', ui-sans-serif, system-ui,
                -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--card);
            border-radius: 16px;
            padding: 36px 32px 32px;
            text-align: center;
        }

        .mark {
            display: block;
            width: 36px;
            height: 36px;
            margin: 0 auto 20px;
            color: var(--brand);
        }

        .code {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 10px;
        }

        h1 {
            font-size: 26px;
            line-height: 1.25;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0 0 12px;
            text-wrap: balance;
        }

        p.lead {
            margin: 0;
            color: var(--muted);
            text-wrap: pretty;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 28px;
        }

        a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease;
        }

        a.primary {
            background: var(--brand);
            color: #fff;
        }

        a.secondary {
            border: 1px solid var(--rule);
            color: var(--ink);
            background: transparent;
        }

        @media (hover: hover) {
            a.primary:hover { background: var(--brand-ink); }
            a.secondary:hover { background: #f4f1eb; }
        }

        a.button:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 2px;
        }

        footer {
            padding: 0 20px 28px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        footer a { color: inherit; }

        @media (max-width: 420px) {
            .card { padding: 28px 20px 24px; }
            h1 { font-size: 22px; }
            .actions a.button { width: 100%; }
        }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <svg class="mark" viewBox="0 0 64 64" role="img" aria-label="{{ config('app.name') }}">
                <path d="M32 2 Q35.6 28.4 62 32 Q35.6 35.6 32 62 Q28.4 35.6 2 32 Q28.4 28.4 32 2 Z" fill="currentColor"/>
            </svg>

            <p class="code">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p class="lead">@yield('message')</p>

            <div class="actions">
                @yield('actions')
            </div>
        </div>
    </main>

    <footer>
        {{ config('app.name') }} · <a href="{{ url('/') }}">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>
    </footer>
</body>
</html>
