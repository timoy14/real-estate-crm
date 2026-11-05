<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

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
                background-color: oklch(0.129 0.042 264.695);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/pages/{$page['component']}.vue"])
        @php
            $meta = $page['props']['meta'] ?? null;
            $metaTitle = $meta['title'] ?? null;
            $metaDescription = $meta['description'] ?? null;
            $metaImage = $meta['image'] ?? null;
            $metaUrl = $meta['url'] ?? null;
        @endphp
        <x-inertia::head>
            <title>{{ $metaTitle ?: config('app.name', 'Skyline Realty') }}</title>
            @if ($metaDescription)
                <meta name="description" content="{{ $metaDescription }}">
                <meta property="og:description" content="{{ $metaDescription }}">
            @endif
            @if ($metaTitle)
                <meta property="og:title" content="{{ $metaTitle }}">
            @endif
            @if ($metaImage)
                <meta property="og:image" content="{{ $metaImage }}">
            @endif
            @if ($metaUrl)
                <meta property="og:url" content="{{ $metaUrl }}">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
