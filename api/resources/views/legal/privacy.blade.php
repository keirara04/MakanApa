@extends('layouts.marketing')

@php
    // PDPA s.7(3): the privacy notice must be available in Bahasa Malaysia and English. Server-side
    // (not the landing page's JS toggle) so each language has its own link and works without JS.
    $isMalay = request()->query('lang') === 'ms';
    $updated = \Illuminate\Support\Carbon::parse(config('legal.privacy_version'))->format('j F Y');
    $h2 = 'font-display text-3xl font-bold uppercase leading-none text-ink';
    $link = 'font-medium text-sambal-600 underline';
    $privacyEmail = config('marketing.privacy_email');
@endphp

@section('title', $isMalay ? 'Notis Privasi | MakanApa' : 'Privacy Policy | MakanApa')
@section('description', $isMalay ? 'Cara MakanApa mengumpul, menggunakan dan memadam data peribadi anda.' : 'How MakanApa collects, uses, shares and deletes your data.')

@push('meta')
    <link rel="alternate" hreflang="en" href="{{ route('privacy') }}">
    <link rel="alternate" hreflang="ms" href="{{ route('privacy', ['lang' => 'ms']) }}">
@endpush

@section('content')

    <div class="flex flex-wrap items-center gap-2 text-sm" role="group" aria-label="Language / Bahasa">
        @foreach (['en' => 'English', 'ms' => 'Bahasa Malaysia'] as $code => $label)
            @if (($code === 'ms') === $isMalay)
                <span class="inline-flex min-h-10 items-center rounded-full bg-ink px-4 py-2 font-semibold text-white" aria-current="true">{{ $label }}</span>
            @else
                <a href="{{ $code === 'ms' ? route('privacy', ['lang' => 'ms']) : route('privacy') }}" hreflang="{{ $code }}" lang="{{ $code }}"
                   class="inline-flex min-h-10 items-center rounded-full border border-ink/20 bg-paper-50 px-4 py-2 font-semibold text-ink hover:border-ink/60">{{ $label }}</a>
            @endif
        @endforeach
    </div>

    <div lang="{{ $isMalay ? 'ms' : 'en' }}" class="mt-6">
        @if ($isMalay)
            @include('legal.privacy.ms')
        @else
            @include('legal.privacy.en')
        @endif
    </div>

@endsection
