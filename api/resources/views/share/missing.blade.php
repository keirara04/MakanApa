@extends('layouts.marketing')

@section('title', 'Place not found | MakanApa')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="text-center">
        <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="mx-auto h-24 w-24" aria-hidden="true">
        <h1 class="mt-4 text-3xl font-semibold">This spot isn't on MakanApa anymore</h1>
        <p class="mt-3 text-ink/70">It may have closed down. MakanApa can pick something else near you in seconds.</p>
        <x-marketing.download-button from="final" class="mt-6">Get the app</x-marketing.download-button>
    </div>
@endsection
