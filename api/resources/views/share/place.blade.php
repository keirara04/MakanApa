@extends('layouts.marketing')

@section('title', $restaurant->name.($restaurant->address ? ' · '.$restaurant->address : '').' | MakanApa')
@section('description', $description)
@section('og_image', $ogImage)
@section('og_image_alt', $restaurant->name.' on MakanApa')

@push('meta')
    @unless ($indexable)
        <meta name="robots" content="noindex">
    @endunless
@endpush

@section('content')
    @php
        $halalClasses = match ($halal['tone']) {
            'certified', 'friendly' => 'bg-pandan/10 text-pandan-700',
            'non_halal' => 'bg-ink/10 text-ink',
            'warning' => 'bg-kunyit/20 text-ink',
            default => 'bg-ink/5 text-ink/70',
        };
    @endphp

    <article class="mx-auto max-w-xl">
        <p class="text-sm font-medium text-sambal-600">Someone sent you a makan spot 🍛</p>

        <h1 class="mt-2 text-3xl font-semibold leading-tight">{{ $restaurant->name }}</h1>

        @if ($restaurant->address || $category)
            <p class="mt-2 text-ink/70">{{ collect([$category, $restaurant->address])->filter()->implode(' · ') }}</p>
        @endif

        <ul class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="At a glance">
            @if ($openStatus === 'open')
                <li class="rounded-full bg-pandan/10 px-3 py-1 font-medium text-pandan-700">Open now{{ $closesAt ? ' · closes '.$closesAt : '' }}</li>
            @elseif ($openStatus === 'closed')
                <li class="rounded-full bg-sambal-50 px-3 py-1 font-medium text-sambal-700">Closed now</li>
            @endif
            {{-- HalalPresenter's own wording — never shortened to "Halal". --}}
            <li class="rounded-full px-3 py-1 font-medium {{ $halalClasses }}" title="{{ $halal['longLabel'] }}">{{ $halal['shortLabel'] }}</li>
            @if ($price)
                <li class="rounded-full bg-ink/5 px-3 py-1">{{ $price }}</li>
            @endif
            @if ($menuRange)
                <li class="rounded-full bg-ink/5 px-3 py-1">Menu {{ $menuRange }}</li>
            @endif
        </ul>

        @if ($pickers)
            <p class="mt-4 text-sm text-ink/70">Picked by {{ $pickers }} people on MakanApa this month.</p>
        @endif

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="{{ $directionsUrl }}" rel="noopener" class="inline-flex min-h-12 items-center justify-center rounded-full bg-sambal-600 px-6 py-3 font-semibold text-white hover:bg-sambal-700">
                Get directions
            </a>
            <a href="{{ $openAppUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-sambal-200 bg-white px-6 py-3 font-semibold text-ink hover:border-sambal-300">
                Open in MakanApa
            </a>
        </div>

        <section class="mt-10 rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Can't decide where to makan?</h2>
            <p class="mt-2 text-ink/70">MakanApa picks a spot near you in seconds — halal-aware, budget-aware, no scrolling.</p>
            <a href="{{ $getAppUrl }}" class="mt-4 inline-flex min-h-11 items-center rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-white hover:bg-ink/90">
                {{ config('marketing.app_download_label') }}
            </a>
        </section>

        @if ($restaurant->provider === 'google')
            <p class="mt-6 text-xs text-ink/70">Place data © Google.</p>
        @endif
    </article>
@endsection
