@extends('layouts.marketing')

@section('title', 'Become a MakanApa Ambassador')
@section('description', 'Represent your campus or neighbourhood on MakanApa: hand-pick the best places to eat, and everyone in your community sees your picks. Apply in the app.')
@section('main_class', '')

@php
    $campusName = $campus ? $campuses[$campus] : null;

    // What ambassadors do. Not a sequence, so no numbers.
    $duties = [
        ['Hand-pick the places', 'Add spots to your ambassador picks, with a note on what to order. Everyone in your community sees them in Community.'],
        ['Fill in the gaps', "Add the places that aren't on MakanApa yet, and fix the ones that are wrong or closed."],
        ['Keep halal info honest', "Send certificate photos and halal reports, so people know what's actually verified."],
        ['Spread the word', 'Share your ambassador card to your Story or feed, and point people to MakanApa.'],
    ];

    // How to apply, in order.
    $steps = [
        ['Get MakanApa', 'Free on iPhone.'],
        ['Join your community', "Pick your university or area in the Community tab. Can't find it? Request it there."],
        ['Apply', 'Tap Become an ambassador and tell us, in a few lines, how well you know the food around you.'],
        ['Hear back in the app', "We read every application. You'll get a notification when it's decided."],
    ];
@endphp

@section('content')

    {{-- HERO --------------------------------------------------------------------------------- --}}
    <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-20 pt-14 sm:px-6 lg:grid-cols-[1.5fr_0.7fr] lg:pt-20">
        <div>
            @if ($campusName)
                <p class="hero-in font-hand text-3xl text-sambal-700" style="--i: 0">{{ $campusName }} has a MakanApa ambassador.</p>
            @endif
            <h1 class="hero-in type-hero hero-title mt-2 max-w-3xl" style="--i: 1">Be the one your campus asks about food.</h1>
            <p class="hero-in mt-6 max-w-xl text-lg leading-relaxed text-ink/75 sm:text-xl" style="--i: 2">
                MakanApa ambassadors hand-pick the best places to eat around their university or neighbourhood, and everyone in their community sees those picks.
            </p>
            <div class="hero-in mt-8 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-6" style="--i: 3">
                <x-marketing.app-store-badge from="ambassadors" :campus="$campus" class="-ml-3.5" />
                <p class="font-display text-lg font-medium text-ink/70">Then apply from the Community tab.</p>
            </div>
        </div>

        <figure class="hero-in relative mx-auto w-full max-w-xs" style="--i: 2">
            <img src="{{ asset('images/ambassador-crest-720.webp') }}" alt="MakanApa ambassador crest: the MakanApa rice-ball mascot in a red cap holding a spoon"
                 width="720" height="720" fetchpriority="high" class="w-full drop-shadow-[0_16px_18px_rgba(43,28,20,0.2)]">
        </figure>
    </section>

    {{-- WHAT AMBASSADORS DO ------------------------------------------------------------------ --}}
    <section class="border-y border-ink/10 bg-paper-50/60">
        <div class="mx-auto max-w-6xl px-5 py-24 sm:px-6">
            <h2 class="type-section max-w-2xl">What ambassadors do</h2>
            <dl class="mt-14 grid gap-x-10 gap-y-10 sm:grid-cols-2">
                @foreach ($duties as [$title, $detail])
                    <div class="border-t-2 border-ink/80 pt-4">
                        <dt class="type-card">{{ $title }}</dt>
                        <dd class="mt-2 max-w-md text-lg text-ink/75">{{ $detail }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- WHAT YOU GET: the share card, shown, not described. ------------------------------------ --}}
    <section class="mx-auto grid max-w-6xl items-center gap-14 px-5 py-24 sm:px-6 lg:grid-cols-[0.9fr_1.1fr]">
        <figure class="relative mx-auto w-full max-w-sm">
            <div class="sketch -rotate-2 bg-paper-50 p-6 text-center" style="--sketch-radius: 18px">
                <span class="tape -top-3 left-1/2 -translate-x-1/2 rotate-2" aria-hidden="true"></span>
                <img src="{{ asset('images/ambassador-crest-320.webp') }}" alt="" aria-hidden="true" width="320" height="320" loading="lazy" class="mx-auto w-48">
                <p class="mt-2 text-sm font-medium text-ink/70">MakanApa ambassador for</p>
                <p class="font-display text-2xl font-bold">{{ $campusName ?? 'Your campus' }}</p>
                <p class="mt-3 text-ink/75">“Can't decide what to eat? Ask me, or let MakanApa pick.”</p>
            </div>
            <figcaption class="mt-6 text-center font-hand text-2xl text-ink/70">Your card, sized for Story or Post.</figcaption>
        </figure>

        <div>
            <h2 class="type-section">What you get</h2>
            <ul class="mt-8 space-y-5 text-lg">
                @foreach ([
                    'An Ambassador badge on everything you post in Community.',
                    'Your picks, front and centre for everyone in your community.',
                    'Your own ambassador card to share.',
                ] as $perk)
                    <li class="flex gap-4">
                        <x-marketing.doodle type="sparkle" class="mt-1.5 h-4 w-4 shrink-0 text-sambal-600" />
                        <span>{{ $perk }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- HOW TO APPLY: a real sequence, so it's numbered. ---------------------------------------- --}}
    <section class="bg-ink text-paper">
        <div class="mx-auto max-w-6xl px-5 py-24 sm:px-6">
            <h2 class="type-section">How to apply</h2>
            <ol class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as $i => [$title, $detail])
                    <li>
                        <span class="relative flex h-12 w-12 items-center justify-center font-display text-2xl font-bold">
                            {{ $i + 1 }}
                            <x-marketing.doodle type="circle" class="draw absolute inset-0 h-full w-full text-sambal-300" style="--draw-delay: {{ 200 + $i * 150 }}ms" />
                        </span>
                        <h3 class="type-card mt-4">{{ $title }}</h3>
                        <p class="mt-2 text-paper/75">{{ $detail }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- LIVE CAMPUSES + CTA ---------------------------------------------------------------------- --}}
    <section class="mx-auto max-w-5xl px-5 pb-16 pt-24 text-center sm:px-6">
        <p class="text-sm font-medium text-ink/70">Ambassadors are live at</p>
        <ul class="mt-4 flex flex-wrap justify-center gap-3">
            @foreach ($campuses as $slug => $name)
                <li @class([
                    'rounded-md border-[3px] px-3 py-1 font-display text-xl font-bold',
                    $loop->odd ? '-rotate-2' : 'rotate-1',
                    'border-sambal-600 text-sambal-600' => $slug === $campus,
                    'border-ink/80' => $slug !== $campus,
                ])>{{ $name }}</li>
            @endforeach
        </ul>

        <h2 class="type-hero mx-auto mt-14 max-w-3xl">Your campus could be next.</h2>
        <p class="mx-auto mt-6 max-w-md text-lg text-ink/75">Not on the list? That's the point. Apply, and be the first.</p>
        <div class="mt-10 flex flex-col items-center gap-3">
            <x-marketing.app-store-badge from="ambassadors" :campus="$campus" />
            <p class="font-display text-lg font-medium text-ink/70">Free on iPhone</p>
        </div>
    </section>

@endsection
