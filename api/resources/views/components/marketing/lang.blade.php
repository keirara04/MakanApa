@props(['en' => null])

{{-- One string in both voices. The slot is the Manglish default; `en` (attribute, or a named
     slot when it needs HTML) is the fully-English version. <html data-lang> picks which shows,
     see the .lang-ms / .lang-en rules in app.css. --}}
<span class="lang-ms">{{ $slot }}</span><span class="lang-en">{{ $en }}</span>
