@extends('layouts.marketing')

@section('title', 'MakanApa | What to Eat')
@section('description', 'Makan apa hari ni? Tell MakanApa your mood, budget and how far you\'ll go. It picks one place nearby. Free public beta on iPhone.')
@section('main_class', '')

@push('nav_extra')
    <x-marketing.lang-toggle />
@endpush

@section('content')

    {{-- HERO ------------------------------------------------------------------------------ --}}
    <section class="relative z-10 overflow-x-clip">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-full bg-gradient-to-b from-sambal-50 to-cream" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 pt-10 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:pt-14">
            <div class="text-center lg:pb-24 lg:text-left">
                <p class="inline-flex items-center gap-2 rounded-full border border-sambal-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wider text-sambal-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-sambal-600" aria-hidden="true"></span>
                    Public beta
                </p>

                <h1 class="mt-5 text-[2.6rem] font-bold leading-[1.02] tracking-tight text-balance sm:text-6xl lg:text-[3.6rem]">
                    <x-marketing.lang en="What should I eat today?">Makan apa hari ni?</x-marketing.lang>
                    <span class="block text-sambal-600">Decided in seconds.</span>
                </h1>

                <p class="mx-auto mt-5 max-w-md text-lg text-ink/75 lg:mx-0">
                    <x-marketing.lang en="Tell us your mood, budget and how far you're willing to go. MakanApa picks one. Not feeling it? Just reroll.">Tell us your mood, budget and how far malas nak jalan. MakanApa picks one. Kalau tak ngam, reroll je.</x-marketing.lang>
                </p>

                <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center lg:justify-start">
                    <x-marketing.download-button from="hero" />
                    <div class="hidden items-center gap-3 rounded-2xl border border-sambal-100 bg-white p-2 pr-4 shadow-clay lg:flex">
                        <img src="{{ asset('images/qr-testflight.svg') }}" alt="QR code to join the MakanApa beta on TestFlight"
                             width="60" height="60" class="h-15 w-15">
                        <p class="text-sm font-semibold leading-tight">Scan with<br>your iPhone</p>
                    </div>
                </div>

                <p class="mt-4 text-sm font-medium text-ink/80">Free · iPhone · Public beta</p>
            </div>

            {{-- Mascot + tilted phone + the three inputs as hand-lettered stickers. The phone hangs
                 over the dark band below on desktop. --}}
            <div class="relative mx-auto h-[30rem] w-full max-w-[34rem] sm:h-[34rem] lg:-mb-28">
                <div class="absolute left-[30%] top-0 w-52 rotate-[4deg] sm:left-[34%] sm:w-60">
                    <x-marketing.phone screen="home" eager
                                       alt="MakanApa home screen asking “Hungry? Okay, what we doing today?” with a Solo pick button" />
                </div>

                <img src="{{ asset('images/mascot-default.svg') }}" alt="" aria-hidden="true" width="240" height="240"
                     class="animate-bob absolute bottom-10 left-0 w-44 drop-shadow-xl sm:bottom-6 sm:w-60">
                <x-marketing.doodle type="burst" class="absolute left-[4%] top-[34%] h-10 w-10 -rotate-12 text-kunyit" />

                {{-- Thought bubble --}}
                <div aria-hidden="true" class="absolute left-[2%] top-[8%] -rotate-[10deg] rounded-[1.75rem] rounded-br-md border-2 border-ink/80 bg-white px-4 py-2 font-hand text-2xl leading-6 shadow-clay sm:text-3xl sm:leading-7">
                    <x-marketing.lang>Nasi?<x-slot:en>Rice?</x-slot:en></x-marketing.lang><br>Korean?<br><span class="text-sambal-600"><x-marketing.lang en="Anything's fine!">Anything lah!</x-marketing.lang></span>
                </div>

                <x-marketing.sticker class="absolute right-0 top-[6%] rotate-[4deg]">&lt; RM20</x-marketing.sticker>
                <x-marketing.sticker tone="sambal" class="absolute -right-1 top-[28%] rotate-[10deg]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                    <x-marketing.lang en="Nearby">Dekat je</x-marketing.lang>
                </x-marketing.sticker>
                <x-marketing.sticker class="absolute right-2 top-[50%] -rotate-[6deg] max-sm:hidden"><x-marketing.lang en="As long as it's good!">Janji sedap!</x-marketing.lang></x-marketing.sticker>
                <x-marketing.doodle type="burst" class="absolute right-[26%] top-[46%] h-8 w-8 rotate-45 text-sambal-500 max-sm:hidden" />
            </div>
        </div>
    </section>

    {{-- PROBLEM BAND ---------------------------------------------------------------------- --}}
    <section class="px-3">
        <div class="relative mx-auto grid max-w-[90rem] items-center gap-6 overflow-hidden rounded-[2.5rem] bg-ink px-6 pb-12 pt-10 text-cream sm:px-10 lg:grid-cols-[22rem_1fr] lg:pb-14 lg:pt-36">
            {{-- Mascot drowning in food-app tabs (generic tiles, no real brands). --}}
            <div class="relative mx-auto h-56 w-72 lg:-mt-24" aria-hidden="true">
                <div class="absolute left-2 top-16 flex h-16 w-16 -rotate-[18deg] items-center justify-center rounded-2xl bg-sambal-500 shadow-lg">
                    <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/></svg>
                </div>
                <div class="absolute right-4 top-2 flex h-16 w-16 rotate-[14deg] items-center justify-center rounded-2xl bg-pandan shadow-lg">
                    <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h2"/></svg>
                </div>
                <div class="absolute bottom-10 right-0 flex h-14 w-14 rotate-[24deg] items-center justify-center rounded-2xl bg-kunyit shadow-lg">
                    <svg class="h-7 w-7 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 17.5v-11"/></svg>
                </div>
                <div class="absolute left-10 top-0 flex h-12 w-12 -rotate-6 items-center justify-center rounded-full border-2 border-cream/40">
                    <svg class="h-6 w-6 text-cream/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 9a3 3 0 1 1 4 2.8c-.6.3-1 .9-1 1.6V15M12 18h.01"/></svg>
                </div>
                <div class="absolute bottom-0 left-1/2 h-44 w-44 -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(253_246_236/0.9)_55%,rgb(253_246_236/0))]"></div>
                <img src="{{ asset('images/mascot-sad.svg') }}" alt="" width="176" height="176"
                     class="absolute bottom-0 left-1/2 h-44 w-44 -translate-x-1/2 drop-shadow-[0_10px_18px_rgba(0,0,0,0.35)]">
            </div>

            <div class="text-center lg:text-left">
                <p class="text-3xl font-bold leading-tight tracking-tight text-balance sm:text-5xl">
                    <x-marketing.lang en="20 minutes scrolling Grab.">Scroll Grab 20 minit.</x-marketing.lang><br>
                    <span class="text-sambal-300"><x-marketing.lang en="Still no idea what to eat?">Still tak tahu nak makan apa?</x-marketing.lang></span>
                </p>
                <p class="mt-4 text-lg text-cream/80">That's literally why we built MakanApa.</p>
            </div>

            <x-marketing.doodle type="squiggle" class="absolute bottom-8 right-8 h-10 w-16 text-cream/40 max-lg:hidden" />
        </div>
    </section>

    {{-- HOW IT WORKS ---------------------------------------------------------------------- --}}
    <section id="how-it-works" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
        <p class="text-center text-xs font-semibold uppercase tracking-wider text-sambal-700">How it works</p>
        <h2 class="mt-2 text-center text-3xl font-bold tracking-tight sm:text-4xl">Three questions. One answer.</h2>

        @php
            $steps = [
                // [illustration, title [ms, en], body [ms, en], options (label or [ms, en]), picked option]
                ['mood-spicy', ['Apa vibe?', "What's the vibe?"], ['Nasi? Pedas? Something light? Anything also can.', 'Rice? Spicy? Something light? Anything works.'], ['Nasi Kandar', 'Ayam Gepuk', 'Nasi Lemak', 'Mee Goreng'], 'Nasi Kandar'],
                ['budget-normal', ['Budget macam mana?', "What's the budget?"], ['Save sikit, normal lah, or treat yourself.', 'Save a little, keep it normal, or treat yourself.'], ['~RM10', '~RM20', '~RM35+', ['Anything lah', 'Anything']], '~RM20'],
                ['distance-walk', ['Jauh boleh?', 'How far can you go?'], ['Dekat je, okay lah, or janji sedap.', 'Close by, a bit further, or anywhere worth it.'], ['Within 1 km', 'Within 2 km', 'Within 5 km'], 'Within 2 km'],
            ];
        @endphp

        <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $i => [$art, $title, $body, $options, $picked])
                <li class="flex flex-col rounded-3xl border border-sambal-100 bg-white p-5 shadow-clay">
                    <div class="flex items-start justify-between">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-sambal-50 text-sm font-bold text-sambal-700">{{ $i + 1 }}</span>
                        <img src="{{ asset("images/illustrations/{$art}.svg") }}" alt="" aria-hidden="true" width="56" height="56" class="h-14 w-14">
                    </div>
                    <h3 class="mt-3 text-lg font-bold"><x-marketing.lang :en="$title[1]">{{ $title[0] }}</x-marketing.lang></h3>
                    <p class="mt-1 text-sm text-ink/75"><x-marketing.lang :en="$body[1]">{{ $body[0] }}</x-marketing.lang></p>
                    <ul class="mt-4 flex flex-wrap gap-2" aria-hidden="true">
                        @foreach ($options as $option)
                            <li @class([
                                'rounded-full px-3 py-1 text-xs font-semibold',
                                'bg-sambal-600 text-white' => $option === $picked,
                                'border border-sambal-100 bg-cream text-ink/80' => $option !== $picked,
                            ])>
                                @if (is_array($option))
                                    <x-marketing.lang :en="$option[1]">{{ $option[0] }}</x-marketing.lang>
                                @else
                                    {{ $option }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach

            <li class="relative flex min-h-56 flex-col overflow-hidden rounded-3xl bg-gradient-to-br from-sambal-500 to-sambal-700 p-5 text-white shadow-clay">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold">4</span>
                <p class="mt-3 text-3xl font-bold tracking-tight">Done.</p>
                <p class="mt-1 max-w-[10rem] text-white">We pick one. You stop deciding.</p>
                <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" aria-hidden="true" width="140" height="140" loading="lazy"
                     class="absolute -bottom-3 -right-3 h-32 w-32 drop-shadow-lg">
                <x-marketing.doodle type="sparkle" class="absolute right-24 top-10 h-4 w-4 text-kunyit" />
            </li>
        </ol>
    </section>

    {{-- THE REAL APP ---------------------------------------------------------------------- --}}
    <section id="app" class="bg-sambal-50/70 py-14 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-wider text-sambal-700">The real app</p>
            <h2 class="mx-auto mt-2 max-w-2xl text-center text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                <x-marketing.lang en="From “what should we eat?” to settled in a few taps.">From “entah nak makan apa” to settled in a few taps.</x-marketing.lang>
            </h2>
        </div>

        <ol class="mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-4 [scrollbar-width:thin] sm:px-6 lg:mx-auto lg:grid lg:max-w-6xl lg:grid-cols-5 lg:gap-6 lg:overflow-visible"
            tabindex="0" aria-label="App screenshots, in order">
            @foreach ([
                ['mood', '1. Pick a mood', 'Quick and simple.', 'Mood step with options like Nasi Kandar, Ayam Gepuk and Nasi Padang'],
                ['budget', '2. Set a budget', 'From save to treat yourself.', 'Budget step with ~RM10 save sikit, ~RM20 normal lah and ~RM35+ feeling kaya'],
                ['distance', '3. How far?', 'Stay nearby or go a little further.', 'Distance step with within 1 km dekat je, 2 km okay lah and 5 km janji sedap'],
                ['result', '4. Get your pick', 'One answer, not a list.', 'Result screen: MakanApa says Nasi Kandar Haji Basheer, settled, with price, distance and why'],
                ['nearby', '5. Or see what’s around', 'Explore the map.', 'Nearby map with top-rated places and community finds'],
            ] as [$screen, $caption, $sub, $alt])
                <li class="w-[62vw] max-w-[14rem] shrink-0 snap-center text-center lg:w-auto lg:max-w-none">
                    <x-marketing.phone :screen="$screen" :alt="$alt" class="rounded-[2rem] p-1.5" />
                    <p class="mt-4 font-bold">{{ $caption }}</p>
                    <p class="text-sm text-ink/70">{{ $sub }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- WHAT MAKES IT DIFFERENT ----------------------------------------------------------- --}}
    <section id="features" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
        <p class="text-center text-xs font-semibold uppercase tracking-wider text-sambal-700">What makes MakanApa different</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Reroll --}}
            <article class="flex flex-col overflow-hidden rounded-3xl border border-sambal-100 bg-white p-5 shadow-clay">
                <div class="flex items-start gap-3">
                    <img src="{{ asset('images/illustrations/mood-quick.svg') }}" alt="" aria-hidden="true" width="44" height="44" class="h-11 w-11 shrink-0">
                    <div>
                        <h3 class="text-lg font-bold">Reroll</h3>
                        <p class="mt-1 text-sm text-ink/75"><x-marketing.lang en="Not feeling it? Next one. Same preferences, different spot.">Tak ngam? Next one. Same preferences, different spot.</x-marketing.lang></p>
                    </div>
                </div>
                <div class="mt-auto flex items-end gap-3 pt-5" aria-hidden="true">
                    <div class="-mb-10 w-20 shrink-0 rotate-[-4deg] rounded-2xl bg-ink p-1 shadow-phone">
                        <img src="{{ asset('images/screens/result-360.webp') }}" alt="" width="360" height="783" loading="lazy" class="rounded-xl">
                    </div>
                    <div class="mb-2 flex-1 rounded-2xl border border-sambal-100 bg-cream p-3 shadow-clay">
                        <p class="text-sm font-semibold">Not feeling it?</p>
                        <p class="mt-2 inline-block rounded-full bg-sambal-600 px-3 py-1 text-xs font-bold text-white"><x-marketing.lang en="Find another">Cari lagi!</x-marketing.lang></p>
                    </div>
                </div>
            </article>

            {{-- Halal --}}
            <article class="flex flex-col rounded-3xl border border-sambal-100 bg-white p-5 shadow-clay">
                <div class="flex items-start gap-3">
                    <svg class="h-11 w-11 shrink-0 text-sambal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                    </svg>
                    <div>
                        <h3 class="text-lg font-bold">Halal info you can understand</h3>
                        <p class="mt-1 text-sm text-ink/75">See Halal certified, Muslim-friendly and places we haven't verified yet. No guessing.</p>
                    </div>
                </div>
                <ul class="mt-auto flex flex-wrap gap-2 pt-5 text-xs font-semibold">
                    <li class="inline-flex items-center gap-1 rounded-full bg-pandan/15 px-2.5 py-1 text-pandan-700">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Halal certified
                    </li>
                    <li class="rounded-full bg-pandan/10 px-2.5 py-1 text-pandan-700">Muslim-friendly</li>
                    <li class="rounded-full bg-ink/5 px-2.5 py-1 text-ink/75">Not verified yet</li>
                </ul>
            </article>

            {{-- Community --}}
            <article class="flex flex-col rounded-3xl border border-sambal-100 bg-white p-5 shadow-clay">
                <div class="flex items-start gap-3">
                    <img src="{{ asset('images/illustrations/location-map.svg') }}" alt="" aria-hidden="true" width="44" height="44" class="h-11 w-11 shrink-0">
                    <div>
                        <h3 class="text-lg font-bold">Community picks</h3>
                        <p class="mt-1 text-sm text-ink/75">See what people around you are actually picking.</p>
                    </div>
                </div>
                <div class="mt-auto pt-5" aria-hidden="true">
                    <div class="flex items-center gap-3 rounded-2xl border border-sambal-100 bg-cream p-3 shadow-clay">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sambal-600 text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 7 13.5 15.5 8.5 10.5 2 17"/><path d="M16 7h6v6"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-wider text-ink/70">Trending near you</p>
                            <p class="truncate text-sm font-bold">Top rated nearby</p>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Geng --}}
            <article class="relative flex flex-col overflow-hidden rounded-3xl border border-sambal-100 bg-white p-5 shadow-clay">
                <div class="flex items-start gap-3">
                    <img src="{{ asset('images/illustrations/geng-group.svg') }}" alt="" aria-hidden="true" width="44" height="44" class="h-11 w-11 shrink-0">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-bold">Geng mode</h3>
                            <span class="rounded-full bg-kunyit/40 px-2 py-0.5 text-[0.7rem] font-bold text-ink">Coming soon</span>
                        </div>
                        <p class="mt-1 text-sm text-ink/75">Everyone votes, one place wins. Still cooking.</p>
                    </div>
                </div>
                <img src="{{ asset('images/mascot-geng.svg') }}" alt="" aria-hidden="true" width="790" height="530" loading="lazy"
                     class="mx-auto mt-auto w-56 pt-3">
            </article>
        </div>
    </section>

    {{-- MADE FOR MALAYSIA ----------------------------------------------------------------- --}}
    <section class="overflow-x-clip bg-sambal-50/60">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[0.8fr_1.2fr]">
            <div class="text-center lg:text-left">
                <h2 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl">Made for the way we actually choose food.</h2>
                <p class="mx-auto mt-4 max-w-md text-lg text-ink/75 lg:mx-0">
                    MakanApa understands the question because we've all had the same conversation.
                </p>
            </div>

            <div class="relative mx-auto flex w-full max-w-xl flex-wrap justify-center gap-3 sm:block sm:h-72" role="list" aria-label="Things we all say about food">
                @foreach ([
                    [['“Dekat je.”', '“Somewhere close.”'], 'sm:left-0 sm:top-6 -rotate-6'],
                    [['“Bawah RM20.”', '“Under RM20.”'], 'sm:left-[26%] sm:top-0 rotate-3'],
                    [['“Janji sedap.”', '“As long as it’s good.”'], 'sm:right-[14%] sm:top-8 -rotate-3'],
                    [['“Pedas sikit boleh.”', '“A little spicy is fine.”'], 'sm:right-0 sm:top-32 rotate-6'],
                    [['“Anything lah.”', '“Anything’s fine.”'], 'sm:left-[4%] sm:bottom-6 rotate-2'],
                ] as [$quote, $pos])
                    <p role="listitem" class="sm:absolute {{ $pos }} rounded-[1.5rem] rounded-bl-md border-2 border-sambal-500 bg-white px-4 py-1.5 font-hand text-2xl text-ink shadow-clay sm:text-3xl"><x-marketing.lang :en="$quote[1]">{{ $quote[0] }}</x-marketing.lang></p>
                @endforeach
                <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" aria-hidden="true" width="128" height="128" loading="lazy"
                     class="mx-auto h-28 w-28 sm:absolute sm:bottom-0 sm:left-1/2 sm:h-32 sm:w-32 sm:-translate-x-1/2">
                <x-marketing.doodle type="burst" class="absolute bottom-24 left-[36%] h-8 w-8 -rotate-12 text-sambal-500 max-sm:hidden" />
            </div>
        </div>
    </section>

    {{-- FAQ ------------------------------------------------------------------------------- --}}
    <section id="faq" class="mx-auto grid max-w-6xl gap-6 px-4 py-12 sm:px-6 lg:grid-cols-[10rem_1fr_1fr] lg:gap-8">
        <h2 class="text-xs font-bold uppercase tracking-wider text-sambal-700 lg:pt-3">Okay but…</h2>

        @php
            $summary = 'flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 py-3 font-semibold marker:content-none [&::-webkit-details-marker]:hidden';
            $plus = 'text-xl text-sambal-600 transition-transform group-open:rotate-45';
            $answer = 'pb-4 text-sm text-ink/75';
            $faqs = [
                [
                    ['Why TestFlight?', 'MakanApa is still in public beta, so the iPhone app is shared through Apple\'s TestFlight app. Tap the button, install TestFlight from the App Store if you don\'t have it, then tap <strong>Accept</strong> and <strong>Install</strong> for MakanApa. You might find a bug or two. <span class="lang-ms">Kalau jumpa, <a href="'.url('/support').'" class="font-medium text-sambal-700 underline">bagitahu us</a>.</span><span class="lang-en">If you find one, <a href="'.url('/support').'" class="font-medium text-sambal-700 underline">let us know</a>.</span>'],
                    ['Is it free?', 'Yes, joining the beta is free.'],
                    ['Which areas does MakanApa work in?', 'MakanApa finds places around wherever you are, so it works anywhere there are restaurants nearby. It\'s built in Malaysia, with Malaysian food in mind.'],
                ],
                [
                    ['Is there an Android version?', 'Not yet. MakanApa is iPhone only for now.'],
                    ['Does MakanApa keep my location?', 'Your location is used to find places near you and work out distance. It isn\'t stored as part of your account profile and is never shown publicly. <a href="'.url('/privacy#location').'" class="font-medium text-sambal-700 underline">Read the privacy policy</a>.'],
                    ['How does halal info work?', 'Each place shows what we actually know: <strong>Halal certified</strong>, <strong>Muslim-friendly</strong> (not certified), or <strong>not verified</strong> yet. We don\'t label a place halal without a certificate, and you can help verify places from the app.'],
                ],
            ];
        @endphp

        @foreach ($faqs as $column)
            <div class="divide-y divide-sambal-100 border-y border-sambal-100">
                @foreach ($column as [$question, $html])
                    <details class="group">
                        <summary class="{{ $summary }}">{{ $question }} <span class="{{ $plus }}" aria-hidden="true">+</span></summary>
                        <p class="{{ $answer }}">{!! $html !!}</p>
                    </details>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- FINAL CTA ------------------------------------------------------------------------- --}}
    <section class="px-4 pb-4 sm:px-6">
        <div class="relative mx-auto grid max-w-6xl items-center gap-6 overflow-hidden rounded-[2rem] border border-sambal-100 bg-gradient-to-r from-sambal-50 via-white to-cream-100 px-6 py-8 text-center shadow-clay lg:grid-cols-[auto_1fr_1fr_auto] lg:gap-8 lg:text-left">
            <div class="relative mx-auto h-28 w-28" aria-hidden="true">
                <img src="{{ asset('images/mascot-celebrate.svg') }}" alt="" width="112" height="112" class="animate-bob h-28 w-28">
                <x-marketing.doodle type="sparkle" class="animate-twinkle absolute -left-3 top-2 h-5 w-5 text-kunyit" />
                <x-marketing.doodle type="sparkle" class="animate-twinkle absolute -right-2 bottom-4 h-4 w-4 text-sambal-500" />
            </div>
            <h2 class="text-3xl font-bold leading-tight tracking-tight lg:text-[2rem]">
                <x-marketing.lang en="Come on, stop scrolling.">Jom, stop scrolling.</x-marketing.lang><br><span class="text-sambal-600"><x-marketing.lang en="Start eating.">Start makan.</x-marketing.lang></span>
            </h2>
            <p class="text-ink/75">Deciding what to eat shouldn't be the hardest part of your day.</p>
            <div class="flex flex-col items-center gap-2">
                <x-marketing.download-button from="final" />
                <p class="text-sm font-medium text-ink/80">Free · iPhone · Public beta</p>
            </div>
        </div>
    </section>

@endsection

@push('styles')
<style>
    @keyframes bob {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    @keyframes twinkle {
        0%, 100% { transform: scale(1) rotate(0deg); opacity: .9; }
        50% { transform: scale(.7) rotate(20deg); opacity: .5; }
    }
    .animate-bob { animation: bob 3.5s ease-in-out infinite; }
    .animate-twinkle { animation: twinkle 3s ease-in-out infinite; }

    @media (prefers-reduced-motion: reduce) {
        .animate-bob, .animate-twinkle { animation: none; }
    }
</style>
@endpush
