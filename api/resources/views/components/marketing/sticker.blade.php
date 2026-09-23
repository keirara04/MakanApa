@props(['tone' => 'cream'])

{{-- Hand-lettered, slightly tilted sticker ("Dekat je", "< RM20"). Decorative: whatever it says
     is also said in real text or alt nearby, so screen readers skip it. --}}
<span aria-hidden="true"
      {{ $attributes->class([
          'inline-flex items-center gap-2 whitespace-nowrap rounded-2xl border-2 px-4 py-1.5 font-hand text-2xl leading-none shadow-clay sm:text-3xl',
          'border-ink/80 bg-cream text-ink' => $tone === 'cream',
          'border-sambal-600 bg-white text-sambal-700' => $tone === 'sambal',
      ]) }}>
    {{ $slot }}
</span>
