@extends('layouts.marketing')

@section('title', 'MakanApa: What to Eat Near You, Decided in Seconds')
@section('description', 'What should I eat today? Tell MakanApa your mood, budget and how far you\'ll go. It picks one place nearby. Free on the App Store for iPhone.')
@section('main_class', '')

@php
    // Spun by the first-visit intro.
    $dishes = ['Nasi lemak', 'Roti canai', 'Satay', 'Char kuey teow', 'Laksa', 'Nasi kandar', 'Mee goreng', 'Teh tarik'];

    // Live numbers strip: [stat key, label]; a null stat is below its floor.
    $statLabels = [
        ['places', 'Food spots on the map'],
        ['picks', 'Picks settled'],
        ['community', 'Added by the community'],
    ];
    $shownStats = array_values(array_filter($statLabels, fn (array $stat) => $stats[$stat[0]] !== null));

    // FAQ: [question, answer HTML], in two columns. Also emitted as FAQPage structured data below.
    $faqs = [
        [
            ['Where do I get it?', 'MakanApa is on the App Store for iPhone: <a href="'.route('marketing.download', ['from' => 'faq']).'" class="font-medium text-sambal-700 underline">download it here</a>, or search <strong>MakanApa</strong> in the App Store. Found a bug? <a href="'.url('/support').'" class="font-medium text-sambal-700 underline">Let us know</a>.'],
            ['Is it free?', 'Yes, MakanApa is free to download and use.'],
            ['Which areas does MakanApa work in?', 'MakanApa finds places around wherever you are, so it works anywhere there are restaurants nearby. It\'s built in Malaysia, with Malaysian food in mind.'],
            ['Do I need an account?', 'No. Picks, Nearby and saved places all work without one. Adding places, posting and halal reports need an account, so the community knows who\'s contributing. Sign up later and everything comes with you.'],
        ],
        [
            ['Is there an Android version?', 'Not yet. MakanApa is iPhone only for now.'],
            ['Does MakanApa keep my location?', 'Your location is used to find places near you and work out distance. We save it with each pick you ask for, linked to your account, and it\'s never shown publicly. <a href="'.route('privacy').'#location" class="font-medium text-sambal-700 underline">Read the privacy policy</a>.'],
            ['How does halal info work?', 'Each place shows what we actually know: <strong>Halal certified</strong>, <strong>not certified</strong> with community notes, or <strong>not verified</strong> yet. We don\'t label a place halal without a certificate, and you can help verify places from the app.'],
            ['How do I become an ambassador?', 'Join your university or area community in the app, then apply from the Community tab. We read every application. <a href="'.route('marketing.ambassadors').'" class="font-medium text-sambal-700 underline">See what ambassadors do</a>.'],
        ],
    ];
@endphp

@section('nav_links')
    <a href="#how-it-works" class="bracket-link">How it works</a>
    <a href="#features" class="bracket-link">Features</a>
    <a href="#ambassadors" class="bracket-link">Ambassadors</a>
    <a href="#faq" class="bracket-link">FAQ</a>
@endsection

{{-- FIRST-VISIT INTRO -------------------------------------------------------------------------- --}}
{{-- A slot-machine "what to eat?" while the page's video clips download, landing on "Let's eat!"
     once they're ready (or after 6s, whichever is first) and lifting away like a torn page. Only
     first visits see it (?intro replays it); Reduce Motion and Data Saver skip it. --}}
@push('head_scripts')
    <script>
        (function () {
            try {
                var replay = /[?&]intro(=|&|$)/.test(location.search);
                var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
                var saver = navigator.connection && navigator.connection.saveData;
                var still = localStorage.getItem('makanapa-motion') === 'off';
                if ((replay || !localStorage.getItem('makanapa-intro-seen')) && !calm && !saver && !still) {
                    document.documentElement.classList.add('intro-active');
                }
            } catch (e) {}
        })();
    </script>
@endpush

@push('body_start')
    <div id="intro" class="intro paper-grain bg-paper px-6 text-center text-ink" role="status" aria-live="polite">
        <span class="sr-only">Loading MakanApa…</span>

        <div class="intro-mascot relative h-36 w-36 sm:h-44 sm:w-44" aria-hidden="true">
            <img src="{{ asset('images/mascot/thinking-320.webp') }}" alt="" width="176" height="176" class="is-thinking absolute inset-0 h-full w-full">
            <img src="{{ asset('images/mascot/excited-320.webp') }}" alt="" width="176" height="176" class="is-picked absolute inset-0 h-full w-full">
        </div>

        <p class="mt-6 font-display text-2xl font-semibold text-ink/70 sm:text-3xl" aria-hidden="true">
            Hmm… what to eat?
        </p>

        <div class="relative mt-3 font-display text-[clamp(2.4rem,8vw,4.5rem)] font-bold tracking-tight" aria-hidden="true">
            <div class="slot-reel">
                {{-- Listed twice so the loop can scroll half its height and wrap without a jump. --}}
                <ul>
                    @foreach ([...$dishes, ...$dishes] as $dish)
                        <li class="whitespace-nowrap leading-[1.3]">{{ $dish }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="intro-final whitespace-nowrap text-sambal-600">
                <span class="relative inline-block leading-[1.3]">
                    Let's eat!
                    <x-marketing.doodle type="circle" id="intro-circle" class="draw absolute -left-6 -top-1 h-[calc(100%+0.5rem)] w-[calc(100%+3rem)] text-sambal-600" style="--draw-delay: 300ms; --draw-dur: 700ms" />
                </span>
            </p>
        </div>

        <svg class="intro-progress mt-6 h-3 w-56 text-ink/80 sm:w-72" viewBox="0 0 300 20" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
            <path pathLength="1" d="M4 13c52-7 104-9 150-6s98 7 142 1"/>
        </svg>
        <p class="mt-3 text-sm text-ink/70" aria-hidden="true">Warming up the wok…</p>

        <button type="button" data-intro-skip class="bracket-link absolute bottom-5 right-6 transition-opacity font-display text-lg font-semibold text-ink/70">Skip</button>

        <svg class="intro-tear" viewBox="0 0 1200 34" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0 0H1200V12L1200 12L1191 20L1170 12L1147 14L1132 23L1115 27L1096 19L1068 8L1043 29L1017 17L989 28L966 9L950 30L921 16L905 10L877 26L848 27L823 18L799 25L783 9L756 23L738 18L719 21L689 11L673 17L645 18L616 24L593 10L572 30L553 15L530 19L502 26L474 18L447 23L427 27L412 26L396 25L379 19L359 11L340 25L317 26L300 12L273 17L255 9L234 9L208 26L193 15L176 9L149 10L128 10L101 21L85 9L65 24L50 19L33 10L18 20L0 18Z"/>
        </svg>
    </div>
@endpush

@push('body_scripts')
    <script>
        (function () {
            var root = document.documentElement;
            var intro = document.getElementById('intro');
            if (!intro) return;
            if (!root.classList.contains('intro-active')) { intro.remove(); return; }

            var MIN_MS = 1600; // long enough for the reel to read as a spin, not a flicker
            var MAX_MS = 3000; // never hold a slow connection hostage
            var started = performance.now();
            // The hero has no clips; the rest load lazily as they scroll into view (layout script),
            // so the intro only waits out its own spin.
            var clips = [];
            var line = intro.querySelector('.intro-progress path');
            var page = [document.querySelector('body > header'), document.getElementById('main'), document.querySelector('body > footer')];
            var shown = 0;
            var finished = false;

            page.forEach(function (el) { if (el) el.inert = true; });

            clips.forEach(function (video) { video.preload = 'auto'; video.load(); });

            function loaded(video) {
                if (video.error || video.readyState >= 3) return 1; // enough to start playing
                var duration = video.duration;
                if (!duration || !isFinite(duration) || !video.buffered.length) return 0;
                return Math.min(1, video.buffered.end(video.buffered.length - 1) / duration);
            }

            function tick() {
                var elapsed = performance.now() - started;
                var real = clips.length
                    ? clips.reduce(function (sum, video) { return sum + loaded(video); }, 0) / clips.length
                    : 1;
                // Creeps on its own so a slow first byte doesn't look frozen, but only real
                // progress can take it past 90%.
                shown = Math.max(shown, real, Math.min(0.9, elapsed / MAX_MS));
                line.style.strokeDashoffset = String(1 - shown);

                if ((real >= 1 && elapsed >= MIN_MS) || elapsed >= MAX_MS) land();
            }

            function land() {
                if (finished) return;
                finished = true;
                clearInterval(timer);
                line.style.strokeDashoffset = '0';
                intro.classList.add('is-landing');
                document.getElementById('intro-circle').classList.add('is-in');
                setTimeout(leave, 1150);
            }

            function leave() {
                finished = true;
                clearInterval(timer);
                // Keep it displayed while it slides, even though <html> drops intro-active now so the
                // hero's own entrance plays as the page is uncovered.
                intro.style.display = 'flex';
                intro.classList.add('is-leaving');
                root.classList.remove('intro-active');
                page.forEach(function (el) { if (el) el.inert = false; });
                try { localStorage.setItem('makanapa-intro-seen', '1'); } catch (e) {}
                document.dispatchEvent(new Event('makanapa:intro-done'));
                setTimeout(function () { intro.remove(); }, 1100);
            }

            intro.querySelector('[data-intro-skip]').addEventListener('click', leave);
            var timer = setInterval(tick, 100);
            tick();
        })();
    </script>
@endpush

@section('content')

    {{-- HERO: the line on the left; on the right, a spoon on a plate of dishes that picks one. -- --}}
    <section class="relative overflow-x-clip">
        <div class="mx-auto grid max-w-6xl items-center gap-16 px-5 pb-12 pt-10 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:gap-10 lg:pb-24 lg:pt-14">
            <div class="text-center lg:text-left">
                <h1 class="hero-in type-hero hero-title" style="--i: 0">
                    <span class="whitespace-nowrap">Can't decide?</span><br>
                    <span class="relative inline-block">
                        We'll pick.
                        <x-marketing.doodle type="underline" class="draw absolute -bottom-3 left-0 h-4 w-full text-sambal-600" style="--draw-delay: 900ms; --draw-dur: 1100ms" />
                    </span>
                </h1>

                <p class="hero-in mx-auto mt-8 max-w-md text-lg leading-relaxed text-ink/75 sm:text-xl lg:mx-0" style="--i: 1">
                    Tell MakanApa your mood, budget and how far you'll go. It picks one place nearby and tells you why. Not feeling it? Find another.
                </p>

                <div class="hero-in mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center sm:gap-6 lg:justify-start" style="--i: 2">
                    <x-marketing.app-store-badge from="hero" class="lg:-ml-3.5" />
                    <p class="font-display text-lg font-medium text-ink/70">
                        Free on iPhone
                        {{-- Only once enough people have rated it; the same value backs the structured data. --}}
                        @if ($appRating)
                            <span class="whitespace-nowrap">and rated ★ {{ number_format($appRating['rating'], 1) }}<span class="sr-only"> out of 5,</span> ({{ number_format($appRating['count']) }} ratings)</span>
                        @endif
                    </p>
                </div>

                {{-- Scan-to-install for desktop visitors. --}}
                <div class="hero-in mt-10 hidden items-center gap-4 lg:flex" style="--i: 3">
                    <img src="{{ asset('images/qr-app-store.svg') }}" alt="QR code to download MakanApa from the App Store"
                         width="72" height="72" class="h-18 w-18 rounded-lg bg-paper-50 p-1.5 shadow-clay">
                    <p class="font-hand text-2xl leading-6 text-ink/70">On a computer?<br>Scan with your iPhone.</p>
                </div>
            </div>

            {{-- "Spin the spoon": a plate of dishes and a spoon that lands on one. Spins once on its
                 own after the page loads (not under Reduce Motion or the pause button); after that
                 it only spins when asked. --}}
            @php
                // Long names sit top and bottom, where there's room.
                $spinDishes = ['Nasi kandar', 'Roti canai', 'Satay', 'Nasi lemak', 'Char kuey teow', 'Ayam gepuk', 'Laksa', 'Mee goreng'];
            @endphp
            <div class="spin hero-in relative mx-auto w-full max-w-[22rem] sm:max-w-[28rem] lg:max-w-[32rem]" data-spin data-state="idle" style="--i: 3">
                <div class="relative aspect-square">
                    {{-- The plate: a soft shadow (plain box-shadow, cheaper than a filter over the
                         filtered rim), a sketched rim and a dashed inner ring. --}}
                    <div class="absolute inset-[2%] rounded-full shadow-[0_24px_30px_rgba(43,28,20,0.18)]" aria-hidden="true"></div>
                    <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="48" fill="var(--color-paper-50)" stroke="var(--color-ink)" stroke-width="0.6" filter="url(#sketch-a)"/>
                        <circle cx="50" cy="50" r="46.6" fill="none" stroke="var(--color-ink)" stroke-width="0.3" opacity="0.45" filter="url(#sketch-b)"/>
                        <circle cx="50" cy="50" r="27" fill="none" stroke="var(--color-ink)" stroke-width="0.35" stroke-dasharray="1.4 1.6" opacity="0.35"/>
                    </svg>

                    <ul aria-hidden="true">
                        @foreach ($spinDishes as $index => $dish)
                            @php
                                $angle = deg2rad($index * 45);
                                $left = round(50 + 37 * sin($angle), 2);
                                $top = round(50 - 37 * cos($angle), 2);
                            @endphp
                            <li data-dish data-name="{{ $dish }}"
                                class="spin-dish absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-display text-sm font-semibold text-ink/75 sm:text-lg"
                                style="left: {{ $left }}%; top: {{ $top }}%">
                                <span class="relative">
                                    {{ $dish }}
                                    <x-marketing.doodle type="circle" class="absolute -inset-x-3 -inset-y-2 h-[calc(100%+1rem)] w-[calc(100%+1.5rem)] text-sambal-600" />
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Bubu, calling it. --}}
                    <div class="pointer-events-none absolute -bottom-6 -left-3 w-20 sm:-bottom-4 sm:-left-12 sm:w-32" aria-hidden="true">
                        <p class="spin-bubble absolute -top-9 left-10 hidden -rotate-6 whitespace-nowrap sm:block rounded-full bg-ink px-3 py-1 font-hand text-xl text-paper">
                            <span data-bubble="idle">Let me pick.</span>
                            <span data-bubble="spinning">Hmm…</span>
                            <span data-bubble="landed">That one!</span>
                        </p>
                        <img src="{{ asset('images/mascot/excited-320.webp') }}" alt="" width="128" height="128" class="w-full drop-shadow-[0_12px_14px_rgba(43,28,20,0.2)]">
                    </div>

                    {{-- The spoon, bowl up; it turns about the plate's centre. --}}
                    <div class="spin-spoon absolute inset-[24%]" aria-hidden="true">
                        <svg class="h-full w-full" viewBox="0 0 100 100">
                            <ellipse cx="50" cy="24" rx="11" ry="15" fill="var(--color-kunyit)" stroke="var(--color-ink)" stroke-width="1.6"/>
                            <ellipse cx="47" cy="20" rx="4" ry="6" fill="#fff" opacity="0.55"/>
                            <path d="M50 39 C 48 52, 47.5 70, 47 86 a 3 3 0 0 0 6 0 C 52.5 70, 52 52, 50 39 Z" fill="var(--color-paper)" stroke="var(--color-ink)" stroke-width="1.6" stroke-linejoin="round"/>
                            <circle cx="50" cy="50" r="2.4" fill="var(--color-sambal-600)"/>
                        </svg>
                    </div>
                </div>

                <div class="mt-8 text-center" aria-live="polite">
                    <p data-spin-result class="font-display text-2xl font-semibold">Spin the spoon. It picks for you.</p>
                    <div class="js-only mt-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-3">
                        <a href="{{ route('marketing.download', ['from' => 'spin']) }}" data-spin-find class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-paper shadow-[3px_3px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[5px_5px_0_var(--color-sambal-600)]"></a>
                        <button type="button" data-spin-again class="bracket-link font-display text-lg font-semibold">Spin again</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- LIVE NUMBERS: straight from the database, recounted hourly (see LandingInsights). -------- --}}
    @if ($shownStats)
        <section class="mx-auto max-w-5xl px-5 pt-8 sm:px-6 sm:pt-12" aria-label="MakanApa in numbers">
            <dl @class(['grid gap-12 text-center', 'sm:grid-cols-2' => count($shownStats) === 2, 'sm:grid-cols-3' => count($shownStats) === 3])>
                @foreach ($shownStats as $index => [$key, $label])
                    <div data-stat="{{ $key }}" class="flex flex-col-reverse items-center">
                        <dt class="mt-3 text-base font-medium text-ink/70">{{ $label }}</dt>
                        <dd class="relative font-display text-[clamp(3.5rem,8vw,5.75rem)] font-bold leading-none tracking-tight tabular-nums">
                            <span data-count="{{ $stats[$key] }}">{{ number_format($stats[$key]) }}</span>
                            <x-marketing.doodle type="underline" class="draw absolute -bottom-2 left-[10%] h-3 w-[80%] text-sambal-600" style="--draw-delay: {{ 400 + $index * 150 }}ms" />
                        </dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-10 text-center text-sm text-ink/70">Live from MakanApa, updated every hour.</p>
        </section>
    @endif

    {{-- TIME CALCULATOR: the one dark page in the sketchbook. Controls on the left, and a printed
         bill for the time on the right. The default bill is rendered here so it reads fine
         without JS; the script below redoes the same sums as the controls change. ------------ --}}
    @php
        // Defaults: 15 minutes a decision, lunch and dinner. MakanApa: ~10 seconds a decision.
        $calcMinutes = 15;
        $calcMealOptions = [['breakfast', 'Breakfast', false], ['lunch', 'Lunch', true], ['dinner', 'Dinner', true], ['supper', 'Supper', false]];
        $calcMeals = count(array_filter(array_column($calcMealOptions, 2)));
        $calcDecisions = $calcMeals * 365;
        $calcHours = $calcMinutes * $calcDecisions / 60;
    @endphp
    <section id="time" class="calc mt-24 overflow-x-clip bg-ink text-paper sm:mt-28">
        <div class="mx-auto grid max-w-6xl items-center gap-16 px-6 py-20 lg:grid-cols-[1fr_26rem] lg:gap-20 lg:py-28">
            <form class="calc-form">
                <h2 class="type-section">How long do you spend deciding where to eat?</h2>
                <p class="mt-5 max-w-md text-lg text-paper/75">Be honest. Group chats count. Here's the bill.</p>

                <div class="mt-12 space-y-11">
                    <div>
                        <label for="calc-minutes" class="flex items-baseline justify-between gap-4 font-display text-xl font-semibold">
                            Minutes per meal, deciding
                            <output for="calc-minutes" data-out="minutes-label" class="whitespace-nowrap font-display text-3xl font-bold tabular-nums text-sambal-300">{{ $calcMinutes }} min</output>
                        </label>
                        <input id="calc-minutes" name="minutes" type="range" min="1" max="60" step="1" value="{{ $calcMinutes }}" class="calc-range mt-5 w-full">
                        <div class="mt-2 flex justify-between font-hand text-xl text-paper/60" aria-hidden="true"><span>Decisive</span><span>Group chat</span></div>
                    </div>

                    <fieldset>
                        <legend class="font-display text-xl font-semibold">Which meals do you agonise over?</legend>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($calcMealOptions as [$value, $label, $checked])
                                <label class="calc-pill">
                                    <input type="checkbox" name="meals" value="{{ $value }}" class="sr-only" @checked($checked)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </form>

            {{-- The bill. --}}
            <div class="relative mx-auto w-full max-w-[26rem]" aria-live="polite">
                <div class="receipt rotate-[1.5deg] bg-paper-50 px-7 pb-12 pt-8 text-ink">
                    <div class="text-center">
                        <x-marketing.wordmark class="text-2xl" />
                        <p class="receipt-mono mt-1 text-xs text-ink/60">Time spent deciding · {{ now()->format('Y') }}</p>
                    </div>

                    <dl class="receipt-mono mt-6 space-y-1.5 border-y border-dashed border-ink/40 py-4 text-sm">
                        <div class="receipt-line"><dt>Deciding, per meal</dt><dd><span data-out="minutes">{{ $calcMinutes }}</span> min</dd></div>
                        <div class="receipt-line"><dt>Meals a day</dt><dd>× <span data-out="meals">{{ $calcMeals }}</span></dd></div>
                        <div class="receipt-line"><dt>Days a year</dt><dd>× 365</dd></div>
                    </dl>

                    <div class="mt-5">
                        <p class="receipt-mono text-sm font-bold">Total time lost</p>
                        <p class="receipt-total mt-1 font-display text-[4.5rem] font-bold leading-none tracking-tight tabular-nums">
                            <span data-out="hours">{{ number_format($calcHours) }}</span><span class="ml-2 text-3xl font-semibold">hours</span>
                        </p>
                        <dl class="receipt-mono mt-3 space-y-1.5 text-sm text-ink/75">
                            <div class="receipt-line"><dt>In full days</dt><dd><span data-out="days">{{ number_format($calcHours / 24, 1) }}</span></dd></div>
                            <div class="receipt-line"><dt>In plates of nasi lemak*</dt><dd><span data-out="plates">{{ number_format($calcMinutes * $calcDecisions / 15) }}</span></dd></div>
                        </dl>
                    </div>

                    <div class="receipt-mono mt-5 border-t border-dashed border-ink/40 pt-4 text-sm">
                        <div class="receipt-line"><dt>With MakanApa, 10 sec a meal</dt><dd><span data-out="with">{{ number_format(10 * $calcDecisions / 3600, 1) }}</span> h</dd></div>
                    </div>

                    {{-- Rubber stamp over the bill. --}}
                    <p class="receipt-stamp absolute bottom-16 right-3 -rotate-12 rounded-lg border-[3px] border-sambal-600 px-3 py-1 text-center font-display font-bold leading-tight text-sambal-600">
                        <span class="block text-2xl tabular-nums"><span data-out="saved">{{ number_format($calcHours - 10 * $calcDecisions / 3600) }}</span> hours</span>
                        <span class="block text-sm">back every year</span>
                    </p>

                    <div class="receipt-barcode mx-auto mt-8 h-10 w-48" aria-hidden="true"></div>
                    <p class="receipt-mono mt-3 text-center text-xs text-ink/60">Thank you. Now go eat.</p>
                    <p class="receipt-mono mt-1 text-center text-[0.7rem] text-ink/50">*At 15 minutes a plate.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- EVERYTHING LOOKS GOOD: real Malaysian food scenes, taped in like polaroids. ------------------ --}}
    <section class="mx-auto max-w-6xl px-5 pt-24 sm:px-6 sm:pt-28">
        <div class="text-center">
            <h2 class="type-section">Everything looks good.</h2>
            <p class="mx-auto mt-5 max-w-lg text-lg text-ink/75">
                Night markets, mamak, satay by the roadside… choosing is the hard part. So let MakanApa choose.
            </p>
        </div>

        @php
            $scenes = [
                // [clip, title, note, tilt]
                ['pasar-malam', 'Night market?', 'Fruit rojak, guava juice, takoyaki… it’s all there.', '-rotate-2'],
                ['mamak', 'Mamak?', 'Roti canai and teh tarik, open 24 hours.', 'rotate-1 md:-translate-y-6'],
                ['satay', 'Satay?', 'Even the smoke smells good.', 'rotate-2'],
            ];
        @endphp

        <div class="mt-16 grid gap-12 md:grid-cols-3 md:gap-8">
            @foreach ($scenes as $index => [$clip, $title, $note, $tilt])
                <figure class="sketch {{ $tilt }} bg-paper-50 p-3 pb-6 transition-[rotate,translate] duration-300 hover:rotate-0" style="--sketch-radius: 6px">
                    <span class="tape -top-3 left-1/2 w-24 -translate-x-1/2 {{ $index % 2 ? 'rotate-2' : '-rotate-3' }}" aria-hidden="true"></span>
                    <div class="aspect-[4/3] overflow-hidden rounded-[3px] bg-ink/10">
                        <x-marketing.clip :name="$clip" />
                    </div>
                    <figcaption class="mt-5 px-2">
                        <p class="type-card">{{ $title }}</p>
                        <p class="mt-2 text-sm text-ink/70">{{ $note }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <div class="mt-14 flex flex-col items-center text-center">
            <p class="font-display text-2xl font-semibold leading-tight sm:text-3xl">
                Too many choices?
                <span class="text-sambal-600">We pick one.</span>
            </p>
            <x-marketing.doodle type="arrow-down" class="draw mt-3 h-20 w-10 text-ink/80" style="--draw-delay: 400ms" />
        </div>
    </section>

    {{-- HOW IT WORKS: the three questions as cards taped to the page, each with its real app
         screen tucked into the bottom. The answer gets its own section right after. ------------ --}}
    @php
        $walk = [
            // [screen, illustration, title, body, options, picked option, margin note, tilt, alt]
            ['mood', 'mood-spicy', "What's the mood?", 'Type a craving, pick one, or leave it to us.', ['Nasi Kandar', 'Ayam Gepuk', 'Nasi Padang', 'Anything'], 'Nasi Padang', 'Not sure? Anything works.', '-rotate-1', 'Mood step: type a craving, choose Anything, or pick Nasi Kandar, Ayam Gepuk or Nasi Padang, with Nasi Padang selected'],
            ['budget', 'budget-normal', "What's the budget?", 'Save a bit, keep it normal, or treat yourself.', ['~RM10', '~RM20', '~RM35+', 'Anything'], '~RM20', 'Per person, roughly.', 'rotate-1 md:translate-y-6', 'Budget step with ~RM10, ~RM20, ~RM35+ and Anything, with ~RM20 selected'],
            ['distance', 'distance-walk', 'How far can you go?', 'Close by, a short trip, or somewhere worth the trip.', ['1 km', '2 km', '5 km'], '2 km', 'From wherever you are.', '-rotate-[0.6deg]', 'Distance step with within 1 km, 2 km and 5 km, with 2 km selected'],
        ];
    @endphp
    <section id="how-it-works" class="scroll-mt-24 mx-auto max-w-6xl px-5 pt-12 sm:px-6">
        <div class="text-center">
            <p class="font-hand text-3xl text-sambal-700">How it works</p>
            <h2 class="type-section mt-1">Three questions.<br>One answer.</h2>
            <p class="mx-auto mt-5 max-w-lg text-lg text-ink/75">No list to scroll through. Answer three quick questions and MakanApa settles it.</p>
        </div>

        <ol class="mx-auto mt-16 grid max-w-sm gap-14 md:max-w-none md:grid-cols-3 md:gap-10 lg:gap-14">
            @foreach ($walk as $i => [$screen, $illustration, $title, $body, $options, $picked, $note, $tilt, $alt])
                <li class="relative {{ $tilt }} transition-[rotate,translate] duration-300 hover:rotate-0">
                    <div class="sketch flex h-full flex-col bg-paper-50 px-6 pt-6" style="--sketch-radius: 16px">
                        <span class="tape -top-3 left-1/2 w-24 -translate-x-1/2 {{ $i % 2 ? 'rotate-3' : '-rotate-2' }}" aria-hidden="true"></span>

                        <div class="flex items-start justify-between gap-4">
                            <span class="relative flex h-12 w-12 shrink-0 items-center justify-center font-display text-2xl font-bold">
                                {{ $i + 1 }}
                                <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-sambal-600" style="--draw-delay: {{ 150 + $i * 150 }}ms" />
                            </span>
                            <img src="{{ asset("images/illustrations/{$illustration}.svg") }}" alt="" aria-hidden="true" width="80" height="80" loading="lazy" class="-mr-3 -mt-4 h-20 w-20">
                        </div>

                        <h3 class="type-card mt-2">{{ $title }}</h3>
                        <p class="mt-2 text-ink/75">{{ $body }}</p>

                        <ul class="mt-5 flex flex-wrap gap-2 font-display font-semibold" aria-hidden="true">
                            @foreach ($options as $option)
                                <li @class([
                                    'rounded-full border-2 px-3 py-1 leading-none',
                                    '-rotate-3 border-sambal-600 bg-white text-sambal-700 shadow-clay' => $option === $picked,
                                    'border-ink/15 text-ink/60' => $option !== $picked,
                                ])>{{ $option }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-4 font-hand text-2xl text-ink/70">{{ $note }}</p>

                        {{-- The real screen, tucked into the bottom of the card. --}}
                        <div class="mx-auto mt-auto h-52 w-40 overflow-hidden pt-6">
                            <x-marketing.phone :screen="$screen" :alt="$alt" class="rounded-[1.8rem] p-1.5" />
                        </div>
                    </div>

                    @unless ($loop->last)
                        <x-marketing.doodle type="arrow-curve" class="draw absolute -right-12 top-24 z-10 hidden h-10 w-14 text-ink/60 md:block lg:-right-14 lg:w-16" style="--draw-delay: {{ 400 + $i * 200 }}ms" />
                    @endunless
                </li>
            @endforeach
        </ol>

        {{-- Bubu hands over to the answer. --}}
        <div class="mt-20 flex flex-col items-center md:mt-28" aria-hidden="true">
            <div class="relative">
                <p class="absolute -top-10 left-1/2 -translate-x-1/2 -rotate-3 whitespace-nowrap rounded-full bg-ink px-4 py-1 font-hand text-2xl text-paper">Then I pick one.</p>
                <img src="{{ asset('images/mascot/thinking-320.webp') }}" alt="" width="112" height="112" loading="lazy" class="h-28 w-28 drop-shadow-[0_12px_14px_rgba(43,28,20,0.2)]">
            </div>
            <x-marketing.doodle type="arrow-down" class="draw mt-2 h-16 w-8 text-ink/70" style="--draw-delay: 300ms" />
        </div>
    </section>

    {{-- THE ANSWER: the real result screen, with notes in the margin pointing at what it shows.
         On lg the notes sit beside the phone at the height of what they point at; below that they
         stack under it. Only points at things every pick shows. ------------------------------- --}}
    @php
        $answerNotes = [
            // [side, title, detail, top on lg]
            ['left', 'See it first', 'Photos and the rating, before you walk in.', '10rem'],
            ['left', 'What it costs, how far', 'Price per person and the walk, right under the name.', '16.75rem'],
            ['right', 'Halal, honestly', 'Certified, not certified, or not verified yet. Never a guess.', '19rem'],
            ['right', 'Why this one?', 'The reasons it picked this place for you.', '25.25rem'],
            ['right', "Go, or find another", "Let's eat opens directions. Not feeling it? One tap for another.", '32.75rem'],
        ];
    @endphp
    <section id="the-answer" class="scroll-mt-24 mx-auto max-w-6xl px-5 pb-24 pt-10 sm:px-6">
        <div class="text-center">
            <h2 class="type-section">One place.<br>And why.</h2>
            <p class="mx-auto mt-5 max-w-lg text-lg text-ink/75">Not a list of twenty. MakanApa picks one place and shows its working, so you can just go.</p>
        </div>

        <div class="mt-14 grid gap-12 lg:grid-cols-[1fr_19rem_1fr] lg:gap-8">
            <figure class="mx-auto w-64 sm:w-72 lg:order-2 lg:w-full">
                <x-marketing.phone screen="result" alt="Result screen: MakanApa picked Takoyaki DNN Bonda, about RM10 per person and a 2 minute walk, with photos, a 4.9 rating, its halal status and why it was picked" class="rotate-2" />
                <figcaption class="mt-6 text-center font-hand text-2xl text-ink/70">The real app, not a mock-up.</figcaption>
            </figure>

            @foreach (['left', 'right'] as $side)
                <ul @class([
                    'grid gap-8 sm:grid-cols-2 lg:relative lg:block lg:h-[42rem]',
                    'lg:order-1 lg:text-right' => $side === 'left',
                    'lg:order-3' => $side === 'right',
                ])>
                    @foreach (array_filter($answerNotes, fn (array $note) => $note[0] === $side) as [, $title, $detail, $top])
                        <li class="relative border-t-2 border-ink/80 pt-3 lg:absolute lg:inset-x-0 lg:top-(--y) lg:border-0 lg:pt-0" style="--y: {{ $top }}">
                            <p class="font-display text-xl font-semibold">{{ $title }}</p>
                            <p class="mt-1 text-ink/75 lg:max-w-[16rem] {{ $side === 'left' ? 'lg:ml-auto' : '' }}">{{ $detail }}</p>
                            <x-marketing.doodle type="arrow-curve" @class([
                                'draw absolute top-3 z-10 hidden h-12 w-20 text-sambal-600 lg:block',
                                '-right-[5.5rem]' => $side === 'left',
                                '-left-[5.5rem] -scale-x-100' => $side === 'right',
                            ]) />
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </section>

    {{-- MOST PICKED NEAR THE DEMO CAMPUS (or top rated, labelled as such). -------------------------- --}}
    @if ($nearby['kind'])
        <section class="mx-auto max-w-3xl px-5 pb-24 sm:px-6 sm:pb-28" data-nearby="{{ $nearby['kind'] }}">
            <div class="sketch relative -rotate-[0.6deg] bg-paper-50 p-6 sm:p-9" style="--sketch-radius: 10px">
                <span class="tape -top-3 left-1/2 -translate-x-1/2 -rotate-2" aria-hidden="true"></span>
                @if ($nearby['kind'] === 'picked')
                    <h2 class="type-card">Most picked near {{ $demoArea }}</h2>
                    <p class="mt-1 text-sm text-ink/70">By MakanApa users over the last 30 days.</p>
                @else
                    <h2 class="type-card">Top rated near {{ $demoArea }}</h2>
                    <p class="mt-1 text-sm text-ink/70">By Google rating, among places MakanApa knows.</p>
                @endif

                <ol class="mt-6 divide-y divide-dashed divide-ink/20 border-t border-dashed border-ink/20">
                    @foreach ($nearby['items'] as $rank => $place)
                        <li>
                            <a href="{{ $place['url'] }}" class="group flex items-center gap-4 py-3.5">
                                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center font-display text-xl font-bold">
                                    {{ $rank + 1 }}
                                    <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-ink/50" style="--draw-delay: {{ 200 + $rank * 120 }}ms" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-lg font-semibold transition-colors group-hover:text-sambal-600">{{ $place['name'] }}</span>
                                    <span class="block text-sm text-ink/70">{{ $place['headline'] }} · {{ number_format($place['distanceKm'], 1) }} km</span>
                                </span>
                                <span class="shrink-0 font-display text-lg font-semibold text-sambal-600">
                                    @if ($place['pickers'] !== null)
                                        {{ $place['pickers'] }} people
                                    @else
                                        ★ {{ number_format($place['rating'], 1) }}
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- COVERAGE: the campuses with enough places mapped to pick well, from the database. Hidden
         until at least one clears the floor (see LandingInsights::coverage). ------------------- --}}
    @if ($coverage)
        <section class="mx-auto max-w-6xl px-5 pb-24 sm:px-6 sm:pb-28" aria-labelledby="coverage-title">
            <div class="flex flex-col items-center text-center">
                <img src="{{ asset('images/illustrations/location-map.svg') }}" alt="" aria-hidden="true" width="96" height="96" loading="lazy" class="h-24 w-24">
                <h2 id="coverage-title" class="type-section mt-2">Where MakanApa<br>knows the food.</h2>
                <p class="mx-auto mt-5 max-w-lg text-lg text-ink/75">Campuses with plenty of places already on the map, counted within 3 km.</p>
            </div>

            <ul class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($coverage as $index => $area)
                    <li class="sketch {{ ['-rotate-1', 'rotate-1', 'rotate-[0.5deg]', '-rotate-[1.5deg]'][$index % 4] }} bg-paper-50 p-6 transition-[rotate] duration-300 hover:rotate-0" style="--sketch-radius: 12px">
                        <span class="tape -top-3 left-1/2 w-20 -translate-x-1/2 {{ $index % 2 ? 'rotate-3' : '-rotate-2' }}" aria-hidden="true"></span>
                        <p class="font-display text-2xl font-bold leading-tight">{{ $area['shortName'] }}</p>
                        @if ($area['name'] !== $area['shortName'])
                            <p class="mt-1 text-sm text-ink/70">{{ $area['name'] }}</p>
                        @endif
                        <p class="mt-4 font-display text-3xl font-bold tabular-nums text-sambal-600">{{ number_format($area['places']) }}</p>
                        <p class="text-sm font-semibold text-ink/70">places nearby</p>
                    </li>
                @endforeach

                <li class="flex flex-col justify-center rounded-xl border-2 border-dashed border-ink/30 p-6">
                    <p class="font-display text-xl font-semibold">Your campus missing?</p>
                    <p class="mt-1 text-ink/75">Ambassadors put their campus on the map.</p>
                    <a href="{{ route('marketing.ambassadors') }}" class="mt-3 w-fit font-display text-lg font-semibold text-sambal-700 underline decoration-2 underline-offset-4">Become an ambassador</a>
                </li>
            </ul>
        </section>
    @endif

    {{-- FEATURES: what the app does beyond the pick. Sizes follow how much there is to show. ---- --}}
    <section id="features" class="scroll-mt-24 border-t border-ink/10 bg-paper-50/60">
        <div class="mx-auto max-w-6xl px-5 py-24 sm:px-6 sm:py-28">
            <div class="max-w-2xl">
                <h2 class="type-section">Small things.<br>Big difference.</h2>
                <p class="mt-5 max-w-lg text-lg text-ink/75">Everything else in the app, for the days the three questions aren't enough.</p>
            </div>

            <div class="mt-16 grid gap-10 lg:grid-cols-6 lg:gap-8">
                {{-- Saved places shuffle --}}
                <article class="sketch grid items-center gap-10 bg-paper p-7 sm:grid-cols-[1fr_15rem] sm:p-9 lg:col-span-4" style="--sketch-radius: 14px">
                    <div>
                        <h3 class="type-card">Shuffle your saved places</h3>
                        <p class="mt-3 max-w-sm text-ink/75">Save two or more places and MakanApa shuffles them like a deck of cards, deals you one, and shows why that one won.</p>
                        <p class="mt-5 font-hand text-2xl text-sambal-700">For when the shortlist is already in your head.</p>
                    </div>
                    <div class="deck relative mx-auto h-60 w-44" aria-hidden="true">
                        @foreach ([['Laksa', '-14deg', '-26deg', '-1.5rem'], ['Satay', '-6deg', '-12deg', '-0.5rem'], ['Roti canai', '5deg', '10deg', '0.75rem']] as [$dish, $tilt, $fan, $shift])
                            <div class="deck-card absolute inset-0 flex items-end rounded-2xl border-2 border-ink/80 bg-cream p-4 shadow-clay" style="--tilt: {{ $tilt }}; --fan: {{ $fan }}; --shift: {{ $shift }}">
                                <span class="font-display text-lg font-semibold text-ink/60">{{ $dish }}</span>
                            </div>
                        @endforeach
                        <div class="deck-card absolute inset-0 flex flex-col rounded-2xl border-2 border-ink bg-paper-50 p-4 shadow-clay" style="--tilt: 0deg; --fan: 0deg; --shift: 0">
                            <span class="text-xs font-semibold text-sambal-700">Dealt to you</span>
                            <span class="mt-1 font-display text-2xl font-bold leading-tight">Nasi kandar</span>
                            <span class="mt-auto font-display text-sm font-semibold text-ink/70">Why this one?</span>
                            <span class="mt-1.5 flex flex-wrap gap-1.5 text-xs font-medium">
                                <span class="rounded-full border border-ink/20 px-2 py-0.5">Saved</span>
                                <span class="rounded-full border border-ink/20 px-2 py-0.5">Open now</span>
                                <span class="rounded-full border border-ink/20 px-2 py-0.5">0.8 km</span>
                            </span>
                        </div>
                    </div>
                </article>

                {{-- Halal --}}
                <article class="sketch flex flex-col bg-paper p-7 lg:col-span-2" style="--sketch-radius: 14px">
                    <svg class="h-12 w-12 text-pandan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                    </svg>
                    <h3 class="type-card mt-4">Halal info you can trust</h3>
                    <p class="mt-3 text-ink/75">Every place shows what we actually know. Only eat halal? MakanApa can hide places known to be non-halal.</p>
                    <ul class="mt-6 space-y-3 font-display text-lg font-semibold leading-none">
                        <li class="relative inline-block text-pandan-700">
                            Halal certified
                            <x-marketing.doodle type="circle" class="draw absolute -inset-x-3 -inset-y-2 h-[calc(100%+1rem)] w-[calc(100%+1.5rem)] text-pandan" />
                        </li>
                        <li class="text-ink/75">Not certified</li>
                        <li class="text-ink/60">Not verified yet</li>
                    </ul>
                    <p class="mt-auto pt-6 text-sm text-ink/70">Certificates and reports from the community are checked before they go live.</p>
                </article>

                {{-- Community --}}
                <article class="sketch flex flex-col bg-paper p-7 sm:p-9 lg:col-span-3" style="--sketch-radius: 14px">
                    <h3 class="type-card">What's your campus eating?</h3>
                    <p class="mt-3 max-w-md text-ink/75">Join your university or area community for trending picks, posts from people nearby, and picks from your ambassadors.</p>
                    <div class="mt-7 rounded-2xl border-2 border-dashed border-ink/25 p-5" aria-hidden="true">
                        <p class="font-display text-xl font-bold">What's {{ config('marketing.demo.university') }} eating?</p>
                        <p class="text-sm text-ink/70">Popular around {{ config('marketing.demo.university') }}</p>
                        <ul class="mt-4 grid gap-2 text-sm font-semibold sm:grid-cols-3">
                            <li class="rounded-xl bg-sambal-50 px-3 py-2 text-sambal-700">Trending picks</li>
                            <li class="rounded-xl bg-paper-200/70 px-3 py-2">What people say</li>
                            <li class="rounded-xl bg-kunyit/25 px-3 py-2">Ambassador picks</li>
                        </ul>
                    </div>
                </article>

                {{-- Add or fix places --}}
                <article class="sketch flex flex-col bg-paper p-7 sm:p-9 lg:col-span-3" style="--sketch-radius: 14px">
                    <h3 class="type-card">Missing a spot? Add it.</h3>
                    <p class="mt-3 max-w-md text-ink/75">Add a new place, fix wrong info, report a closure or send a halal report. My places shows where each one is in review.</p>
                    @php
                        $reviewSteps = ['Sent', 'Reviewing', 'Live'];
                        $submissions = [['New place', 2], ['Halal report', 1], ['Closure report', 0]];
                    @endphp
                    <ul class="mt-7 divide-y divide-dashed divide-ink/20 border-y border-dashed border-ink/20" aria-label="Example: three submissions and where they are in review">
                        @foreach ($submissions as [$type, $reached])
                            <li class="flex items-center justify-between gap-4 py-3">
                                <span class="font-semibold">{{ $type }}</span>
                                <span class="flex items-center gap-1.5 text-xs font-semibold">
                                    @foreach ($reviewSteps as $step => $name)
                                        <span @class([
                                            'rounded-full px-2 py-0.5',
                                            'bg-pandan text-white' => $step === $reached && $name === 'Live',
                                            'bg-ink text-paper' => $step === $reached && $name !== 'Live',
                                            'text-ink/70' => $step < $reached,
                                            'text-ink/35' => $step > $reached,
                                        ])>{{ $name }}</span>
                                    @endforeach
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </article>

                {{-- The smaller things, as a plain list --}}
                <div class="lg:col-span-4">
                    <dl class="grid gap-x-10 gap-y-7 sm:grid-cols-2">
                        @foreach ([
                            ['Nearby map', 'Everything around you on one map. Filter by open now, under RM20 or 4.5 stars and up.'],
                            ['Taste profile', 'Choose up to four things you usually go for, and picks lean that way.'],
                            ['Mealtime picks', 'Switch it on for a pick near you at lunch or dinner. One a day, at most.'],
                            ['No account needed', 'Picks, Nearby and saves work without one. Sign up later and nothing is lost.'],
                            ['Your maps app', 'Directions open in Apple Maps or Google Maps, whichever you prefer.'],
                            ['Photos and reviews', 'See photos and Google reviews before you go.'],
                        ] as [$term, $detail])
                            <div class="border-t-2 border-ink/80 pt-3">
                                <dt class="font-display text-xl font-semibold">{{ $term }}</dt>
                                <dd class="mt-1 text-ink/75">{{ $detail }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Group mode: not out yet --}}
                <article class="sketch relative flex flex-col bg-paper p-7 lg:col-span-2" style="--sketch-radius: 14px">
                    <h3 class="type-card">Group mode</h3>
                    {{-- Rubber stamp, not a badge: this one isn't out yet. --}}
                    <span class="mt-3 inline-block w-fit -rotate-6 rounded-md border-[3px] border-sambal-600 px-2.5 py-0.5 font-display text-lg font-bold text-sambal-600 opacity-85 mix-blend-multiply sm:absolute sm:right-5 sm:top-5 sm:mt-0 sm:-rotate-12">Coming soon</span>
                    <p class="mt-3 text-ink/75">Everyone votes, one place wins. Still cooking.</p>
                    {{-- The group: three moods of the mascot, huddled up to vote. --}}
                    <div class="mx-auto -mb-3 mt-auto flex items-end pt-4" aria-hidden="true">
                        <img src="{{ asset('images/mascot/thinking-160.webp') }}" alt="" width="80" height="80" loading="lazy" class="-mr-4 h-20 w-20 -rotate-6">
                        <img src="{{ asset('images/mascot/excited-320.webp') }}" alt="" width="104" height="104" loading="lazy" class="relative z-10 h-26 w-26">
                        <img src="{{ asset('images/mascot/wave-160.webp') }}" alt="" width="80" height="80" loading="lazy" class="-ml-4 h-20 w-20 rotate-6">
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- AMBASSADORS: the people behind each campus's picks. ------------------------------------- --}}
    <section id="ambassadors" class="scroll-mt-24 border-y border-ink/10 bg-paper">
        <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 py-24 sm:px-6 sm:py-28 lg:grid-cols-[1.15fr_0.85fr]">
            <div>
                <h2 class="type-section">Every campus has someone who knows where to eat.</h2>
                <p class="mt-6 max-w-lg text-lg text-ink/75">
                    MakanApa ambassadors pick the places their community should try, add the spots that are missing and keep halal info honest. Their picks show up for everyone in their community.
                </p>

                <p class="mt-10 text-sm font-medium text-ink/70">Ambassadors are live at</p>
                <ul class="mt-3 flex flex-wrap gap-3">
                    @foreach (config('marketing.ambassador_campuses') as $slug => $campusName)
                        <li class="rounded-md border-[3px] border-ink/80 px-3 py-1 font-display text-lg font-bold {{ $loop->odd ? '-rotate-2' : 'rotate-1' }}">{{ $campusName }}</li>
                    @endforeach
                </ul>

                <a href="{{ route('marketing.ambassadors') }}"
                   class="mt-10 inline-flex min-h-13 items-center justify-center gap-2 rounded-full bg-ink px-7 py-3.5 text-base font-semibold text-paper shadow-[5px_5px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[8px_8px_0_var(--color-sambal-600)]">
                    Become an ambassador
                </a>
            </div>

            <figure class="relative mx-auto w-full max-w-sm">
                <div class="sketch rotate-2 bg-paper-50 p-6 text-center" style="--sketch-radius: 18px">
                    <span class="tape -top-3 left-1/2 -translate-x-1/2 -rotate-3" aria-hidden="true"></span>
                    <img src="{{ asset('images/ambassador-crest-320.webp') }}" srcset="{{ asset('images/ambassador-crest-320.webp') }} 320w, {{ asset('images/ambassador-crest-720.webp') }} 720w" sizes="16rem"
                         alt="MakanApa ambassador crest: the MakanApa rice-ball mascot in a red cap holding a spoon" width="320" height="320" loading="lazy" class="mx-auto w-56">
                    <p class="mt-2 text-sm font-medium text-ink/70">MakanApa ambassador for</p>
                    <p class="font-display text-2xl font-bold">Your campus</p>
                    <p class="mt-3 text-ink/75">“Can't decide what to eat? Ask me, or let MakanApa pick.”</p>
                </div>
                <figcaption class="mt-6 text-center font-hand text-2xl text-ink/70">Every ambassador gets a card to share.</figcaption>
            </figure>
        </div>
    </section>

    {{-- MADE FOR MALAYSIA ------------------------------------------------------------------------ --}}
    <section class="overflow-x-clip">
        <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 py-24 sm:px-6 lg:grid-cols-[0.85fr_1.15fr]">
            <div class="text-center lg:text-left">
                <h2 class="type-section">Made for the way we actually choose food.</h2>
                <p class="mx-auto mt-6 max-w-md text-lg text-ink/75 lg:mx-0">
                    MakanApa understands the question because we've all had the same conversation.
                </p>
            </div>

            <div class="relative mx-auto flex w-full max-w-xl flex-wrap items-center justify-center gap-4 sm:block sm:h-80" role="list" aria-label="Things we all say about food">
                @foreach ([
                    ['“Somewhere close.”', 'sm:left-0 sm:top-6 -rotate-6'],
                    ['“Under RM20.”', 'sm:left-[34%] sm:top-0 rotate-3'],
                    ['“As long as it’s good.”', 'sm:right-0 sm:top-[5.5rem] -rotate-3'],
                    ['“A little spicy is fine.”', 'sm:right-[2%] sm:top-48 rotate-6'],
                    ['“Anything’s fine.”', 'sm:left-[2%] sm:bottom-6 rotate-2'],
                ] as $index => [$quote, $pos])
                    <p role="listitem" class="sketch sm:absolute {{ $pos }} bg-paper-50 px-5 py-2 font-display text-xl font-semibold text-ink sm:text-2xl" style="--sketch-radius: 26px">{{ $quote }}</p>
                @endforeach
                <img src="{{ asset('images/mascot/wave-320.webp') }}" alt="" aria-hidden="true" width="140" height="140" loading="lazy"
                     class="mx-auto h-32 w-32 sm:absolute sm:bottom-2 sm:left-1/2 sm:h-36 sm:w-36 sm:-translate-x-1/2">
            </div>
        </div>
    </section>

    {{-- REVIEWS: real App Store reviews, quoted as written (see RefreshAppStoreRating). Hidden
         until there are enough 4 and 5 star ones to choose from. ------------------------------ --}}
    @if ($reviews)
        <section class="mx-auto max-w-6xl px-5 py-24 sm:px-6 sm:py-28" aria-labelledby="reviews-title">
            <div class="text-center">
                <p class="font-hand text-3xl text-sambal-700">From the App Store</p>
                <h2 id="reviews-title" class="type-section mt-1">What people say.</h2>
            </div>

            <ul class="mt-14 grid gap-10 md:grid-cols-3 md:gap-8">
                @foreach ($reviews as $index => $review)
                    <li class="sketch {{ ['-rotate-1', 'rotate-1 md:translate-y-4', '-rotate-[0.6deg]'][$index % 3] }} flex flex-col bg-paper-50 p-7 transition-[rotate] duration-300 hover:rotate-0" style="--sketch-radius: 12px">
                        <span class="tape -top-3 left-1/2 w-20 -translate-x-1/2 {{ $index % 2 ? 'rotate-3' : '-rotate-2' }}" aria-hidden="true"></span>
                        <p class="text-xl tracking-widest text-kunyit"><span aria-hidden="true">{{ str_repeat('★', $review['rating']) }}</span><span class="sr-only">{{ $review['rating'] }} out of 5 stars</span></p>
                        <blockquote class="mt-3">
                            @if ($review['title'] !== '')
                                <p class="font-display text-xl font-semibold leading-snug">{{ $review['title'] }}</p>
                            @endif
                            <p class="mt-2 text-ink/80">{{ $review['body'] }}</p>
                        </blockquote>
                        <p class="mt-auto pt-5 font-hand text-2xl text-ink/70">{{ $review['author'] }}, App Store</p>
                    </li>
                @endforeach
            </ul>

            <p class="mt-12 text-center">
                <a href="{{ route('marketing.download', ['from' => 'reviews']) }}" class="font-display text-lg font-semibold text-sambal-700 underline decoration-2 underline-offset-4">Read more on the App Store</a>
            </p>
        </section>
    @endif

    {{-- FAQ ------------------------------------------------------------------------------------- --}}
    <section id="faq" class="scroll-mt-24 mx-auto grid max-w-6xl gap-10 border-t border-ink/10 px-5 py-24 sm:px-6 lg:grid-cols-[14rem_1fr_1fr] lg:gap-10">
        <h2 class="type-section lg:pt-2">Okay but…<span class="sr-only"> Frequently asked questions</span></h2>

        @php
            $summary = 'flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden';
            $plus = 'h-6 w-6 shrink-0 text-sambal-600 transition-transform duration-300 group-open:rotate-45';
            $answer = 'pb-5 leading-relaxed text-ink/75';
        @endphp

        @foreach ($faqs as $index => $column)
            <div class="divide-y divide-dashed divide-ink/25 border-y border-dashed border-ink/25">
                @foreach ($column as [$question, $html])
                    <details class="group">
                        <summary class="{{ $summary }}">{{ $question }} <x-marketing.doodle type="plus" class="{{ $plus }}" /></summary>
                        <p class="{{ $answer }}">{!! $html !!}</p>
                    </details>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- FINAL CTA: the one sambal page, torn off the top like the intro curtain, with Bubu
         standing on its bottom edge. ------------------------------------------------------ --}}
    <section class="cta relative mt-32 bg-sambal-600 text-paper sm:mt-40">
        <svg class="absolute bottom-full left-0 h-[34px] w-full -scale-y-100 text-sambal-600" viewBox="0 0 1200 34" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0 0H1200V12L1200 12L1191 20L1170 12L1147 14L1132 23L1115 27L1096 19L1068 8L1043 29L1017 17L989 28L966 9L950 30L921 16L905 10L877 26L848 27L823 18L799 25L783 9L756 23L738 18L719 21L689 11L673 17L645 18L616 24L593 10L572 30L553 15L530 19L502 26L474 18L447 23L427 27L412 26L396 25L379 19L359 11L340 25L317 26L300 12L273 17L255 9L234 9L208 26L193 15L176 9L149 10L128 10L101 21L85 9L65 24L50 19L33 10L18 20L0 18Z"/>
        </svg>

        <div class="mx-auto grid max-w-6xl items-end gap-4 px-5 pt-16 sm:px-6 lg:grid-cols-[1.35fr_0.65fr] lg:gap-10 lg:pt-20">
            <div class="pb-6 text-center lg:pb-24 lg:text-left">
                <h2 class="type-hero">Stop scrolling.<br>Start eating.</h2>
                <p class="mx-auto mt-6 max-w-md text-lg text-paper/90 lg:mx-0">Deciding what to eat shouldn't be the hardest part of your day. Let MakanApa take it from here.</p>

                <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-8 lg:justify-start">
                    <x-marketing.app-store-badge from="final" class="lg:-ml-3.5" />
                    <div class="hidden items-center gap-3 lg:flex">
                        <img src="{{ asset('images/qr-app-store.svg') }}" alt="QR code to download MakanApa from the App Store"
                             width="64" height="64" loading="lazy" class="h-16 w-16 rounded-lg bg-paper p-1.5">
                        <p class="font-hand text-2xl leading-6 text-paper/90">Or scan it<br>with your iPhone.</p>
                    </div>
                </div>
                <p class="mt-3 font-display text-lg font-medium text-paper/85">Free on iPhone</p>
            </div>

            {{-- Bubu, standing on the bottom edge. --}}
            <div class="relative mx-auto w-48 sm:w-60 lg:w-full lg:max-w-xs" aria-hidden="true">
                <p class="absolute -top-4 right-0 z-10 rotate-6 rounded-2xl bg-paper px-4 py-1.5 font-hand text-3xl text-ink shadow-[0_10px_20px_-10px_rgba(0,0,0,0.5)] sm:-right-4">Let's eat!</p>
                <x-marketing.doodle type="sparkle" class="absolute left-2 top-10 h-6 w-6 text-kunyit" />
                <x-marketing.doodle type="sparkle" class="absolute right-6 top-1/2 h-4 w-4 text-paper" />
                <img src="{{ asset('images/mascot/excited-640.webp') }}" alt="" width="320" height="320" loading="lazy"
                     class="-mb-8 w-full drop-shadow-[0_18px_22px_rgba(0,0,0,0.25)] lg:-mb-12">
            </div>
        </div>
    </section>

    <x-marketing.motion-toggle />

@endsection

{{-- Structured data: the app itself, and the FAQ above as a FAQPage. Answers have their tags
     stripped; JSON_HEX_TAG keeps it inside <script>. --}}
@push('meta')
    @php
        $plainAnswer = fn (string $html) => trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'MobileApplication',
                'name' => 'MakanApa',
                'operatingSystem' => 'iOS',
                'applicationCategory' => 'LifestyleApplication',
                'description' => 'Tell MakanApa your mood, budget and how far you\'ll go. It picks one place nearby.',
                'url' => \App\Support\MarketingUrl::to('/'),
                'downloadUrl' => config('marketing.app_download_url'),
                'installUrl' => config('marketing.app_download_url'),
                'sameAs' => [config('marketing.app_download_url'), config('marketing.threads_url')],
                'image' => asset('images/og.jpg'),
                'screenshot' => array_map(fn (string $screen) => asset("images/screens/{$screen}-720.webp"), ['mood', 'result', 'nearby']),
                'inLanguage' => 'en-MY',
                'author' => ['@type' => 'Person', 'name' => 'Hakeemi Ridza'],
                'publisher' => ['@type' => 'Person', 'name' => 'Hakeemi Ridza'],
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'MYR'],
                // Google only accepts a rating the page visibly shows: same $appRating as the hero line.
                'aggregateRating' => $appRating
                    ? ['@type' => 'AggregateRating', 'ratingValue' => $appRating['rating'], 'ratingCount' => $appRating['count'], 'bestRating' => 5, 'worstRating' => 1]
                    : null,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqs)->flatten(1)->map(fn (array $faq) => [
                    '@type' => 'Question',
                    'name' => $faq[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $plainAnswer($faq[1])],
                ])->all(),
            ],
        ];
        $structuredData[0] = array_filter($structuredData[0], fn ($value) => $value !== null);
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@push('body_scripts')
    <script>
        // Live numbers count up from zero the first time they scroll into view.
        (function () {
            var counters = document.querySelectorAll('[data-count]');
            var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!counters.length || calm || !('IntersectionObserver' in window)) return;

            var format = new Intl.NumberFormat('en-MY');
            var counting = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    counting.unobserve(entry.target);
                    var el = entry.target;
                    var target = Number(el.dataset.count);
                    var started = performance.now();
                    requestAnimationFrame(function frame(now) {
                        var t = Math.min(1, (now - started) / 1400);
                        el.textContent = format.format(Math.round(target * (1 - Math.pow(1 - t, 3))));
                        if (t < 1) requestAnimationFrame(frame);
                    });
                });
            }, { threshold: 0.6 });

            counters.forEach(function (el) { el.textContent = '0'; counting.observe(el); });
        })();

        // Time calculator: the same sums as the server-rendered bill, redone as the controls change.
        (function () {
            var form = document.querySelector('.calc-form');
            if (!form) return;
            var whole = new Intl.NumberFormat('en-MY');
            var tenths = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
            var total = document.querySelector('.receipt-total');

            function out(name, text) { document.querySelector('.calc [data-out="' + name + '"]').textContent = text; }

            function update() {
                var minutes = Number(form.elements.minutes.value);
                var meals = form.querySelectorAll('input[name="meals"]:checked').length;
                var decisions = meals * 365;
                var hours = minutes * decisions / 60;
                out('minutes-label', minutes + ' min');
                out('minutes', minutes);
                out('meals', meals);
                out('hours', whole.format(Math.round(hours)));
                out('days', tenths.format(hours / 24));
                out('plates', whole.format(Math.round(minutes * decisions / 15)));
                out('with', tenths.format(10 * decisions / 3600));
                out('saved', whole.format(Math.round(hours - 10 * decisions / 3600)));
                // Little jolt on the total, like the printer just stamped it.
                total.classList.remove('is-printing');
                void total.offsetWidth;
                total.classList.add('is-printing');
            }

            form.addEventListener('input', update);
        })();

        // Hero spinner: lands the spoon on a random dish; "Find …" goes to the App Store.
        (function () {
            var spin = document.querySelector('[data-spin]');
            if (!spin) return;
            var root = document.documentElement;
            var spoon = spin.querySelector('.spin-spoon');
            var dishes = Array.prototype.slice.call(spin.querySelectorAll('[data-dish]'));
            var result = spin.querySelector('[data-spin-result]');
            var find = spin.querySelector('[data-spin-find]');
            var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
            var turn = 0;
            var busy = false;

            function still() { return calm || root.dataset.motion === 'off'; }

            function land(index) {
                var dish = dishes[index];
                dishes.forEach(function (item) { item.classList.toggle('is-picked', item === dish); });
                result.textContent = dish.dataset.name + ' it is.';
                find.textContent = 'Find ' + dish.dataset.name.toLowerCase() + ' near you';
                spin.dataset.state = 'landed';
                busy = false;
            }

            function go() {
                if (busy) return;
                busy = true;
                spin.dataset.state = 'spinning';
                dishes.forEach(function (item) { item.classList.remove('is-picked'); });
                var index = Math.floor(Math.random() * dishes.length);
                var current = ((turn % 360) + 360) % 360;
                // Always forward: a few full turns, then on to the dish (45° apart, 0° = top).
                turn += (still() ? 0 : 360 * 4) + ((index * 45 - current + 360) % 360);
                spoon.style.setProperty('--turn', turn + 'deg');
                setTimeout(function () { land(index); }, still() ? 0 : 2700);
            }

            spin.querySelector('[data-spin-again]').addEventListener('click', go);

            // One spin on its own, once the page is uncovered; a still page just shows the first pick.
            function first() { if (still()) { land(0); } else { setTimeout(go, 700); } }
            if (root.classList.contains('intro-active')) {
                document.addEventListener('makanapa:intro-done', first, { once: true });
            } else {
                first();
            }
        })();
    </script>
@endpush
