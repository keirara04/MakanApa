@props(['name'])

{{-- Decorative muted loop from public/videos (credits in public/videos/CREDITS.md). preload="none"
     so nothing downloads up front; the layout script plays it only while it's on screen, and
     leaves it on its poster frame under Reduce Motion or Data Saver. Whatever it shows is also
     said in the caption next to it, so screen readers skip it. --}}
<video {{ $attributes->merge(['class' => 'block h-full w-full object-cover']) }}
       data-clip muted loop playsinline preload="none" disablepictureinpicture disableremoteplayback
       poster="{{ asset("videos/{$name}.jpg") }}" aria-hidden="true" tabindex="-1">
    <source src="{{ asset("videos/{$name}.mp4") }}" type="video/mp4">
</video>
