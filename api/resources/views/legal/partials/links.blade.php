{{-- Cross-links between the three legal pages; the current page is marked instead of linked. --}}
<nav aria-label="Legal documents" class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm">
    @foreach (['terms' => 'Terms of Use', 'privacy' => 'Privacy Policy', 'community-guidelines' => 'Community Guidelines'] as $routeName => $label)
        @if (request()->routeIs($routeName))
            <span class="font-semibold text-ink" aria-current="page">{{ $label }}</span>
        @else
            <a href="{{ route($routeName) }}" class="font-medium text-sambal-600 underline">{{ $label }}</a>
        @endif
    @endforeach
</nav>
