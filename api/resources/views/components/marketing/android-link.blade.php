@props(['from', 'tone' => 'ink'])

{{-- For Android phones the site didn't recognise (Chrome's "Desktop site" mode drops "Android"
     from the user agent), or someone reading on a computer for an Android phone. Recognised
     Android visitors already get "Open MakanApa" in place of the App Store badge. --}}
@if (\App\Support\WebAppEntry::live() && ! \App\Support\WebAppEntry::forAndroid())
    <p {{ $attributes->class(['font-medium', 'text-ink/70' => $tone === 'ink', 'text-paper/90' => $tone === 'paper']) }}>
        On Android?
        <a href="{{ route('marketing.web-app', ['from' => $from]) }}"
           @class(['underline underline-offset-2', 'text-sambal-700 hover:text-ink' => $tone === 'ink', 'text-paper hover:text-paper/80' => $tone === 'paper'])>Open the web app</a>
    </p>
@endif
