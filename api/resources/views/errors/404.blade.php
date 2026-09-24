@extends('layouts.marketing')

@section('title', 'Page not found | MakanApa')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="py-10 text-center">
        <img src="{{ asset('images/mascot-sad.svg') }}" alt="" aria-hidden="true" width="140" height="140" class="mx-auto h-32 w-32">

        <p class="relative mx-auto mt-6 inline-block font-display text-[clamp(6rem,22vw,10rem)] font-bold leading-[0.8]">
            404
            <x-marketing.doodle type="circle" class="draw absolute -left-6 -top-3 h-[calc(100%+1.5rem)] w-[calc(100%+3rem)] text-sambal-600" style="--draw-delay: 400ms" />
        </p>

        <h1 class="mt-8 font-display text-[clamp(2.6rem,7vw,4rem)] font-bold uppercase leading-[0.9] tracking-tight text-balance">
            This page went out to makan.
        </h1>
        <p class="mx-auto mt-4 max-w-md text-lg text-ink/75">
            The link might be old, or the page moved. Let's get you back to deciding what to eat.
        </p>

        <div class="mt-10 flex flex-col items-center justify-center gap-5 sm:flex-row sm:gap-8">
            <a href="{{ url('/') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-ink px-7 py-3 font-semibold text-paper shadow-[5px_5px_0_var(--color-sambal-600)] transition-[translate,box-shadow] duration-200 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:shadow-[8px_8px_0_var(--color-sambal-600)]">
                Back to MakanApa
            </a>
            <a href="{{ url('/support') }}" class="bracket-link font-display text-2xl font-bold uppercase">[Get help]</a>
        </div>
    </div>
@endsection
