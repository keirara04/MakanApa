<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'MakanApa')</title>
        <meta name="description" content="@yield('description', 'MakanApa — what to eat, decided in seconds.')">

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif

        <style>
            /* Minimal fallback so the page is legible even before a production build exists. */
            body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        </style>
    </head>
    <body class="bg-cream text-ink antialiased">
        <header class="border-b border-sambal-100">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-5">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-lg">
                    <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="h-8 w-8" aria-hidden="true">
                    MakanApa
                </a>
                <nav class="flex items-center gap-4 text-sm font-medium">
                    <a href="{{ url('/support') }}" class="hover:text-sambal-600">Support</a>
                    <a href="{{ url('/privacy') }}" class="hover:text-sambal-600">Privacy</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            @yield('content')
        </main>

        <footer class="border-t border-sambal-100 mt-16">
            <div class="mx-auto max-w-3xl px-6 py-8 text-sm text-ink/70">
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    <a href="{{ url('/') }}" class="hover:text-sambal-600">Home</a>
                    <a href="{{ url('/support') }}" class="hover:text-sambal-600">Support</a>
                    <a href="{{ url('/privacy') }}" class="hover:text-sambal-600">Privacy Policy</a>
                </div>
                <p class="mt-4">&copy; {{ date('Y') }} Hakeemi Ridza. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
