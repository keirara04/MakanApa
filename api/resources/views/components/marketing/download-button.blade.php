@props(['from', 'size' => 'lg'])

{{-- Every download CTA goes through /go/testflight so clicks are counted per placement. --}}
<a href="{{ route('marketing.download', ['from' => $from]) }}"
   {{ $attributes->class([
       'group inline-flex items-center justify-center gap-2 rounded-full bg-sambal-600 font-semibold text-white shadow-clay transition duration-200 hover:bg-sambal-700 hover:-translate-y-0.5 active:translate-y-0',
       'min-h-12 whitespace-nowrap px-6 py-3 text-base sm:px-7' => $size === 'lg',
       'min-h-10 px-4 py-2 text-sm' => $size === 'sm',
   ]) }}>
    {{-- Lucide "smartphone" — no Apple logo outside Apple's official badges. --}}
    <svg class="{{ $size === 'lg' ? 'h-5 w-5' : 'h-4 w-4' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
        <path d="M12 18h.01"/>
    </svg>
    {{ $slot->isEmpty() ? config('marketing.app_download_label') : $slot }}
    @if ($size === 'lg')
        <svg class="h-5 w-5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
        </svg>
    @endif
</a>
