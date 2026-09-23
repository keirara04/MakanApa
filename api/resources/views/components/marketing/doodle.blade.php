@props(['type' => 'burst'])

{{-- Marker-style accents from the brand illustrations. Colour comes from `text-*`. --}}
<svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
     {{ $attributes->merge(['class' => 'pointer-events-none']) }}
     @switch($type)
         @case('burst') viewBox="0 0 40 40" @break
         @case('squiggle') viewBox="0 0 60 40" @break
         @case('sparkle') viewBox="0 0 24 24" @break
     @endswitch
>
    @switch($type)
        @case('burst')
            <path d="M20 4v8M8 10l6 5M32 10l-6 5M6 24h7M34 24h-7"/>
            @break
        @case('squiggle')
            <path d="M4 30c8-14 14 6 22-8s14 4 22-10M14 36c6-6 12 0 18-6"/>
            @break
        @case('sparkle')
            <path fill="currentColor" stroke="none" d="M12 0c.6 6.4 5.6 11.4 12 12-6.4.6-11.4 5.6-12 12-.6-6.4-5.6-11.4-12-12C6.4 11.4 11.4 6.4 12 0z"/>
            @break
    @endswitch
</svg>
