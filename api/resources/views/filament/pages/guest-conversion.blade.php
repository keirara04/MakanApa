<x-filament-panels::page>
    @php($summary = $this->summary())
    @php($pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 1).'%')
    @php($hours = fn ($v) => $v === null ? '—' : ($v < 48 ? number_format($v, 1).' h' : number_format($v / 24, 1).' days'))

    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Window</span>
            <select wire:model.live="days" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </label>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem">
        @foreach ([
            'New guests' => number_format($summary['guests']),
            'Upgraded (of those)' => number_format($summary['converted']),
            'Conversion rate' => $pct($summary['rate']),
            'Median time to convert' => $hours($summary['medianHoursToConvert']),
        ] as $label => $value)
            <x-filament::section>
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $value }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem">
        <x-filament::section heading="Guest upgrades by source" description="Which prompt the guest signed up from (users.signup_source), for upgrades in the window.">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr><th class="py-1">Source</th><th>Upgrades</th><th>Median time to convert</th></tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($summary['conversionsBySource'] as $row)
                        <tr class="border-t border-gray-950/5 dark:border-white/10">
                            <td class="py-1.5 font-mono text-xs">{{ $row['source'] }}</td>
                            <td>{{ number_format($row['total']) }}</td>
                            <td>{{ $hours($row['medianHours']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-500">No guest upgrades in this window yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Direct sign-ups by source" description="Accounts created without a guest session first.">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr><th class="py-1">Source</th><th>Sign-ups</th></tr>
                </thead>
                <tbody class="tabular-nums">
                    @forelse ($summary['freshSignupsBySource'] as $row)
                        <tr class="border-t border-gray-950/5 dark:border-white/10">
                            <td class="py-1.5 font-mono text-xs">{{ $row['source'] }}</td>
                            <td>{{ number_format($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-2 text-gray-500">No direct sign-ups in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>

    <p class="text-xs text-gray-500">
        "unknown" = signed up from an app build older than signup-source tracking. Numbers are cached for 5 minutes.
    </p>
</x-filament-panels::page>
