@props(['from' => null, 'href' => null, 'size' => 'lg', 'campus' => null])

{{-- Apple's official "Download on the App Store" badge (public/images/app-store-badge.svg, used
     unmodified). Apple's rules: at least 40px tall, clear space of a quarter of its height on every
     side (the padding below), and never tilted, animated or restyled — so unlike the ink pill this
     has no hover lift, only a focus ring. Clicks go through a tracked redirect like every CTA. --}}
<a href="{{ $href ?? route('marketing.download', array_filter(['from' => $from, 'campus' => $campus])) }}"
   {{ $attributes->class([
       'inline-block rounded-2xl outline-offset-0 focus-visible:outline-2 focus-visible:outline-sambal-600',
       'p-3.5' => $size === 'lg',
       'p-3' => $size === 'md',
   ]) }}>
    <img src="{{ asset('images/app-store-badge.svg') }}" alt="Download on the App Store"
         width="{{ $size === 'lg' ? 168 : 144 }}" height="{{ $size === 'lg' ? 56 : 48 }}"
         @class(['w-auto', 'h-14' => $size === 'lg', 'h-12' => $size === 'md'])>
</a>
