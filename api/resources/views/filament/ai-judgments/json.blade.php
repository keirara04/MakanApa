@php($value = $getState())
@if (blank($value))
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty ?? '—' }}</p>
@else
    <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
@endif
