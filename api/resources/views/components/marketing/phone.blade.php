@props(['screen', 'alt', 'eager' => false, 'priority' => false])

{{-- CSS-only iPhone frame around a real app screenshot. width/height are set so the image
     reserves its space before loading (no layout shift). `priority` is for the one LCP image only;
     a lazy image that's display:none (the hero's side phones on mobile) is never downloaded. --}}
<div {{ $attributes->merge(['class' => 'rounded-[2.6rem] bg-ink p-2 shadow-phone']) }}>
    <img src="{{ asset("images/screens/{$screen}-360.webp") }}"
         srcset="{{ asset("images/screens/{$screen}-360.webp") }} 360w, {{ asset("images/screens/{$screen}-720.webp") }} 720w"
         sizes="(min-width: 1024px) 300px, 70vw"
         width="360" height="783"
         alt="{{ $alt }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}"
         @if ($priority) fetchpriority="high" @endif
         decoding="async"
         class="block h-auto w-full rounded-[2.1rem] bg-cream">
</div>
