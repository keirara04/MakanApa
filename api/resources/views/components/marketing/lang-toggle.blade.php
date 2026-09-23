{{-- Manglish ↔ English switch. Starts hidden so visitors without JS never see a dead button;
     the script below un-hides it. The choice lives in localStorage and is applied before first
     paint by the head script the layout renders from @stack('head_scripts'). --}}
<button type="button" id="lang-toggle" hidden
        class="inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-full border border-sambal-200 bg-white px-2.5 text-sm sm:px-3 font-semibold text-ink transition hover:border-sambal-500">
    {{-- Lucide "languages" --}}
    <svg class="h-4 w-4 text-sambal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>
    </svg>
    {{-- Icon-only on phones to keep "Get the app" on one line; aria-label carries the name. --}}
    <span data-lang-label class="max-sm:hidden"></span>
</button>

@once
    @push('head_scripts')
        <script>
            try { if (localStorage.getItem('makanapa-lang') === 'en') document.documentElement.dataset.lang = 'en'; } catch (e) {}
        </script>
    @endpush

    @push('body_scripts')
        <script>
            (function () {
                var button = document.getElementById('lang-toggle');
                if (!button) return;
                var root = document.documentElement;
                var label = button.querySelector('[data-lang-label]');

                function render() {
                    var isEnglish = root.dataset.lang === 'en';
                    label.textContent = isEnglish ? 'Manglish' : 'English';
                    button.setAttribute('aria-label', isEnglish ? 'Show page in Manglish' : 'Show page in English');
                    button.setAttribute('aria-pressed', isEnglish ? 'true' : 'false');
                }

                function flip() {
                    var next = root.dataset.lang === 'en' ? 'ms' : 'en';
                    if (next === 'en') { root.dataset.lang = 'en'; } else { delete root.dataset.lang; }
                    try { localStorage.setItem('makanapa-lang', next); } catch (e) {}
                    render();
                }

                button.addEventListener('click', function () {
                    var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (document.startViewTransition && !calm) { document.startViewTransition(flip); } else { flip(); }
                });

                render();
                button.hidden = false;
            })();
        </script>
    @endpush
@endonce
