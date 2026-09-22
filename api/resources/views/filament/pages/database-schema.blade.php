<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/svg-pan-zoom@3.6.1/dist/svg-pan-zoom.min.js"></script>

    <div class="flex flex-wrap items-center gap-3 mb-3">
        <input
            type="text"
            id="database-schema-search"
            placeholder="Search table..."
            class="fi-input block w-full rounded-lg border-none py-1.5 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20 sm:max-w-xs"
        />

        <div class="flex-1"></div>

        <x-filament::button id="database-schema-export-svg" color="gray" size="sm">
            Export SVG
        </x-filament::button>
        <x-filament::button id="database-schema-export-png" color="gray" size="sm">
            Export PNG
        </x-filament::button>
        <x-filament::button id="database-schema-fullscreen" color="gray" size="sm">
            Fullscreen
        </x-filament::button>
        <x-filament::button id="database-schema-reset" color="gray" size="sm">
            Reset view
        </x-filament::button>
    </div>

    <details class="mb-3">
        <summary class="cursor-pointer text-sm text-gray-500 dark:text-gray-400">Hidden tables</summary>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
            @foreach ($this->getAllTableNames() as $table)
                <label class="flex items-center gap-1.5 text-sm">
                    <input type="checkbox" wire:model.live="hiddenTables" value="{{ $table }}" />
                    {{ $table }}
                </label>
            @endforeach
        </div>
    </details>

    <div
        id="database-schema-container"
        class="fi-section rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <div
            id="database-schema-diagram"
            data-diagram="{{ $this->getDiagram() }}"
            data-resource-urls="{{ json_encode($this->getTableResourceUrls()) }}"
            style="height: 80vh;"
        ></div>
    </div>

    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

        mermaid.initialize({
            startOnLoad: false,
            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
        });

        let panZoom = null;
        let renderToken = 0;

        async function renderDiagram() {
            const el = document.getElementById('database-schema-diagram');
            if (!el) return;

            const myToken = ++renderToken;
            const { svg } = await mermaid.render('database-schema-diagram-svg-' + myToken, el.dataset.diagram);

            // A newer render started while this one was pending (rapid checkbox toggles) —
            // drop this result so we never paint an out-of-date diagram over a fresh one.
            if (myToken !== renderToken) return;

            el.innerHTML = svg;

            const svgEl = el.querySelector('svg');
            svgEl.style.maxWidth = 'none';
            svgEl.style.height = '80vh';

            if (panZoom) panZoom.destroy();
            panZoom = window.svgPanZoom(svgEl, {
                zoomEnabled: true,
                panEnabled: true,
                controlIconsEnabled: true,
                fit: true,
                center: true,
                minZoom: 0.2,
                maxZoom: 20,
            });

            wireResourceLinks(el);
            wireSearch(el);
        }

        function wireResourceLinks(el) {
            const urls = JSON.parse(el.dataset.resourceUrls || '{}');

            el.querySelectorAll('[id^="entity-"]').forEach((node) => {
                const match = node.id.match(/^entity-(.+?)-\d+$/);
                if (!match) return;

                const url = urls[match[1].toLowerCase()];
                if (!url) return;

                node.style.cursor = 'pointer';
                node.addEventListener('click', () => window.location.href = url);
            });
        }

        function wireSearch(el) {
            const input = document.getElementById('database-schema-search');
            const nodes = Array.from(el.querySelectorAll('[id^="entity-"]'));

            input.oninput = () => {
                const term = input.value.trim().toLowerCase();

                nodes.forEach((node) => {
                    const match = term === '' || node.id.toLowerCase().includes(term);
                    node.style.opacity = match ? '1' : '0.15';
                });
            };
        }

        document.getElementById('database-schema-reset').addEventListener('click', () => {
            if (panZoom) {
                panZoom.resetZoom();
                panZoom.resetPan();
            }
        });

        document.getElementById('database-schema-fullscreen').addEventListener('click', () => {
            const container = document.getElementById('database-schema-container');
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                container.requestFullscreen();
            }
        });

        document.getElementById('database-schema-export-svg').addEventListener('click', () => {
            const svgEl = document.querySelector('#database-schema-diagram svg');
            if (!svgEl) return;

            const blob = new Blob([new XMLSerializer().serializeToString(svgEl)], { type: 'image/svg+xml' });
            downloadBlob(blob, 'database-schema.svg');
        });

        document.getElementById('database-schema-export-png').addEventListener('click', () => {
            const svgEl = document.querySelector('#database-schema-diagram svg');
            if (!svgEl) return;

            const svgString = new XMLSerializer().serializeToString(svgEl);
            const svgBlob = new Blob([svgString], { type: 'image/svg+xml' });
            const url = URL.createObjectURL(svgBlob);

            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.width * 2;
                canvas.height = img.height * 2;

                const ctx = canvas.getContext('2d');
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                canvas.toBlob((blob) => downloadBlob(blob, 'database-schema.png'));
                URL.revokeObjectURL(url);
            };
            img.src = url;
        });

        function downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        // Re-render whenever Livewire swaps in a new data-diagram (hidden-tables toggle) —
        // decoupling from Livewire's own lifecycle events this way survives Filament's
        // wire:navigate SPA transitions without guessing at event names/timing.
        const observer = new MutationObserver(() => renderDiagram());
        document.addEventListener('livewire:navigated', () => {
            const el = document.getElementById('database-schema-diagram');
            if (!el) return;
            observer.disconnect();
            observer.observe(el, { attributes: true, attributeFilter: ['data-diagram'] });
            renderDiagram();
        });

        const initialEl = document.getElementById('database-schema-diagram');
        observer.observe(initialEl, { attributes: true, attributeFilter: ['data-diagram'] });
        renderDiagram();
    </script>
</x-filament-panels::page>
