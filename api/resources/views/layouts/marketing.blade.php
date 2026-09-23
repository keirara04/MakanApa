<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'MakanApa')</title>
        <meta name="description" content="@yield('description', 'MakanApa: what to eat, decided in seconds.')">
        <meta name="theme-color" content="#fdf6ec">
        <link rel="canonical" href="{{ url()->current() }}">

        <link rel="icon" href="{{ asset('images/mascot-default.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

        {{-- WhatsApp / X / iMessage link previews --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="MakanApa">
        <meta property="og:title" content="@yield('title', 'MakanApa')">
        <meta property="og:description" content="@yield('description', 'MakanApa: what to eat, decided in seconds.')">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="MakanApa mascot with the line: Stop deciding. Start makan.">
        <meta name="twitter:card" content="summary_large_image">

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif

        <style>
            /* Minimal fallback so the page is legible even before a production build exists. */
            body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        </style>

        @stack('styles')
    </head>
    <body class="bg-cream text-ink antialiased">
        <a href="#main"
           class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-white">
            Skip to content
        </a>

        <header class="sticky top-0 z-40 border-b border-sambal-100 bg-cream/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2 text-lg">
                    <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="h-8 w-8" width="32" height="32" aria-hidden="true">
                    <x-marketing.wordmark />
                </a>
                <nav aria-label="Main" class="flex items-center gap-1 text-sm font-medium sm:gap-3">
                    <a href="{{ url('/support') }}" class="hidden min-h-11 items-center px-2 hover:text-sambal-600 sm:inline-flex">Support</a>
                    <a href="{{ url('/privacy') }}" class="hidden min-h-11 items-center px-2 hover:text-sambal-600 sm:inline-flex">Privacy</a>
                    <x-marketing.download-button from="nav" size="sm">Get the app</x-marketing.download-button>
                </nav>
            </div>
        </header>

        <main id="main" class="@yield('main_class', 'mx-auto max-w-3xl px-6 py-10')">
            @yield('content')
        </main>

        <footer class="mt-16 border-t border-sambal-100">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-ink/70 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 text-base text-ink">
                        <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="h-7 w-7" width="28" height="28" aria-hidden="true">
                        <x-marketing.wordmark />
                    </a>
                    <nav aria-label="Footer" class="flex flex-wrap gap-x-4 gap-y-2">
                        <a href="{{ url('/support') }}" class="hover:text-sambal-600">Support</a>
                        <a href="{{ url('/privacy') }}" class="hover:text-sambal-600">Privacy Policy</a>
                    </nav>
                </div>
                <p>Made in Malaysia <span aria-hidden="true">🇲🇾</span> · &copy; {{ date('Y') }} Hakeemi Ridza. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
