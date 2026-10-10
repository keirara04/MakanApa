{{-- Pause/play for everything that moves on its own (looping clips, bobbing mascots). WCAG 2.2.2:
     motion that runs longer than five seconds needs a way to stop it. Pinned to the corner so it
     can be reached from anywhere on the page. Starts hidden: without JS nothing loops, and under
     Reduce Motion nothing moves, so there's nothing to pause. The choice lives in localStorage and
     is applied before first paint (head script below). --}}
<button type="button" id="motion-toggle" hidden aria-pressed="false" aria-label="Pause animations" title="Pause animations"
        class="fixed bottom-4 left-4 z-30 inline-flex h-11 w-11 items-center justify-center rounded-full border border-ink/20 bg-paper-50/90 text-ink shadow-[0_8px_20px_-10px_rgba(43,28,20,0.5)] backdrop-blur transition hover:border-ink/60 focus-visible:outline-2 focus-visible:outline-sambal-600">
    {{-- Lucide "pause" / "play" --}}
    <svg data-motion-icon="pause" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="14" y="4" width="4" height="16" rx="1"/><rect x="6" y="4" width="4" height="16" rx="1"/>
    </svg>
    <svg data-motion-icon="play" hidden class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polygon points="6 3 20 12 6 21 6 3"/>
    </svg>
</button>

@once
    @push('head_scripts')
        <script>
            try { if (localStorage.getItem('makanapa-motion') === 'off') document.documentElement.dataset.motion = 'off'; } catch (e) {}
        </script>
    @endpush

    @push('body_scripts')
        <script>
            (function () {
                var button = document.getElementById('motion-toggle');
                if (!button || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                var root = document.documentElement;

                function render() {
                    var off = root.dataset.motion === 'off';
                    button.setAttribute('aria-pressed', off ? 'true' : 'false');
                    button.querySelector('[data-motion-icon="pause"]').hidden = off;
                    button.querySelector('[data-motion-icon="play"]').hidden = !off;
                }

                button.addEventListener('click', function () {
                    var off = root.dataset.motion !== 'off';
                    if (off) { root.dataset.motion = 'off'; } else { delete root.dataset.motion; }
                    try { localStorage.setItem('makanapa-motion', off ? 'off' : 'on'); } catch (e) {}
                    // The layout's clip player listens for this to pause any clip that's playing.
                    document.dispatchEvent(new CustomEvent('makanapa:motion'));
                    render();
                });

                render();
                button.hidden = false;
            })();
        </script>
    @endpush
@endonce
