<x-filament-panels::page>
    @php($report = $this->report())
    @php($totals = $this->totals())

    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Purpose</span>
            <select wire:model.live="purpose" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                @foreach ($this->purposes() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Definition version</span>
            <select wire:model.live="version" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                <option value="">Latest</option>
                @foreach ($this->versions() as $v)
                    <option value="{{ $v }}">v{{ $v }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Threshold</span>
            <input type="number" step="0.05" min="0" max="1" wire:model.live.debounce.500ms="threshold" class="w-24 rounded-lg border-none py-1.5 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" />
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Since</span>
            <input type="date" wire:model.live="since" class="rounded-lg border-none py-1.5 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" />
        </label>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach (['Runs' => $totals['runs'], 'Successful' => $totals['ok'], 'Labelled by an admin' => $totals['labelled']] as $label => $value)
            <x-filament::section compact>
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="text-2xl font-semibold tabular-nums">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <p class="text-sm text-gray-500">
        {{ $report['purpose'] }} v{{ $report['version'] }} · {{ $report['labelled'] }} labelled judgments.
        A well-calibrated question has "actual yes rate" close to "mean predicted" in every band.
        Brier score: 0 is perfect, 0.25 is a coin flip. Truth is derived from the admin's decision, so it's approximate.
    </p>

    @forelse ($report['questions'] as $questionId => $q)
        <x-filament::section :heading="$questionId">
            @if ($q['kind'] === 'choice')
                <p class="text-sm">n={{ $q['n'] }} · top-1 hit rate <strong>{{ number_format($q['hitRate'], 2) }}</strong></p>
            @else
                <p class="mb-3 text-sm">
                    n={{ $q['n'] }} · Brier <strong>{{ number_format($q['brier'], 4) }}</strong> ·
                    base rate {{ number_format($q['baseRate'], 2) }} ·
                    false positives {{ $q['falsePositives'] }} · false negatives {{ $q['falseNegatives'] }}
                    (at ≥ {{ number_format($report['threshold'], 2) }})
                </p>
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr><th class="py-1">Predicted</th><th>n</th><th>Mean predicted</th><th>Actual yes rate</th><th class="w-1/3"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($q['bands'] as $band)
                            <tr class="border-t border-gray-950/5 dark:border-white/10">
                                <td class="py-1 font-mono">{{ $band['range'] }}</td>
                                <td>{{ $band['n'] }}</td>
                                <td>{{ number_format($band['meanPredicted'], 2) }}</td>
                                <td>{{ number_format($band['actualRate'], 2) }}</td>
                                <td>
                                    <div class="relative h-2 rounded bg-gray-100 dark:bg-white/10">
                                        <div class="absolute h-2 rounded bg-primary-500" style="width: {{ round($band['actualRate'] * 100) }}%"></div>
                                        <div class="absolute -top-1 h-4 w-0.5 bg-gray-700 dark:bg-gray-200" style="left: {{ round($band['meanPredicted'] * 100) }}%" title="mean predicted"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500">No labelled judgments yet. Outcomes are recorded when an admin approves/rejects a halal vouch, or sets a status on an AI-flagged restaurant.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
