@extends('layouts.marketing')

@section('title', $restaurant->name.($restaurant->address ? ' · '.$restaurant->address : '').' | MakanApa')
@section('description', $description)
@section('og_image', $ogImage)
@section('og_image_alt', $restaurant->name.' on MakanApa')
@section('app_argument', $canonicalUrl)

@push('meta')
    @unless ($indexable)
        <meta name="robots" content="noindex">
    @endunless
    @if ($structuredData)
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
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

        {{-- Someone with the app is taken straight into it by the universal link, so a visitor here
             most likely doesn't have it yet: the App Store badge leads, "open" is the fallback. --}}
        <div class="mt-8 flex flex-col items-center gap-1 sm:flex-row sm:gap-3">
            <a href="{{ $directionsUrl }}" rel="noopener" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-sambal-600 px-6 py-3 font-semibold text-white hover:bg-sambal-700 sm:w-auto">
                Get directions
            </a>
            <x-marketing.app-store-badge :href="$getAppUrl" size="md" />
        </div>
        <p class="mt-1 text-center text-sm text-ink/70 sm:text-left">
            Already have MakanApa? <a href="{{ $openAppUrl }}" class="font-medium text-sambal-700 underline">Open this place in the app</a>
        </p>

        <section class="mt-10 rounded-3xl bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Can't decide where to makan?</h2>
            <p class="mt-2 text-ink/70">MakanApa picks a spot near you in seconds — halal-aware, budget-aware, no scrolling. Free on iPhone.</p>
        </section>

        @if ($restaurant->provider === 'google')
            <p class="mt-6 text-xs text-ink/70">Place data © Google.</p>
        @endif
    </article>
@endsection
