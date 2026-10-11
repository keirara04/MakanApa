@props(['from', 'tone' => 'ink'])

{{-- Second way in for computers once the web app is live (WEB_APP_LIVE). Android already gets the
     web app in place of the App Store badge, and iPhones keep the App Store only. Outlined, so the
     badge stays the main call to action. --}}
@if (\App\Support\WebAppEntry::live() && ! \App\Support\WebAppEntry::forAndroid() && ! \App\Support\WebAppEntry::forApple())
    <a href="{{ route('marketing.web-app', ['from' => $from]) }}"
       {{ $attributes->class([
           'inline-flex min-h-13 items-center justify-center gap-2 whitespace-nowrap rounded-full border-2 px-6 py-3 font-semibold transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2',
           'border-ink text-ink hover:bg-ink hover:text-paper focus-visible:outline-sambal-600' => $tone === 'ink',
           'border-paper text-paper hover:bg-paper hover:text-sambal-700 focus-visible:outline-paper' => $tone === 'paper',
       ]) }}>
        {{-- Lucide "globe" --}}
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>
        </svg>
        Use it in your browser
    </a>
@endif
