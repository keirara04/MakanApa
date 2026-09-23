<x-filament-panels::page>
    @php($metrics = $this->metrics())
    @php($concentration = $this->concentration())
    @php($pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 1).'%')
    @php($num = fn ($v, $d = 2) => $v === null ? '—' : number_format($v, $d))

    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Cohort</span>
            <select wire:model.live="cohort" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                @foreach ($this->cohorts() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Last N days</span>
            <input type="number" min="1" max="180" wire:model.live.debounce.500ms="days" class="w-24 rounded-lg border-none py-1.5 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" />
        </label>
    </div>

    <x-filament::section heading="Decisions">
        @if ($metrics === [])
            <p class="text-sm text-gray-500">No decisions in this cohort and window yet.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th class="py-1">Version</th><th>Decisions</th><th>Accept</th><th>Median time to decide</th>
                        <th>Rerolls / dec.</th><th>Tunes / dec.</th><th>Fatigue</th><th>Regret</th>
                        <th>Exploratory accepted</th><th>Reasons opened</th><th>What-if</th><th>Directions</th>
                    </tr>
                </thead>
                <tbody class="tabular-nums">
                    @foreach ($metrics as $m)
                        <tr class="border-t border-gray-950/5 dark:border-white/10">
                            <td class="py-1.5 font-semibold">{{ $m['version'] }}</td>
                            <td>{{ $m['decisions'] }}</td>
                            <td>{{ $pct($m['acceptRate']) }}</td>
                            <td>{{ $m['medianSeconds'] === null ? '—' : number_format($m['medianSeconds'], 0).'s' }}</td>
                            <td>{{ $num($m['rerollsPerDecision']) }}</td>
                            <td>{{ $num($m['tunesPerDecision']) }}</td>
                            <td>{{ $pct($m['fatigueRate']) }}</td>
                            <td>{{ $pct($m['regretRate']) }}</td>
                            <td>{{ $pct($m['exploratoryAcceptRate']) }} <span class="text-gray-400">({{ $m['exploratoryShown'] }})</span></td>
                            <td>{{ $pct($m['reasonsExpandedRate']) }}</td>
                            <td>{{ $pct($m['whatIfRate']) }}</td>
                            <td>{{ $pct($m['directionsRate']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p class="mt-3 text-xs text-gray-500">
            Regret = accepted, then decided again within 2 minutes (same person) or reopened. Exploratory = picks shown with
            selection probability under 20%. Lower regret and faster decisions matter more than raw accept rate.
        </p>
    </x-filament::section>

    <x-filament::section heading="Discovery health (all cohorts)">
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr><th class="py-1">Version</th><th>Accepts</th><th>Top-10 share</th><th>Catalog coverage</th><th>Categories</th><th>Repeat-category rate</th></tr>
            </thead>
            <tbody class="tabular-nums">
                @forelse ($concentration as $c)
                    <tr class="border-t border-gray-950/5 dark:border-white/10">
                        <td class="py-1.5 font-semibold">{{ $c['version'] }}</td>
                        <td>{{ $c['accepts'] }}</td>
                        <td>{{ $pct($c['top10Share']) }}</td>
                        <td>{{ $pct($c['coverage']) }}</td>
                        <td>{{ $c['categories'] }}</td>
                        <td>{{ $pct($c['repeatCategoryRate']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-2 text-gray-500">No accepted picks in this window yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">If personalization is working but discovery is dying, top-10 share rises and coverage falls.</p>
    </x-filament::section>
</x-filament-panels::page>
