<x-filament-panels::page>
    @php($report = $this->report())
    @php($usd = fn ($v) => '$'.number_format($v, 2))
    @php($pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 0).'%')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(24rem,1fr));gap:1rem">
        @foreach ($report as $provider)
            <x-filament::section :heading="$provider['label']">
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem">
                    <div>
                        <p class="text-xs text-gray-500">Month to date</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $usd($provider['cost']) }}</p>
                        <p class="text-xs text-gray-500">{{ $pct($provider['percent']) }} of budget</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Projected month end</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $usd($provider['projected']) }}</p>
                        @php($projected = $provider['projectedPercent'])
                        <x-filament::badge :color="$projected === null ? 'gray' : ($projected >= 1 ? 'danger' : ($projected >= 0.8 ? 'warning' : 'success'))" class="mt-1 w-fit">
                            {{ $pct($projected) }} of budget
                        </x-filament::badge>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Monthly budget</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $provider['budget'] > 0 ? $usd($provider['budget']) : '—' }}</p>
                    </div>
                </div>

                <table class="mt-4 w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr><th class="py-1">SKU / model</th><th>Usage (MTD)</th><th>Est. cost</th><th>Projected</th></tr>
                    </thead>
                    <tbody class="tabular-nums">
                        @forelse ($provider['lines'] as $line)
                            <tr class="border-t border-gray-950/5 dark:border-white/10">
                                <td class="py-1.5">{{ $line['label'] }}</td>
                                <td>{{ number_format($line['units']) }} {{ $line['unitLabel'] }}</td>
                                <td>{{ $usd($line['cost']) }}</td>
                                <td>{{ $usd($line['projected']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-gray-500">No usage this month yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-filament::section>
        @endforeach
    </div>

    <p class="text-xs text-gray-500">
        Estimates from MakanApa's own counters, priced with config/admin_budgets.php (Google's monthly free caps are
        subtracted first). Projection assumes the rest of the month looks like the days so far. Check the Google Cloud
        and OpenRouter billing consoles for the real invoice. Cached for 5 minutes.
    </p>
</x-filament-panels::page>
