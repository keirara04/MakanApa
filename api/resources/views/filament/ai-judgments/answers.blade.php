@php($answers = $getState() ?? [])
@if ($answers === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">No answers (the run did not succeed).</p>
@else
    <div class="space-y-4">
        @foreach ($answers as $id => $a)
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $id }}</span>
                    <span class="text-xs uppercase tracking-wide text-gray-500">{{ $a['type'] }}</span>
                    @if ($a['type'] === 'binary')
                        <span class="text-sm">p(yes) <strong>{{ number_format($a['probability'], 2) }}</strong></span>
                    @elseif ($a['type'] === 'choice')
                        <span class="text-sm">choice <strong>{{ $a['choice'] }}</strong> · confidence {{ number_format($a['confidence'], 2) }} · margin {{ number_format($a['margin'] ?? 0, 2) }} · vote share {{ number_format($a['voteShare'] ?? 1, 2) }}</span>
                    @else
                        <span class="text-sm">score <strong>{{ number_format($a['score'], 2) }}</strong> / {{ $a['maxLevel'] }} · confidence {{ number_format($a['confidence'], 2) }}</span>
                    @endif
                    <span class="text-xs text-gray-500">
                        n={{ $a['sampleCount'] ?? 1 }}
                        @isset($a['sampleStdDev']) · spread ±{{ number_format($a['sampleStdDev'], 2) }} @endisset
                    </span>
                </div>

                @if (isset($a['sampleValues']) && count($a['sampleValues']) > 1)
                    <p class="mt-1 text-xs text-gray-500">samples: {{ collect($a['sampleValues'])->map(fn ($v) => number_format($v, 2))->implode(', ') }}</p>
                @elseif (isset($a['sampleScores']) && count($a['sampleScores']) > 1)
                    <p class="mt-1 text-xs text-gray-500">samples: {{ collect($a['sampleScores'])->map(fn ($v) => number_format($v, 2))->implode(', ') }}</p>
                @endif

                @if (in_array($a['type'], ['choice', 'score'], true))
                    @php($probs = collect($a['probabilities'])->sortDesc()->take(8))
                    <div class="mt-2 space-y-1">
                        @foreach ($probs as $option => $p)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-40 truncate font-mono text-gray-600 dark:text-gray-300">{{ $option }}</span>
                                <div class="h-2 flex-1 rounded bg-gray-100 dark:bg-white/10">
                                    <div class="h-2 rounded bg-primary-500" style="width: {{ round($p * 100) }}%"></div>
                                </div>
                                <span class="w-10 text-right tabular-nums">{{ number_format($p, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
