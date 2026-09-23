@php($attempts = $getState() ?? [])
@if ($attempts === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">No provider requests were made (disabled, over budget, or state too large).</p>
@else
    <table class="w-full text-left text-xs">
        <thead class="text-gray-500">
            <tr><th class="py-1">Sample</th><th>Retry</th><th>HTTP</th><th>Latency</th><th>Failure</th></tr>
        </thead>
        <tbody>
            @foreach ($attempts as $a)
                <tr class="border-t border-gray-950/5 dark:border-white/10">
                    <td class="py-1">{{ ($a['sample'] ?? 0) + 1 }}</td>
                    <td>{{ $a['retry'] ?? 0 }}</td>
                    <td>{{ $a['httpStatus'] ?? '—' }}</td>
                    <td>{{ $a['latencyMs'] ?? '—' }} ms</td>
                    <td>{{ $a['failureReason'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
