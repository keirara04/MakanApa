@extends('layouts.marketing')

@section('title', 'Place not found | MakanApa')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="text-center">
        <img src="{{ asset('images/mascot/sad-320.webp') }}" alt="" class="mx-auto h-24 w-24" aria-hidden="true">
        <h1 class="mt-4 type-section">This spot isn't on MakanApa anymore</h1>
        <p class="mt-3 text-ink/70">It may have closed down. MakanApa can pick something else near you in seconds.</p>
        <p class="mt-6 text-ink/70">Get the app free on the App Store.</p>
        <x-marketing.app-store-badge from="missing" class="mt-2" />
    </div>
@endsection
