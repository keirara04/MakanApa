@extends('layouts.marketing')

@section('title', 'MakanApa | What to Eat')
@section('description', 'Makan apa hari ni? Tell MakanApa your mood, budget and how far you\'ll go. It picks one place nearby. Free public beta on iPhone.')
@section('main_class', '')

@php
    // Spun by both the first-visit intro and the "try it" reel.
    $dishes = ['Nasi lemak', 'Roti canai', 'Satay', 'Char kuey teow', 'Laksa', 'Nasi kandar', 'Mee goreng', 'Teh tarik'];

    // "Try it": [field, question [ms, en], options [value, label (or [ms, en])], default]. Mood
    // values are MarketingController::DEMO_MOODS — the same tags the app sends.
    $tryQuestions = [
        ['mood', ['Apa vibe?', "What's the vibe?"], [['nasi_kandar', 'Nasi Kandar'], ['nasi_lemak', 'Nasi Lemak'], ['ayam_gepuk', 'Ayam Gepuk'], ['mee_goreng', 'Mee Goreng'], ['char_kuey_teow', 'Char Kuey Teow'], ['', ['Anything lah', 'Anything']]], ''],
        ['budget', ['Budget macam mana?', "What's the budget?"], [['1', '~RM10'], ['2', '~RM20'], ['3', '~RM35+'], ['', ['Anything lah', 'Anything']]], '2'],
        ['km', ['Jauh boleh?', 'How far?'], [['1', '1 km'], ['2', '2 km'], ['5', '5 km']], '2'],
    ];

    // Live numbers strip: [stat key, label ms, label en]; a null stat is below its floor.
    $statLabels = [
        ['places', 'Tempat makan dalam peta', 'Makan spots on the map'],
        ['picks', 'Keputusan dah settle', 'Picks settled'],
        ['community', 'Ditambah oleh komuniti', 'Added by the community'],
    ];
    $shownStats = array_values(array_filter($statLabels, fn (array $stat) => $stats[$stat[0]] !== null));

    // FAQ: [question, answer HTML], in two columns. Also emitted as FAQPage structured data below.
    $faqs = [
        [
            ['Why TestFlight?', 'MakanApa is still in public beta, so the iPhone app is shared through Apple\'s TestFlight app. Tap the button, install TestFlight from the App Store if you don\'t have it, then tap <strong>Accept</strong> and <strong>Install</strong> for MakanApa. You might find a bug or two. <span class="lang-ms">Kalau jumpa, <a href="'.url('/support').'" class="font-medium text-sambal-700 underline">bagitahu us</a>.</span><span class="lang-en">If you find one, <a href="'.url('/support').'" class="font-medium text-sambal-700 underline">let us know</a>.</span>'],
            ['Is it free?', 'Yes, joining the beta is free.'],
            ['Which areas does MakanApa work in?', 'MakanApa finds places around wherever you are, so it works anywhere there are restaurants nearby. It\'s built in Malaysia, with Malaysian food in mind.'],
        ],
        [
            ['Is there an Android version?', 'Not yet. MakanApa is iPhone only for now.'],
            ['Does MakanApa keep my location?', 'Your location is used to find places near you and work out distance. We save it with each pick you ask for, linked to your account, and it\'s never shown publicly. <a href="'.route('privacy').'#location" class="font-medium text-sambal-700 underline">Read the privacy policy</a>.'],
            ['How does halal info work?', 'Each place shows what we actually know: <strong>Halal certified</strong>, <strong>not certified</strong> with community notes, or <strong>not verified</strong> yet. We don\'t label a place halal without a certificate, and you can help verify places from the app.'],
        ],
    ];
@endphp

@section('nav_links')
    <a href="#how-it-works" class="bracket-link">[How it works]</a>
    <a href="#try" class="bracket-link js-only">[Try it]</a>
    <a href="#app" class="bracket-link">[The app]</a>
    <a href="#faq" class="bracket-link">[FAQ]</a>
@endsection

@push('nav_extra')
    <x-marketing.lang-toggle />
@endpush

{{-- FIRST-VISIT INTRO -------------------------------------------------------------------------- --}}
{{-- A slot-machine "makan apa ya?" while the page's video clips download, landing on "Jom makan!"
     once they're ready (or after 6s, whichever is first) and lifting away like a torn page. Only
     first visits see it (?intro replays it); Reduce Motion and Data Saver skip it. --}}
@push('head_scripts')
    <script>
        (function () {
            try {
                var replay = /[?&]intro(=|&|$)/.test(location.search);
                var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
                var saver = navigator.connection && navigator.connection.saveData;
                if ((replay || !localStorage.getItem('makanapa-intro-seen')) && !calm && !saver) {
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
            <img src="{{ asset('images/mascot-default.svg') }}" alt="" width="176" height="176" class="is-thinking animate-sketch-bob absolute inset-0 h-full w-full">
            <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" width="176" height="176" class="is-picked absolute inset-0 h-full w-full">
        </div>

        <p class="mt-6 font-display text-3xl font-bold uppercase tracking-wide text-ink/70 sm:text-4xl" aria-hidden="true">
            <x-marketing.lang en="Hmm… what to eat?">Hmm… makan apa ya?</x-marketing.lang>
        </p>

        <div class="relative mt-4 font-display text-[clamp(3.6rem,12vw,6.5rem)] font-bold uppercase" aria-hidden="true">
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
                    <x-marketing.lang en="Let's eat!">Jom makan!</x-marketing.lang>
                    <x-marketing.doodle type="circle" id="intro-circle" class="draw absolute -left-6 -top-1 h-[calc(100%+0.5rem)] w-[calc(100%+3rem)] text-sambal-600" style="--draw-delay: 300ms; --draw-dur: 700ms" />
                </span>
            </p>
        </div>

        <svg class="intro-progress mt-6 h-3 w-56 text-ink/80 sm:w-72" viewBox="0 0 300 20" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
            <path pathLength="1" d="M4 13c52-7 104-9 150-6s98 7 142 1"/>
        </svg>
        <p class="mt-3 text-sm text-ink/60" aria-hidden="true"><x-marketing.lang en="Warming up the wok…">Tengah panaskan kuali…</x-marketing.lang></p>

        <button type="button" data-intro-skip class="bracket-link absolute bottom-5 right-6 transition-opacity font-display text-2xl font-bold uppercase text-ink/60">[Skip]</button>

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

            var MIN_MS = 2400; // long enough for the reel to read as a spin, not a flicker
            var MAX_MS = 6000; // never hold a slow connection hostage
            var started = performance.now();
            var clips = Array.prototype.slice.call(document.querySelectorAll('video[data-clip]'));
            var line = intro.querySelector('.intro-progress path');
            var page = [document.querySelector('body > header'), document.getElementById('main'), document.querySelector('body > footer')];
            var shown = 0;
            var finished = false;

            page.forEach(function (el) { if (el) el.inert = true; });

            // Fetch every clip now rather than when it scrolls into view, so they're ready on entry.
            clips.forEach(function (video) { video.preload = 'auto'; video.load(); });

            function loaded(video) {
                if (video.error || video.readyState >= 4) return 1;
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

    {{-- HERO: one giant hand-lettered question, then the app on a sketched "stage". ------------ --}}
    <section class="relative overflow-x-clip px-5 pt-12 sm:px-6 lg:pt-16">
        <div class="mx-auto max-w-6xl text-center">
            <p class="hero-in font-display text-2xl font-bold uppercase tracking-wide text-ink/70 sm:text-3xl" style="--i: 0">
                <x-marketing.doodle type="burst" class="mr-1 inline-block h-6 w-6 -translate-y-1 -rotate-12 text-sambal-600" />
                <x-marketing.lang en="Hey there, hungry human.">Hai, orang lapar.</x-marketing.lang>
            </p>

            <h1 class="hero-in mx-auto mt-2 max-w-5xl font-display text-[clamp(4.4rem,14vw,11rem)] font-bold uppercase leading-[0.8] tracking-tight text-balance" style="--i: 1">
                <x-marketing.lang en="What should I eat today?">Makan apa hari ni?</x-marketing.lang>
            </h1>

            <div class="hero-in relative mx-auto mt-5 inline-block" style="--i: 2">
                <p class="font-display text-[clamp(2.2rem,5vw,3.6rem)] font-bold uppercase leading-none text-sambal-600">Decided in seconds.</p>
                <x-marketing.doodle type="underline" class="draw absolute -bottom-4 -left-[3%] h-4 w-[106%] text-sambal-600" style="--draw-delay: 900ms; --draw-dur: 1100ms" />
            </div>

            <p class="hero-in mx-auto mt-10 max-w-xl text-lg leading-relaxed text-ink/75 sm:text-xl" style="--i: 3">
                <x-marketing.lang en="Tell us your mood, budget and how far you're willing to go. MakanApa picks one. Not feeling it? Just reroll.">Tell us your mood, budget and how far malas nak jalan. MakanApa picks one. Kalau tak ngam, reroll je.</x-marketing.lang>
            </p>

            <div class="hero-in mt-9 flex flex-col items-center justify-center gap-5 sm:flex-row sm:gap-8" style="--i: 4">
                <x-marketing.download-button from="hero" />
                <p class="font-display text-2xl font-bold uppercase tracking-wide text-ink/70">Free · iPhone · Public beta</p>
            </div>
        </div>

        {{-- The stage: a hand-drawn frame the phones break out of, with margin notes around it. --}}
        <div class="relative mx-auto mt-20 max-w-5xl sm:mt-24">
            <div data-reveal class="sketch mx-2 h-[23rem] bg-paper-50/70 sm:mx-6 sm:h-[30rem] lg:h-[33rem]" style="--sketch-radius: 22px">
                {{-- A Melaka night market running behind the phones, printed in ink on the page. --}}
                <div class="absolute inset-0 overflow-hidden rounded-[20px]">
                    <x-marketing.clip name="night-market" class="opacity-45 mix-blend-multiply grayscale sepia-[.4] contrast-125" />
                    <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgb(251_248_242/0.85)_0%,rgb(251_248_242/0)_38%,rgb(251_248_242/0)_58%,rgb(251_248_242/0.95)_100%)]"></div>
                </div>
            </div>

            <div class="absolute inset-x-0 -bottom-16 flex items-end justify-center sm:-bottom-20">
                <div class="relative z-0 -mr-10 mb-10 hidden w-48 -rotate-[8deg] sm:block lg:w-56">
                    <x-marketing.phone screen="mood" eager class="rounded-[2.2rem] p-1.5"
                                       alt="Mood step with options like Nasi Kandar, Ayam Gepuk and Nasi Padang" />
                </div>
                <div class="relative z-10 w-52 sm:w-60 lg:w-64">
                    <span class="tape -top-3 left-1/2 -translate-x-1/2 -rotate-3" aria-hidden="true"></span>
                    <x-marketing.phone screen="result" eager class="rounded-[2.4rem] p-1.5"
                                       alt="Result screen: MakanApa says Nasi Kandar Haji Basheer, settled, with price, distance and why" />
                </div>
                <div class="relative z-0 -ml-10 mb-10 hidden w-48 rotate-[7deg] sm:block lg:w-56">
                    <x-marketing.phone screen="nearby" eager class="rounded-[2.2rem] p-1.5"
                                       alt="Nearby map with top-rated places and community finds" />
                </div>
            </div>

            {{-- Margin notes --}}
            <div class="pointer-events-none absolute left-14 top-10 hidden w-40 text-left font-display text-[1.7rem] font-bold uppercase leading-[1.05] xl:block" aria-hidden="true">
                <span class="-rotate-3 inline-block"><x-marketing.lang en="Mood. Budget. Distance.">Mood. Budget. Jauh mana.</x-marketing.lang></span>
                <x-marketing.doodle type="arrow-curve" class="draw ml-6 mt-2 h-16 w-24 text-ink/80" style="--draw-delay: 1400ms" />
            </div>

            <div class="pointer-events-none absolute right-14 top-10 hidden w-40 text-right font-display text-[1.7rem] font-bold uppercase leading-[1.05] xl:block" aria-hidden="true">
                <span class="inline-block rotate-2">One answer.<br><span class="text-sambal-600">Not a list.</span></span>
                <x-marketing.doodle type="arrow-curve" class="draw ml-auto mr-6 mt-2 h-16 w-24 -scale-x-100 text-ink/80" style="--draw-delay: 1700ms" />
            </div>

            <div class="absolute -bottom-12 -left-3 z-20 w-28 sm:-bottom-6 sm:left-2 sm:w-44 lg:-left-4 lg:w-52">
                <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" aria-hidden="true" width="208" height="208"
                     class="animate-sketch-bob w-full drop-shadow-[0_12px_14px_rgba(43,28,20,0.18)]">
                <p class="pointer-events-none absolute -top-11 left-1/2 hidden -translate-x-1/3 -rotate-6 whitespace-nowrap font-display text-4xl font-bold uppercase sm:block" aria-hidden="true">Jom! —</p>
            </div>

            {{-- Scan-to-install for desktop visitors, pinned to the frame like a note. --}}
            <div class="absolute -right-10 bottom-24 z-20 hidden rotate-[4deg] xl:block">
                <div class="relative bg-paper-50 p-3 shadow-[0_18px_30px_-18px_rgba(43,28,20,0.5)]">
                    <span class="tape -top-3 left-1/2 w-20 -translate-x-1/2 rotate-2" aria-hidden="true"></span>
                    <img src="{{ asset('images/qr-testflight.svg') }}" alt="QR code to join the MakanApa beta on TestFlight"
                         width="96" height="96" class="h-24 w-24">
                    <p class="mt-1 text-center font-display text-xl font-bold uppercase leading-5">Scan with<br>your iPhone</p>
                </div>
            </div>
        </div>
    </section>

    {{-- LIVE NUMBERS: straight from the database, recounted hourly (see LandingInsights). -------- --}}
    @if ($shownStats)
        <section class="mx-auto max-w-5xl px-5 pt-36 sm:px-6 sm:pt-40" aria-label="MakanApa in numbers">
            <dl @class(['grid gap-12 text-center', 'sm:grid-cols-2' => count($shownStats) === 2, 'sm:grid-cols-3' => count($shownStats) === 3])>
                @foreach ($shownStats as $index => [$key, $labelMs, $labelEn])
                    <div data-stat="{{ $key }}" data-reveal class="flex flex-col-reverse items-center" style="--i: {{ $index }}">
                        <dt class="mt-2 font-display text-2xl font-bold uppercase tracking-wide text-ink/70"><x-marketing.lang :en="$labelEn">{{ $labelMs }}</x-marketing.lang></dt>
                        <dd class="relative font-display text-[clamp(4rem,9vw,6.5rem)] font-bold leading-none">
                            <span data-count="{{ $stats[$key] }}">{{ number_format($stats[$key]) }}</span>
                            <x-marketing.doodle type="underline" class="draw absolute -bottom-1 left-[10%] h-3 w-[80%] text-sambal-600" style="--draw-delay: {{ 400 + $index * 150 }}ms" />
                        </dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-10 text-center text-sm text-ink/55"><x-marketing.lang en="Live from MakanApa, updated every hour.">Live dari MakanApa, dikemas kini setiap jam.</x-marketing.lang></p>
        </section>
    @endif

    {{-- PROBLEM: the one dark page in the sketchbook. -------------------------------------------- --}}
    <section @class(['bg-ink text-paper', 'mt-24 sm:mt-28' => $shownStats, 'mt-36 sm:mt-40' => ! $shownStats])>
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-[17rem_1fr] lg:gap-16 lg:py-28">
            <figure data-reveal class="relative mx-auto w-56 lg:w-full">
                <div class="sketch p-5" style="--sketch-color: var(--color-paper); --sketch-radius: 4px">
                    <img src="{{ asset('images/mascot-sad.svg') }}" alt="" aria-hidden="true" width="224" height="224" loading="lazy" class="mx-auto w-full">
                </div>
                <figcaption class="mt-5 flex items-start gap-2 font-display text-2xl font-bold uppercase leading-none text-paper/80">
                    <x-marketing.doodle type="arrow-curve" class="draw h-10 w-14 shrink-0 -scale-y-100 text-paper/70" />
                    <span><x-marketing.lang en="Me, every lunch.">Aku, setiap kali lunch.</x-marketing.lang></span>
                </figcaption>
            </figure>

            <div data-reveal style="--i: 1">
                <p class="font-display text-[clamp(3.2rem,7.5vw,6.2rem)] font-bold uppercase leading-[0.88] tracking-tight text-balance">
                    <x-marketing.lang>
                        Scroll Grab <span class="relative inline-block whitespace-nowrap">20 minit.<x-marketing.doodle type="circle" class="draw absolute -left-2 -top-4 h-[calc(100%+2rem)] w-[calc(100%+1.25rem)] text-sambal-500 sm:-left-6 sm:-top-5 sm:h-[calc(100%+2.5rem)] sm:w-[calc(100%+3rem)]" style="--draw-delay: 600ms" /></span>
                        <x-slot:en><span class="relative inline-block whitespace-nowrap">20 minutes<x-marketing.doodle type="circle" class="draw absolute -left-2 -top-4 h-[calc(100%+2rem)] w-[calc(100%+1.25rem)] text-sambal-500 sm:-left-6 sm:-top-5 sm:h-[calc(100%+2.5rem)] sm:w-[calc(100%+3rem)]" style="--draw-delay: 600ms" /></span> scrolling Grab.</x-slot:en>
                    </x-marketing.lang>
                    <span class="mt-3 block text-sambal-300"><x-marketing.lang en="Still no idea what to eat?">Still tak tahu nak makan apa?</x-marketing.lang></span>
                </p>
                <p class="mt-8 max-w-lg text-lg text-paper/75">That's literally why we built MakanApa.</p>
            </div>
        </div>
    </section>

    {{-- EVERYTHING LOOKS SEDAP: real Malaysian food scenes, taped in like polaroids. ------------------ --}}
    <section class="mx-auto max-w-6xl px-5 pt-24 sm:px-6 sm:pt-28">
        <div data-reveal class="text-center">
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-sambal-600"><x-marketing.lang en="The real problem">Masalah sebenar</x-marketing.lang></p>
            <h2 class="mt-1 font-display text-[clamp(3rem,7vw,5.5rem)] font-bold uppercase leading-[0.88] tracking-tight">
                <x-marketing.lang en="Everything looks good.">Semua nampak sedap.</x-marketing.lang>
            </h2>
            <p class="mx-auto mt-5 max-w-lg text-lg text-ink/75">
                <x-marketing.lang en="Night markets, mamak, satay by the roadside… choosing is the hard part. So let MakanApa choose.">Pasar malam, mamak, satay tepi jalan… nak pilih tu yang susah. So biar MakanApa pilih.</x-marketing.lang>
            </p>
        </div>

        @php
            $scenes = [
                // [clip, title [ms, en], note [ms, en], tilt]
                ['pasar-malam', ['Pasar malam?', 'Night market?'], ['Rojak buah, jus jambu, takoyaki… semua ada.', 'Fruit rojak, guava juice, takoyaki… it’s all there.'], '-rotate-2'],
                ['mamak', ['Mamak?', 'Mamak?'], ['Roti canai, teh tarik, bukak 24 jam.', 'Roti canai and teh tarik, open 24 hours.'], 'rotate-1 md:-translate-y-6'],
                ['satay', ['Satay?', 'Satay?'], ['Bau asap dia pun dah sedap.', 'Even the smoke smells good.'], 'rotate-2'],
            ];
        @endphp

        <div class="mt-16 grid gap-12 md:grid-cols-3 md:gap-8">
            @foreach ($scenes as $index => [$clip, $title, $note, $tilt])
                <figure data-reveal class="sketch {{ $tilt }} bg-paper-50 p-3 pb-6 transition-[rotate,translate] duration-300 hover:rotate-0" style="--i: {{ $index }}; --sketch-radius: 6px">
                    <span class="tape -top-3 left-1/2 w-24 -translate-x-1/2 {{ $index % 2 ? 'rotate-2' : '-rotate-3' }}" aria-hidden="true"></span>
                    <div class="aspect-[4/3] overflow-hidden rounded-[3px] bg-ink/10">
                        <x-marketing.clip :name="$clip" />
                    </div>
                    <figcaption class="mt-5 px-2">
                        <p class="font-display text-4xl font-bold uppercase leading-none"><x-marketing.lang :en="$title[1]">{{ $title[0] }}</x-marketing.lang></p>
                        <p class="mt-2 text-sm text-ink/70"><x-marketing.lang :en="$note[1]">{{ $note[0] }}</x-marketing.lang></p>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <div data-reveal class="mt-14 flex flex-col items-center text-center" style="--i: 3">
            <p class="font-display text-3xl font-bold uppercase leading-none sm:text-4xl">
                <x-marketing.lang en="Too many choices?">Banyak sangat pilihan?</x-marketing.lang>
                <span class="text-sambal-600"><x-marketing.lang en="We pick one.">Kami pilih satu.</x-marketing.lang></span>
            </p>
            <x-marketing.doodle type="arrow-down" class="draw mt-3 h-20 w-10 text-ink/80" style="--draw-delay: 400ms" />
        </div>
    </section>

    {{-- HOW IT WORKS ----------------------------------------------------------------------------- --}}
    <section id="how-it-works" class="scroll-mt-20 mx-auto max-w-6xl px-5 pb-24 pt-10 sm:px-6 sm:pb-28 sm:pt-12">
        <div data-reveal class="text-center">
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-sambal-600">How it works</p>
            <h2 class="mt-1 font-display text-[clamp(3rem,7vw,5.5rem)] font-bold uppercase leading-[0.88] tracking-tight">Three questions.<br>One answer.</h2>
        </div>

        @php
            $steps = [
                // [illustration, title [ms, en], body [ms, en], options (label or [ms, en]), picked option]
                ['mood-spicy', ['Apa vibe?', "What's the vibe?"], ['Nasi? Pedas? Something light? Anything also can.', 'Rice? Spicy? Something light? Anything works.'], ['Nasi Kandar', 'Ayam Gepuk', 'Nasi Lemak', 'Mee Goreng'], 'Nasi Kandar'],
                ['budget-normal', ['Budget macam mana?', "What's the budget?"], ['Save sikit, normal lah, or treat yourself.', 'Save a little, keep it normal, or treat yourself.'], ['~RM10', '~RM20', '~RM35+', ['Anything lah', 'Anything']], '~RM20'],
                ['distance-walk', ['Jauh boleh?', 'How far can you go?'], ['Dekat je, okay lah, or janji sedap.', 'Close by, a bit further, or anywhere worth it.'], ['Within 1 km', 'Within 2 km', 'Within 5 km'], 'Within 2 km'],
            ];
            $tilts = ['-rotate-1', 'rotate-1', '-rotate-[0.5deg]'];
        @endphp

        <ol class="mt-16 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-7">
            @foreach ($steps as $i => [$art, $title, $body, $options, $picked])
                <li data-reveal class="sketch {{ $tilts[$i] }} flex flex-col bg-paper-50 p-6 transition-[rotate] duration-300 hover:rotate-0" style="--i: {{ $i }}; --sketch-radius: 14px">
                    <div class="flex items-start justify-between">
                        <span class="relative flex h-12 w-12 items-center justify-center font-display text-4xl font-bold">
                            {{ $i + 1 }}
                            <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-ink/70" />
                        </span>
                        <img src="{{ asset("images/illustrations/{$art}.svg") }}" alt="" aria-hidden="true" width="64" height="64" loading="lazy" class="h-16 w-16">
                    </div>
                    <h3 class="mt-5 font-display text-4xl font-bold uppercase leading-none"><x-marketing.lang :en="$title[1]">{{ $title[0] }}</x-marketing.lang></h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink/70"><x-marketing.lang :en="$body[1]">{{ $body[0] }}</x-marketing.lang></p>
                    <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-3 font-display text-2xl font-bold uppercase leading-none" aria-hidden="true">
                        @foreach ($options as $option)
                            <li @class(['relative', 'text-sambal-600' => $option === $picked, 'text-ink/45' => $option !== $picked])>
                                @if (is_array($option))
                                    <x-marketing.lang :en="$option[1]">{{ $option[0] }}</x-marketing.lang>
                                @else
                                    {{ $option }}
                                @endif
                                @if ($option === $picked)
                                    <x-marketing.doodle type="circle" class="draw absolute -inset-x-3 -inset-y-2 h-[calc(100%+1rem)] w-[calc(100%+1.5rem)] text-sambal-600" style="--draw-delay: {{ 500 + $i * 150 }}ms" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach

            <li data-reveal class="sketch relative flex min-h-72 rotate-1 flex-col bg-ink p-6 text-paper transition-[rotate] duration-300 hover:rotate-0" style="--i: 3; --sketch-radius: 14px">
                <span class="relative flex h-12 w-12 items-center justify-center font-display text-4xl font-bold">
                    4
                    <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-paper/70" />
                </span>
                <p class="mt-5 font-display text-7xl font-bold uppercase leading-[0.8]">Done.</p>
                <p class="mt-3 max-w-[11rem] text-paper/80">We pick one. You stop deciding.</p>
                <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" aria-hidden="true" width="150" height="150" loading="lazy"
                     class="absolute -bottom-4 -right-4 h-36 w-36">
                <x-marketing.doodle type="sparkle" class="absolute right-28 top-14 h-5 w-5 text-kunyit" />
            </li>
        </ol>
    </section>

    {{-- TRY IT: the real picking engine on real places, anchored on a campus since the page never
         asks for the visitor's location. Needs JS, so it's hidden without it. ------------------- --}}
    <section id="try" class="js-only scroll-mt-20 mx-auto max-w-6xl px-5 pb-20 sm:px-6">
        <div data-reveal class="text-center">
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-sambal-600"><x-marketing.lang en="Now you try">Cuba sekarang</x-marketing.lang></p>
            <h2 class="mt-1 font-display text-[clamp(3rem,7vw,5.5rem)] font-bold uppercase leading-[0.88] tracking-tight text-balance">
                <x-marketing.lang :en="'What to eat near '.$demoArea.'?'">Makan apa dekat {{ $demoArea }}?</x-marketing.lang>
            </h2>
            <p class="mx-auto mt-5 max-w-xl text-lg text-ink/75">
                <x-marketing.lang en="Real places, picked the same way the app picks. In the app, it uses wherever you are.">Tempat betul, dipilih sama macam dalam app. Dalam app, dia guna lokasi kau sendiri.</x-marketing.lang>
            </p>
        </div>

        <div data-reveal class="sketch mt-14 grid gap-12 bg-paper-50 p-6 sm:p-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14" style="--i: 1; --sketch-radius: 18px">
            <form id="try-form" action="{{ route('marketing.try') }}" method="get" class="space-y-7">
                @foreach ($tryQuestions as $number => [$field, $question, $options, $default])
                    <fieldset>
                        <legend class="font-display text-3xl font-bold uppercase leading-none">{{ $number + 1 }}. <x-marketing.lang :en="$question[1]">{{ $question[0] }}</x-marketing.lang></legend>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-display text-[1.7rem] font-bold uppercase leading-none">
                            @foreach ($options as [$value, $label])
                                <label class="chip">
                                    <input type="radio" name="{{ $field }}" value="{{ $value }}" class="sr-only" @checked($value === $default)>
                                    <span class="relative whitespace-nowrap">
                                        @if (is_array($label))
                                            <x-marketing.lang :en="$label[1]">{{ $label[0] }}</x-marketing.lang>
                                        @else
                                            {{ $label }}
                                        @endif
                                        <x-marketing.doodle type="circle" class="absolute -left-2.5 -top-1.5 h-[calc(100%+0.75rem)] w-[calc(100%+1.25rem)] text-sambal-600" />
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-ink px-5 py-2.5 font-semibold text-paper shadow-[3px_3px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[5px_5px_0_var(--color-sambal-600)] min-h-13 px-7 py-3.5 text-base shadow-[5px_5px_0_var(--color-sambal-600)] hover:shadow-[8px_8px_0_var(--color-sambal-600)]">
                    <x-marketing.lang en="What should I eat?">Makan apa?</x-marketing.lang>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            <div class="try-panel relative flex min-h-80 items-center justify-center text-center" data-show="idle" aria-live="polite">
                <div data-state="idle" class="flex-col items-center">
                    <img src="{{ asset('images/mascot-default.svg') }}" alt="" aria-hidden="true" width="140" height="140" loading="lazy" class="animate-sketch-bob h-32 w-32">
                    <p class="mt-4 max-w-[15rem] font-display text-3xl font-bold uppercase leading-none text-ink/70">
                        <x-marketing.lang en="Pick your answers, then hit the button.">Pilih jawapan, lepas tu tekan butang.</x-marketing.lang>
                    </p>
                </div>

                <div data-state="spinning" class="flex-col items-center" aria-hidden="true">
                    <p class="font-display text-3xl font-bold uppercase text-ink/60"><x-marketing.lang en="Hmm… what to eat?">Hmm… makan apa ya?</x-marketing.lang></p>
                    <div class="slot-reel mt-2 font-display text-[clamp(3rem,7vw,4.5rem)] font-bold uppercase">
                        <ul>
                            @foreach ([...$dishes, ...$dishes] as $dish)
                                <li class="whitespace-nowrap leading-[1.3]">{{ $dish }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div data-state="pick" class="w-full flex-col items-center">
                    <p class="font-display text-2xl font-bold uppercase text-ink/60">MakanApa says…</p>
                    <p data-field="name" class="mt-1 font-display text-[clamp(2.8rem,6vw,4.4rem)] font-bold uppercase leading-[0.9] text-balance text-sambal-600"></p>
                    <p data-field="headline" class="mt-2 font-display text-2xl font-bold uppercase text-ink/75"></p>
                    <ul data-field="facts" class="mt-4 flex flex-wrap justify-center gap-2"></ul>
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-x-5 gap-y-3">
                        <a data-field="url" href="#" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-ink px-5 py-2.5 font-semibold text-paper shadow-[3px_3px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[5px_5px_0_var(--color-sambal-600)] text-sm">
                            <x-marketing.lang en="See this place">Tengok tempat ni</x-marketing.lang>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                        <button type="button" data-reroll class="bracket-link font-display text-2xl font-bold uppercase"><x-marketing.lang en="[Find another!]">[Cari lagi!]</x-marketing.lang></button>
                    </div>
                </div>

                <div data-state="none" class="flex-col items-center">
                    <img src="{{ asset('images/mascot-sad.svg') }}" alt="" aria-hidden="true" width="120" height="120" loading="lazy" class="h-28 w-28">
                    <p class="mt-3 font-display text-4xl font-bold uppercase leading-none">Aiyo…</p>
                    <p data-variant="first" class="mt-2 max-w-xs text-ink/75"><x-marketing.lang :en="'Nothing matches that around '.$demoArea.' yet. Try 5 km, or Anything lah.'">Tak jumpa yang ngam dekat {{ $demoArea }}. Cuba 5 km, atau Anything lah.</x-marketing.lang></p>
                    <p data-variant="more" class="mt-2 max-w-xs text-ink/75"><x-marketing.lang en="That's every match nearby for those answers. Try different ones!">Dah habis semua yang ngam untuk jawapan tu. Cuba jawapan lain!</x-marketing.lang></p>
                </div>

                <div data-state="error" class="flex-col items-center">
                    <img src="{{ asset('images/mascot-sad.svg') }}" alt="" aria-hidden="true" width="120" height="120" loading="lazy" class="h-28 w-28">
                    <p class="mt-3 font-display text-4xl font-bold uppercase leading-none"><x-marketing.lang en="Slow down a bit lah">Slow sikit lah</x-marketing.lang></p>
                    <p class="mt-2 max-w-xs text-ink/75"><x-marketing.lang en="Too many tries in a row. Give it a minute, then try again.">Banyak sangat cuba berturut-turut. Tunggu seminit, then cuba lagi.</x-marketing.lang></p>
                </div>
            </div>
        </div>
    </section>

    {{-- MOST PICKED NEAR THE DEMO CAMPUS (or top rated, labelled as such). -------------------------- --}}
    @if ($nearby['kind'])
        <section class="mx-auto max-w-3xl px-5 pb-24 sm:px-6 sm:pb-28" data-nearby="{{ $nearby['kind'] }}">
            <div data-reveal class="sketch relative -rotate-[0.6deg] bg-paper-50 p-6 sm:p-9" style="--sketch-radius: 10px">
                <span class="tape -top-3 left-1/2 -translate-x-1/2 -rotate-2" aria-hidden="true"></span>
                @if ($nearby['kind'] === 'picked')
                    <h3 class="font-display text-4xl font-bold uppercase leading-none sm:text-5xl"><x-marketing.lang :en="'Most picked near '.$demoArea">Paling ramai pilih dekat {{ $demoArea }}</x-marketing.lang></h3>
                    <p class="mt-1 text-sm text-ink/60"><x-marketing.lang en="By MakanApa users over the last 30 days.">Oleh pengguna MakanApa, 30 hari lepas.</x-marketing.lang></p>
                @else
                    <h3 class="font-display text-4xl font-bold uppercase leading-none sm:text-5xl"><x-marketing.lang :en="'Top rated near '.$demoArea">Top rated dekat {{ $demoArea }}</x-marketing.lang></h3>
                    <p class="mt-1 text-sm text-ink/60"><x-marketing.lang en="By Google rating, among places MakanApa knows.">Ikut rating Google, antara tempat yang MakanApa tahu.</x-marketing.lang></p>
                @endif

                <ol class="mt-6 divide-y divide-dashed divide-ink/20 border-t border-dashed border-ink/20">
                    @foreach ($nearby['items'] as $rank => $place)
                        <li>
                            <a href="{{ $place['url'] }}" class="group flex items-center gap-4 py-3.5">
                                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center font-display text-3xl font-bold">
                                    {{ $rank + 1 }}
                                    <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-ink/50" style="--draw-delay: {{ 200 + $rank * 120 }}ms" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-lg font-semibold transition-colors group-hover:text-sambal-600">{{ $place['name'] }}</span>
                                    <span class="block text-sm text-ink/60">{{ $place['headline'] }} · {{ number_format($place['distanceKm'], 1) }} km</span>
                                </span>
                                <span class="shrink-0 font-display text-2xl font-bold uppercase text-sambal-600">
                                    @if ($place['pickers'] !== null)
                                        <x-marketing.lang :en="$place['pickers'].' people'">{{ $place['pickers'] }} orang</x-marketing.lang>
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

    {{-- THE REAL APP: screenshots taped into the sketchbook. -------------------------------------- --}}
    <section id="app" class="scroll-mt-20 border-y border-ink/10 bg-paper-50/60 py-24 sm:py-28">
        <div data-reveal class="mx-auto max-w-4xl px-5 text-center sm:px-6">
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-sambal-600">The real app</p>
            <h2 class="mt-1 font-display text-[clamp(2.8rem,6.5vw,5rem)] font-bold uppercase leading-[0.9] tracking-tight text-balance">
                <x-marketing.lang en="From “what should we eat?” to settled in a few taps.">From “entah nak makan apa” to settled in a few taps.</x-marketing.lang>
            </h2>
        </div>

        <ol class="mt-16 flex snap-x snap-mandatory gap-10 overflow-x-auto px-8 pb-10 pt-4 [scrollbar-width:thin] lg:mx-auto lg:grid lg:max-w-6xl lg:grid-cols-5 lg:gap-8 lg:overflow-visible lg:px-6"
            tabindex="0" aria-label="App screenshots, in order">
            @foreach ([
                ['mood', '1. Pick a mood', 'Quick and simple.', 'Mood step with options like Nasi Kandar, Ayam Gepuk and Nasi Padang', '-rotate-2'],
                ['budget', '2. Set a budget', 'From save to treat yourself.', 'Budget step with ~RM10 save sikit, ~RM20 normal lah and ~RM35+ feeling kaya', 'rotate-1'],
                ['distance', '3. How far?', 'Stay nearby or go a little further.', 'Distance step with within 1 km dekat je, 2 km okay lah and 5 km janji sedap', '-rotate-1'],
                ['result', '4. Get your pick', 'One answer, not a list.', 'Result screen: MakanApa says Nasi Kandar Haji Basheer, settled, with price, distance and why', 'rotate-2'],
                ['nearby', '5. Or see what’s around', 'Explore the map.', 'Nearby map with top-rated places and community finds', '-rotate-1'],
            ] as $index => [$screen, $caption, $sub, $alt, $tilt])
                <li data-reveal class="w-[60vw] max-w-[14rem] shrink-0 snap-center text-center lg:w-auto lg:max-w-none" style="--i: {{ $index }}">
                    <div class="relative {{ $tilt }} transition-[rotate,translate] duration-300 hover:-translate-y-2 hover:rotate-0">
                        <span class="tape -top-3 left-1/2 w-20 -translate-x-1/2 {{ $index % 2 ? 'rotate-3' : '-rotate-2' }}" aria-hidden="true"></span>
                        <x-marketing.phone :screen="$screen" :alt="$alt" class="rounded-[2rem] p-1.5" />
                    </div>
                    <p class="mt-6 font-display text-3xl font-bold uppercase leading-none">{{ $caption }}</p>
                    <p class="mt-1 text-sm text-ink/70">{{ $sub }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- WHAT MAKES IT DIFFERENT: index cards pinned to the page. ---------------------------------- --}}
    <section id="features" class="mx-auto max-w-6xl px-5 py-24 sm:px-6 sm:py-28">
        <div data-reveal class="text-center">
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-sambal-600">What makes MakanApa different</p>
            <h2 class="mt-1 font-display text-[clamp(3rem,7vw,5.5rem)] font-bold uppercase leading-[0.88] tracking-tight">Small things.<br>Big difference.</h2>
        </div>

        <div class="mt-16 grid gap-12 md:grid-cols-2 md:gap-x-10 md:gap-y-14">
            {{-- Reroll --}}
            <article data-reveal class="sketch relative -rotate-1 bg-paper-50 p-7 transition-[rotate] duration-300 hover:rotate-0" style="--sketch-radius: 10px">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('images/illustrations/mood-quick.svg') }}" alt="" aria-hidden="true" width="56" height="56" loading="lazy" class="h-14 w-14 shrink-0">
                    <div>
                        <h3 class="font-display text-4xl font-bold uppercase leading-none">Reroll</h3>
                        <p class="mt-2 text-ink/75"><x-marketing.lang en="Not feeling it? Next one. Same preferences, different spot.">Tak ngam? Next one. Same preferences, different spot.</x-marketing.lang></p>
                    </div>
                </div>
                <div class="mt-6 flex items-end gap-5" aria-hidden="true">
                    <div class="-mb-16 w-24 shrink-0 -rotate-6 rounded-2xl bg-ink p-1 shadow-phone">
                        <img src="{{ asset('images/screens/result-360.webp') }}" alt="" width="360" height="783" loading="lazy" class="rounded-xl">
                    </div>
                    <div class="mb-3 flex-1">
                        <p class="font-display text-3xl font-bold uppercase leading-none">Not feeling it?</p>
                        <p class="relative mt-3 inline-block font-display text-3xl font-bold uppercase leading-none text-sambal-600">
                            <x-marketing.lang en="Find another!">Cari lagi!</x-marketing.lang>
                            <x-marketing.doodle type="underline" class="draw absolute -bottom-2 left-0 h-3 w-full text-sambal-600" />
                        </p>
                    </div>
                </div>
            </article>

            {{-- Halal --}}
            <article data-reveal class="sketch rotate-1 bg-paper-50 p-7 transition-[rotate] duration-300 hover:rotate-0" style="--i: 1; --sketch-radius: 10px">
                <div class="flex items-start gap-4">
                    <svg class="h-14 w-14 shrink-0 text-sambal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                    </svg>
                    <div>
                        <h3 class="font-display text-4xl font-bold uppercase leading-none">Halal info you can understand</h3>
                        <p class="mt-2 text-ink/75">See which places are halal certified, which aren't, and which we haven't verified yet. No guessing.</p>
                    </div>
                </div>
                <ul class="mt-7 flex flex-wrap gap-x-6 gap-y-3 font-display text-2xl font-bold uppercase leading-none">
                    <li class="relative text-pandan-700">
                        Halal certified
                        <x-marketing.doodle type="circle" class="draw absolute -inset-x-3 -inset-y-2 h-[calc(100%+1rem)] w-[calc(100%+1.5rem)] text-pandan" />
                    </li>
                    <li class="text-ink/75">Not certified</li>
                    <li class="text-ink/55">Not verified yet</li>
                </ul>
            </article>

            {{-- Community --}}
            <article data-reveal class="sketch rotate-[0.5deg] bg-paper-50 p-7 transition-[rotate] duration-300 hover:rotate-0" style="--sketch-radius: 10px">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('images/illustrations/location-map.svg') }}" alt="" aria-hidden="true" width="56" height="56" loading="lazy" class="h-14 w-14 shrink-0">
                    <div>
                        <h3 class="font-display text-4xl font-bold uppercase leading-none">Community picks</h3>
                        <p class="mt-2 text-ink/75">See what people around you are actually picking.</p>
                    </div>
                </div>
                <div class="mt-7 flex items-center gap-3" aria-hidden="true">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sambal-600 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink/70">Trending near you</p>
                        <p class="font-display text-3xl font-bold uppercase leading-none">Top rated nearby</p>
                    </div>
                </div>
            </article>

            {{-- Geng --}}
            <article data-reveal class="sketch relative -rotate-1 bg-paper-50 p-7 transition-[rotate] duration-300 hover:rotate-0" style="--i: 1; --sketch-radius: 10px">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('images/illustrations/geng-group.svg') }}" alt="" aria-hidden="true" width="56" height="56" loading="lazy" class="h-14 w-14 shrink-0">
                    <div class="flex-1">
                        <h3 class="font-display text-4xl font-bold uppercase leading-none">Geng mode</h3>
                        {{-- Rubber stamp, not a badge: this one isn't out yet. --}}
                        <span class="mt-2 inline-block -rotate-6 rounded-md border-[3px] border-sambal-600 px-2.5 py-0.5 font-display text-2xl font-bold uppercase tracking-wider text-sambal-600 opacity-85 mix-blend-multiply sm:absolute sm:right-5 sm:top-5 sm:mt-0 sm:-rotate-12">Coming soon</span>
                        <p class="mt-2 text-ink/75">Everyone votes, one place wins. Still cooking.</p>
                    </div>
                </div>
                <img src="{{ asset('images/mascot-geng.svg') }}" alt="" aria-hidden="true" width="790" height="530" loading="lazy"
                     class="mx-auto -mb-3 mt-4 w-56">
            </article>
        </div>
    </section>

    {{-- MADE FOR MALAYSIA ------------------------------------------------------------------------ --}}
    <section class="overflow-x-clip border-y border-ink/10 bg-paper-50/60">
        <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 py-24 sm:px-6 lg:grid-cols-[0.85fr_1.15fr]">
            <div data-reveal class="text-center lg:text-left">
                <h2 class="font-display text-[clamp(3rem,6.5vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight text-balance">Made for the way we actually choose food.</h2>
                <p class="mx-auto mt-6 max-w-md text-lg text-ink/75 lg:mx-0">
                    MakanApa understands the question because we've all had the same conversation.
                </p>
            </div>

            <div class="relative mx-auto flex w-full max-w-xl flex-wrap items-center justify-center gap-4 sm:block sm:h-80" role="list" aria-label="Things we all say about food">
                @foreach ([
                    [['“Dekat je.”', '“Somewhere close.”'], 'sm:left-0 sm:top-6 -rotate-6'],
                    [['“Bawah RM20.”', '“Under RM20.”'], 'sm:left-[28%] sm:top-0 rotate-3'],
                    [['“Janji sedap.”', '“As long as it’s good.”'], 'sm:right-[6%] sm:top-12 -rotate-3'],
                    [['“Pedas sikit boleh.”', '“A little spicy is fine.”'], 'sm:right-0 sm:top-40 rotate-6'],
                    [['“Anything lah.”', '“Anything’s fine.”'], 'sm:left-[2%] sm:bottom-6 rotate-2'],
                ] as $index => [$quote, $pos])
                    <p role="listitem" data-reveal class="sketch sm:absolute {{ $pos }} bg-paper-50 px-5 py-2 font-display text-3xl font-bold uppercase text-ink sm:text-4xl" style="--i: {{ $index }}; --sketch-radius: 26px"><x-marketing.lang :en="$quote[1]">{{ $quote[0] }}</x-marketing.lang></p>
                @endforeach
                <img src="{{ asset('images/mascot-default.svg') }}" alt="" aria-hidden="true" width="140" height="140" loading="lazy"
                     class="animate-sketch-bob mx-auto h-32 w-32 sm:absolute sm:bottom-2 sm:left-1/2 sm:h-36 sm:w-36 sm:-translate-x-1/2">
            </div>
        </div>
    </section>

    {{-- FAQ ------------------------------------------------------------------------------------- --}}
    <section id="faq" class="scroll-mt-20 mx-auto grid max-w-6xl gap-10 px-5 py-24 sm:px-6 lg:grid-cols-[14rem_1fr_1fr] lg:gap-10">
        <h2 data-reveal class="font-display text-6xl font-bold uppercase leading-[0.85] tracking-tight lg:pt-2">Okay but…</h2>

        @php
            $summary = 'flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-lg font-semibold marker:content-none [&::-webkit-details-marker]:hidden';
            $plus = 'h-6 w-6 shrink-0 text-sambal-600 transition-transform duration-300 group-open:rotate-45';
            $answer = 'pb-5 leading-relaxed text-ink/75';
        @endphp

        @foreach ($faqs as $index => $column)
            <div data-reveal class="divide-y divide-dashed divide-ink/25 border-y border-dashed border-ink/25" style="--i: {{ $index + 1 }}">
                @foreach ($column as [$question, $html])
                    <details class="group">
                        <summary class="{{ $summary }}">{{ $question }} <x-marketing.doodle type="plus" class="{{ $plus }}" /></summary>
                        <p class="{{ $answer }}">{!! $html !!}</p>
                    </details>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- FINAL CTA ------------------------------------------------------------------------------- --}}
    <section class="relative mx-auto max-w-5xl px-5 pb-16 pt-8 text-center sm:px-6">
        <div data-reveal class="relative mx-auto w-32 sm:w-36">
            <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" aria-hidden="true" width="144" height="144" loading="lazy" class="animate-sketch-bob w-full">
            <x-marketing.doodle type="sparkle" class="absolute -left-5 top-3 h-5 w-5 text-kunyit" />
            <x-marketing.doodle type="sparkle" class="absolute -right-4 bottom-6 h-4 w-4 text-sambal-500" />
        </div>
        <h2 data-reveal class="mt-4 font-display text-[clamp(3.6rem,10vw,8rem)] font-bold uppercase leading-[0.82] tracking-tight" style="--i: 1">
            <x-marketing.lang en="Come on, stop scrolling.">Jom, stop scrolling.</x-marketing.lang><br><span class="text-sambal-600"><x-marketing.lang en="Start eating.">Start makan.</x-marketing.lang></span>
        </h2>
        <p data-reveal class="mx-auto mt-6 max-w-md text-lg text-ink/75" style="--i: 2">Deciding what to eat shouldn't be the hardest part of your day.</p>

        <div data-reveal class="relative mt-12 inline-flex flex-col items-center gap-4" style="--i: 3">
            <x-marketing.doodle type="arrow-loop" class="draw absolute -left-40 -top-16 hidden h-24 w-36 text-ink/80 sm:block" style="--draw-delay: 500ms; --draw-dur: 1300ms" />
            <x-marketing.download-button from="final" />
            <p class="font-display text-2xl font-bold uppercase tracking-wide text-ink/70">Free · iPhone · Public beta</p>
        </div>
    </section>

@endsection

{{-- Structured data: the app itself, and the FAQ above as a FAQPage. Answers are the English text
     (the Manglish variant is dropped and tags stripped); JSON_HEX_TAG keeps it inside <script>. --}}
@push('meta')
    @php
        $plainAnswer = fn (string $html) => trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<span class="lang-ms">.*?</span>#s', '', $html))));
        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'MobileApplication',
                'name' => 'MakanApa',
                'operatingSystem' => 'iOS',
                'applicationCategory' => 'LifestyleApplication',
                'description' => 'Tell MakanApa your mood, budget and how far you\'ll go. It picks one place nearby.',
                'url' => url('/'),
                'image' => asset('images/og.png'),
                'inLanguage' => 'en-MY',
                'author' => ['@type' => 'Person', 'name' => 'Hakeemi Ridza'],
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'MYR'],
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

        // "Try it": asks /try for one real pick, spins the reel while it waits (at least long enough
        // to read as a spin), and "Cari lagi" re-asks while skipping what's already been shown.
        (function () {
            var form = document.getElementById('try-form');
            if (!form) return;
            var panel = document.querySelector('.try-panel');
            var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
            var minSpin = calm ? 0 : 1100;
            var shown = [];

            function field(name) { return panel.querySelector('[data-field="' + name + '"]'); }

            function settle(started) {
                return new Promise(function (resolve) {
                    setTimeout(resolve, Math.max(0, minSpin - (performance.now() - started)));
                });
            }

            function render(data) {
                var pick = data.pick;
                if (!pick) {
                    panel.querySelector('[data-variant="first"]').hidden = shown.length > 0;
                    panel.querySelector('[data-variant="more"]').hidden = shown.length === 0;
                    panel.dataset.show = 'none';
                    return;
                }

                shown.push(pick.id);
                field('name').textContent = pick.name;
                field('headline').textContent = pick.headline;
                field('url').href = pick.url;

                var facts = field('facts');
                facts.replaceChildren();
                [pick.distanceKm.toFixed(1) + ' km', pick.price, pick.rating ? '★ ' + pick.rating.toFixed(1) : null, pick.halal]
                    .filter(Boolean)
                    .forEach(function (text) {
                        var fact = document.createElement('li');
                        fact.className = 'rounded-full border border-ink/15 bg-paper px-3 py-1 text-sm font-medium text-ink/80';
                        fact.textContent = text;
                        facts.appendChild(fact);
                    });

                panel.dataset.show = 'pick';
            }

            function ask(reroll) {
                if (!reroll) shown = [];
                var answers = new FormData(form);
                var params = new URLSearchParams({ km: answers.get('km') || '2' });
                if (answers.get('mood')) params.set('mood', answers.get('mood'));
                if (answers.get('budget')) params.set('budget', answers.get('budget'));
                shown.slice(-20).forEach(function (id) { params.append('exclude[]', id); });

                panel.dataset.show = 'spinning';
                if (panel.getBoundingClientRect().top > window.innerHeight - 120) {
                    panel.scrollIntoView({ block: 'center', behavior: calm ? 'auto' : 'smooth' });
                }

                var started = performance.now();
                fetch(form.action + '?' + params, { headers: { Accept: 'application/json' } })
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function (data) { return settle(started).then(function () { render(data); }); })
                    .catch(function () { return settle(started).then(function () { panel.dataset.show = 'error'; }); });
            }

            form.addEventListener('submit', function (event) { event.preventDefault(); ask(false); });
            form.addEventListener('change', function () { shown = []; });
            panel.querySelector('[data-reroll]').addEventListener('click', function () { ask(true); });
        })();
    </script>
@endpush
