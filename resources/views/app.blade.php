<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{-- Как ссылка выглядит в поиске, мессенджерах и соцсетях.
             Страница может передать свои title и description через
             withViewData — у лендинга они развёрнутые, под поисковую выдачу.
             Сервер отдаёт их сразу в HTML: робот не всегда исполняет JS,
             и без этого в выдаче было одно слово «Слайдуша». --}}
        @php($seoTitle = $seoTitle ?? config('app.name'))
        @php($seoDescription = $seoDescription ?? 'Нейросеть собирает презентацию по теме или по готовому тексту: факты из источников, иллюстрации, сверстанные слайды и PDF.')
        <meta name="description" content="{{ $seoDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:image" content="{{ url('/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $seoTitle }}</title>
        </x-inertia::head>

        @isset($seoJsonLd)
            <script type="application/ld+json">{!! json_encode($seoJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endisset
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        @include('partials.metrika')
    </body>
</html>
