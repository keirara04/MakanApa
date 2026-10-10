@extends('layouts.marketing')

@section('title', 'Opening MakanApa…')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="text-center">
        <img src="{{ asset('images/mascot-default.svg') }}" alt="" class="mx-auto h-24 w-24" aria-hidden="true">
        <h1 class="mt-4 type-section">Opening MakanApa…</h1>
        <p class="mt-3 text-ink/70">Don't have the app yet? Get it free on the App Store, then open {{ $restaurant->name }} from there.</p>
        <x-marketing.app-store-badge :href="$downloadUrl" class="mt-4" />
        <p class="mt-4 text-sm text-ink/70">
            <a href="{{ $appUrl }}" class="font-medium text-sambal-700 underline">Try opening the app again</a>
            <span aria-hidden="true">·</span>
            <a href="{{ $placeUrl }}" class="font-medium text-sambal-700 underline">Back to {{ $restaurant->name }}</a>
        </p>
    </div>
@endsection

{{-- Try the app's URL scheme. If the app opens, Safari hides this page; if it's still showing after
     a couple of seconds the app isn't installed, so carry on to the App Store (tracked link). --}}
@push('body_scripts')
    <script>
        (function () {
            var left = false;
            function away() { left = true; }
            document.addEventListener('visibilitychange', function () { if (document.hidden) away(); });
            window.addEventListener('pagehide', away);

            window.location.href = @json($appUrl);
            setTimeout(function () {
                if (!left && !document.hidden) window.location.replace(@json($downloadUrl));
            }, 2000);
        })();
    </script>
@endpush
