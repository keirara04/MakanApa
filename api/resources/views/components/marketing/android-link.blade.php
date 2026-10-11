@props(['from', 'tone' => 'ink'])

{{-- Inline, at the end of the "Free on iPhone." line. For Android phones the site didn't recognise
     (Chrome's "Desktop site" mode drops "Android" from the user agent), or someone reading on
     another device. Recognised Android visitors already get "Open MakanApa" in place of the App
     Store badge. --}}
@if (\App\Support\WebAppEntry::live() && ! \App\Support\WebAppEntry::forAndroid())
    <span {{ $attributes->class(['block sm:inline']) }}>
        On Android?
        <a href="{{ route('marketing.web-app', ['from' => $from]) }}"
           @class(['underline decoration-2 underline-offset-4', 'text-sambal-700 hover:text-ink' => $tone === 'ink', 'text-paper hover:decoration-paper/60' => $tone === 'paper'])>Open the web app</a>
    </span>
@endif
