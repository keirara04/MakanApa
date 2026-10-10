<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'MakanApa')</title>
        <meta name="description" content="@yield('description', 'MakanApa: what to eat, decided in seconds.')">
        <meta name="theme-color" content="#f4efe6">
        @if (config('marketing.app_store_id'))
            {{-- Safari's own "Get"/"Open" bar; a share page passes its URL so "Open" lands on the place. --}}
            <meta name="apple-itunes-app" content="app-id={{ config('marketing.app_store_id') }}@hasSection('app_argument'), app-argument=@yield('app_argument')@endif">
        @endif
        {{-- Always the marketing domain (never the API/admin host that also serves these routes),
             without query strings like ?ref=. A page with its own variants sets 'canonical'. --}}
        <link rel="canonical" href="@yield('canonical', \App\Support\MarketingUrl::to(request()->path()))">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ asset('images/mascot-default.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

        {{-- WhatsApp / X / iMessage link previews --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="MakanApa">
        <meta property="og:title" content="@yield('title', 'MakanApa')">
        <meta property="og:description" content="@yield('description', 'MakanApa: what to eat, decided in seconds.')">
        <meta property="og:url" content="@yield('canonical', \App\Support\MarketingUrl::to(request()->path()))">
        <meta property="og:locale" content="@yield('og_locale', 'en_MY')">
        <meta property="og:image" content="@yield('og_image', asset('images/og.jpg'))">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="@yield('og_image_alt', 'MakanApa: Hungry? What to eat today? Decided in seconds. The mascot next to the app showing its pick.')">
        @stack('meta')
        <meta name="twitter:card" content="summary_large_image">

        {{-- Before first paint: lets the reveal/draw-in styles hide things only when the script
             that un-hides them will actually run. --}}
        <script>document.documentElement.classList.add('js');</script>

        @fonts
        {{-- The headings are the first thing on screen; fetch their font with the page instead of
             after the CSS, so they don't flash in the fallback face. --}}
        <link rel="preload" href="{{ asset('fonts/fredoka/fredoka-latin-700.woff2') }}" as="font" type="font/woff2" crossorigin>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif

        <style>
            /* Self-hosted, all SIL OFL, so the site makes no third-party font calls. Declared here, not
               in app.css, so the URLs resolve against this site under the Vite dev server too.
               Fredoka: headings, UI and the wordmark. Caveat: handwritten notes. */
            @font-face { font-family: 'Fredoka'; font-weight: 500; font-display: swap; src: url('{{ asset('fonts/fredoka/fredoka-latin-500.woff2') }}') format('woff2'); }
            @font-face { font-family: 'Fredoka'; font-weight: 600; font-display: swap; src: url('{{ asset('fonts/fredoka/fredoka-latin-600.woff2') }}') format('woff2'); }
            @font-face { font-family: 'Fredoka'; font-weight: 700; font-display: swap; src: url('{{ asset('fonts/fredoka/fredoka-latin-700.woff2') }}') format('woff2'); }
            @font-face { font-family: 'Caveat'; font-weight: 700; font-display: swap; src: url('{{ asset('fonts/caveat/caveat-latin-700.woff2') }}') format('woff2'); }

            /* Minimal fallback so the page is legible even before a production build exists. */
            body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        </style>

        @stack('styles')
        @stack('head_scripts')
    </head>
    <body class="paper-grain bg-paper text-ink antialiased">
        {{-- Wobble for the hand-drawn .sketch frames: two seeds so the double stroke never lines up. --}}
        <svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
            <filter id="sketch-a" x="-5%" y="-5%" width="110%" height="110%">
                <feTurbulence type="fractalNoise" baseFrequency="0.018" numOctaves="2" seed="4" result="noise"/>
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="5" xChannelSelector="R" yChannelSelector="G"/>
            </filter>
            <filter id="sketch-b" x="-5%" y="-5%" width="110%" height="110%">
                <feTurbulence type="fractalNoise" baseFrequency="0.024" numOctaves="2" seed="11" result="noise"/>
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="7" xChannelSelector="G" yChannelSelector="R"/>
            </filter>
        </svg>

        @stack('body_start')

        <a href="#main"
           class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-white">
            Skip to content
        </a>

        <header class="sticky top-0 z-40 border-b border-ink/10 bg-paper/95">
            <div class="mx-auto grid h-16 max-w-6xl grid-cols-[auto_1fr_auto] items-center gap-4 px-4 sm:px-6">
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2">
                    <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="h-9 w-9" width="36" height="36" aria-hidden="true">
                    <x-marketing.wordmark class="text-[1.4rem] leading-none sm:text-[1.6rem]" />
                </a>

                <nav aria-label="Main" class="hidden justify-center gap-7 font-display text-[1.05rem] font-semibold lg:flex xl:gap-9">
                    @hasSection('nav_links')
                        @yield('nav_links')
                    @else
                        <a href="{{ url('/') }}" class="bracket-link">Home</a>
                        <a href="{{ route('marketing.ambassadors') }}" class="bracket-link" @if (request()->routeIs('marketing.ambassadors')) data-active aria-current="page" @endif>Ambassadors</a>
                        <a href="{{ url('/support') }}" class="bracket-link" @if (request()->is('support')) data-active aria-current="page" @endif>Support</a>
                        {{-- Privacy, terms and guidelines stay footer-only: more header links don't fit at tablet width. --}}
                    @endif
                </nav>

                <div class="col-start-3 flex items-center gap-2 sm:gap-3">
                    @stack('nav_extra')
                    <x-marketing.download-button from="nav" size="sm">Get the app</x-marketing.download-button>
                </div>
            </div>
        </header>

        <main id="main" class="@yield('main_class', 'mx-auto max-w-3xl px-6 py-10')">
            @yield('content')
        </main>

        <footer class="mt-10 border-t border-ink/10">
            <div class="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-10 text-sm text-ink/70 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 text-ink">
                        <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="h-8 w-8" width="32" height="32" aria-hidden="true">
                        <x-marketing.wordmark class="text-[1.4rem] leading-none" />
                    </a>
                    <nav aria-label="Footer" class="flex flex-wrap gap-x-6 gap-y-2 font-display text-base font-semibold text-ink">
                        <a href="{{ route('marketing.ambassadors') }}" class="bracket-link min-h-0">Ambassadors</a>
                        <a href="{{ url('/support') }}" class="bracket-link min-h-0">Support</a>
                        <a href="{{ route('privacy') }}" class="bracket-link min-h-0">Privacy Policy</a>
                        <a href="{{ route('terms') }}" class="bracket-link min-h-0">Terms of Use</a>
                        <a href="{{ route('community-guidelines') }}" class="bracket-link min-h-0">Community Guidelines</a>
                    </nav>
                </div>
                <p>Made in Malaysia <span aria-hidden="true">🇲🇾</span> · &copy; {{ date('Y') }} Hakeemi Ridza. All rights reserved.</p>
            </div>
            <p class="mx-auto max-w-6xl px-4 pb-8 text-xs text-ink/70 sm:px-6">Apple and the Apple logo are trademarks of Apple Inc., registered in the U.S. and other countries and regions. App Store is a service mark of Apple Inc.</p>
        </footer>

        <script>
            // Scroll reveal + draw-in (.draw / [data-reveal] → .is-in), the nav marking
            // whichever section is on screen, and the marketing clip loops (video[data-clip]) that only
            // play while hovered. Everything is visible without this script (clips stay on their poster).
            (function () {
                var clips = document.querySelectorAll('video[data-clip]');
                var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
                var saveData = navigator.connection && navigator.connection.saveData;
                // Clips play only while hovered: several 30fps videos running at once halved the
                // frame rate around them. Touch screens keep the poster frame.
                var canHover = matchMedia('(hover: hover) and (pointer: fine)').matches;
                if (clips.length && canHover && !calm && !saveData) {
                    // The motion-toggle component sets data-motion="off" on <html> to stop all clips.
                    var root = document.documentElement;
                    clips.forEach(function (video) {
                        var target = video.closest('figure') || video;
                        target.addEventListener('pointerenter', function () {
                            if (root.dataset.motion === 'off') return;
                            var playing = video.play();
                            if (playing && playing.catch) playing.catch(function () {});
                        });
                        target.addEventListener('pointerleave', function () { video.pause(); });
                    });
                    document.addEventListener('makanapa:motion', function () {
                        if (root.dataset.motion === 'off') clips.forEach(function (video) { video.pause(); });
                    });
                }

                // The home page's first-visit intro covers the page; hold the reveals until it lifts.
                if (document.documentElement.classList.contains('intro-active')) {
                    document.addEventListener('makanapa:intro-done', startReveal, { once: true });
                } else {
                    startReveal();
                }

                function startReveal() {
                    var targets = document.querySelectorAll('[data-reveal], .draw');
                    if (!('IntersectionObserver' in window)) {
                        targets.forEach(function (el) { el.classList.add('is-in'); });
                        return;
                    }

                    var reveal = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (!entry.isIntersecting) return;
                            entry.target.classList.add('is-in');
                            reveal.unobserve(entry.target);
                        });
                    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.15 });
                    targets.forEach(function (el) { reveal.observe(el); });

                    var links = document.querySelectorAll('.bracket-link[href^="#"]');
                    if (!links.length) return;
                    var spy = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            var link = document.querySelector('.bracket-link[href="#' + entry.target.id + '"]');
                            if (!link) return;
                            if (entry.isIntersecting) { link.setAttribute('data-active', ''); } else { link.removeAttribute('data-active'); }
                        });
                    }, { rootMargin: '-45% 0px -50% 0px' });
                    links.forEach(function (link) {
                        var section = document.getElementById(link.getAttribute('href').slice(1));
                        if (section) spy.observe(section);
                    });
                }
            })();
        </script>
        @stack('body_scripts')
    </body>
</html>
