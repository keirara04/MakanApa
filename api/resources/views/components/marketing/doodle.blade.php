@props(['type' => 'burst'])

{{-- Marker-style accents from the brand illustrations. Colour comes from `text-*`. Every stroke
     has pathLength="1" so adding the `draw` class animates it being drawn (see app.css).
     `circle` and `underline` stretch to whatever box they're given. --}}
@php
    $boxes = [
        'burst' => '0 0 40 40',
        'squiggle' => '0 0 60 40',
        'sparkle' => '0 0 24 24',
        'underline' => '0 0 300 20',
        'circle' => '0 0 120 60',
        'arrow-curve' => '0 0 120 80',
        'arrow-down' => '0 0 60 120',
        'arrow-loop' => '0 0 160 110',
        'plus' => '0 0 24 24',
    ];
    $stretches = in_array($type, ['underline', 'circle'], true);
@endphp
<svg aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
     stroke-width="{{ $type === 'underline' || $type === 'circle' ? 3 : 2.5 }}"
     viewBox="{{ $boxes[$type] ?? $boxes['burst'] }}"
     @if ($stretches) preserveAspectRatio="none" @endif
     {{ $attributes->merge(['class' => 'pointer-events-none overflow-visible']) }}>
    @switch($type)
        @case('burst')
            <path pathLength="1" d="M20 4v8M8 10l6 5M32 10l-6 5M6 24h7M34 24h-7"/>
            @break
        @case('squiggle')
            <path pathLength="1" d="M4 30c8-14 14 6 22-8s14 4 22-10M14 36c6-6 12 0 18-6"/>
            @break
        @case('sparkle')
            <path fill="currentColor" stroke="none" d="M12 0c.6 6.4 5.6 11.4 12 12-6.4.6-11.4 5.6-12 12-.6-6.4-5.6-11.4-12-12C6.4 11.4 11.4 6.4 12 0z"/>
            @break
        @case('underline')
            <path pathLength="1" d="M4 13c52-7 104-9 150-6s98 7 142 1"/>
            @break
        @case('circle')
            {{-- A marker loop that overshoots its start, like circling an answer on paper. --}}
            <path pathLength="1" d="M70 5C36 3 6 12 5 29c-1 17 30 27 60 26 31-1 52-11 50-27C113 12 86 4 52 9"/>
            @break
        @case('arrow-curve')
            <path pathLength="1" d="M5 12c32-10 70-2 92 44M84 50l14 10 4-17"/>
            @break
        @case('arrow-down')
            <path pathLength="1" d="M32 4C10 34 50 64 28 108M16 94l12 16 14-14"/>
            @break
        @case('arrow-loop')
            {{-- Curls once, then heads down-right to whatever it's pointing at. --}}
            <path pathLength="1" d="M6 18c30-14 64-8 66 12 2 18-24 22-26 6-2-20 44-26 70 6 14 18 20 38 22 56M125 84l11 16 13-15"/>
            @break
        @case('plus')
            <path pathLength="1" d="M12 3c.4 6-.3 12 .3 18M3 12.4c6-.6 12 .3 18-.4"/>
            @break
    @endswitch
</svg>
