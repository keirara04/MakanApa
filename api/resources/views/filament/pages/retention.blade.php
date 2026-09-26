<x-filament-panels::page>
    @php($cohorts = $this->cohorts())
    @php($windows = ['d1' => 'Day 1', 'd7' => 'Day 7', 'd30' => 'Day 30', 'w1' => 'Week 1', 'w2' => 'Week 2', 'w3' => 'Week 3', 'w4' => 'Week 4'])

    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Who</span>
            <select wire:model.live="segment" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                @foreach ($this->segments() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-gray-500">Cohorts (weeks)</span>
            <select wire:model.live="weeks" class="fi-select-input rounded-lg border-none py-1.5 ps-3 pe-8 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                @foreach ([4, 8, 12, 26] as $n)
                    <option value="{{ $n }}">{{ $n }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <x-filament::section>
        <table class="w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th class="py-1">Signup week</th><th>Users</th>
                    @foreach ($windows as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="tabular-nums">
                @forelse ($cohorts as $cohort)
                    <tr class="border-t border-gray-950/5 dark:border-white/10">
                        <td class="py-1.5 font-semibold">{{ \Illuminate\Support\Carbon::parse($cohort['cohort'])->format('j M Y') }}</td>
                        <td>{{ number_format($cohort['size']) }}</td>
                        @foreach (array_keys($windows) as $key)
                            @php($w = $cohort['windows'][$key])
                            <td title="{{ $w['retained'] }} of {{ $w['eligible'] }} eligible">
                                @if ($w['rate'] === null)
                                    <span class="text-gray-400">—</span>
                                @else
                                    <x-filament::badge :color="$w['rate'] >= 0.3 ? 'success' : ($w['rate'] >= 0.1 ? 'warning' : 'danger')" class="w-fit">
                                        {{ number_format($w['rate'] * 100, 0) }}%
                                    </x-filament::badge>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 2 + count($windows) }}" class="py-2 text-gray-500">No signups in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">
            Retained = opened the app (an app session) during that window after signing up. "—" means the window
            hasn't fully passed for anyone in the cohort yet. Hover a cell for counts. Cached for 10 minutes.
        </p>
    </x-filament::section>
</x-filament-panels::page>
