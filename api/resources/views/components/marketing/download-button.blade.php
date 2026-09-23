@props(['from', 'size' => 'lg'])

{{-- Every download CTA goes through /go/testflight so clicks are counted per placement. Ink pill
     with an offset sambal "print" shadow that lifts on hover, like a sticker peeling up. --}}
<a href="{{ route('marketing.download', ['from' => $from]) }}"
   {{ $attributes->class([
       'group inline-flex items-center justify-center gap-2 rounded-full bg-ink font-semibold text-paper transition-[translate,box-shadow,background-color] duration-200 ease-out hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#3a2619] active:translate-x-0 active:translate-y-0',
       'min-h-13 whitespace-nowrap px-7 py-3.5 text-base shadow-[5px_5px_0_var(--color-sambal-600)] hover:shadow-[8px_8px_0_var(--color-sambal-600)] active:shadow-[3px_3px_0_var(--color-sambal-600)] sm:px-8' => $size === 'lg',
       'min-h-10 whitespace-nowrap px-4 py-2 text-sm shadow-[3px_3px_0_var(--color-sambal-600)] hover:shadow-[5px_5px_0_var(--color-sambal-600)]' => $size === 'sm',
   ]) }}>
    {{-- Lucide "smartphone" — no Apple logo outside Apple's official badges. --}}
    <svg class="{{ $size === 'lg' ? 'h-5 w-5' : 'h-4 w-4' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
        <path d="M12 18h.01"/>
    </svg>
    {{ $slot->isEmpty() ? config('marketing.app_download_label') : $slot }}
    @if ($size === 'lg')
        <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
        </svg>
    @endif
</a>
