<?php

namespace Tests\Unit\Services\Brain;

use App\Services\Brain\MomentPulse;
use App\Services\Brain\TasteMemory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TasteMemoryTest extends TestCase
{
    private function event(array $overrides): array
    {
        return [
            'signal' => 'accept', 'dimension' => 'category', 'dimension_key' => 'nasi', 'value' => 1.0,
            'scope' => 'both', 'slot' => 'lunch', 'detail' => null, 'session_id' => 's1',
            'metadata' => json_encode(['primary' => true]), 'created_at' => '2026-09-01T12:00:00+00:00', ...$overrides,
        ];
    }

    public function test_value_halves_after_one_half_life(): void
    {
        $decayed = TasteMemory::decay(2.0, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-22'));

        $this->assertEqualsWithDelta(1.0, $decayed, 0.0001);
    }

    public function test_confidence_grows_with_evidence(): void
    {
        $this->assertEqualsWithDelta(0.2, TasteMemory::confidence(1), 0.0001);
        $this->assertEqualsWithDelta(0.667, TasteMemory::confidence(8), 0.001);
    }

    public function test_one_explicit_negative_cannot_flip_eight_accepts(): void
    {
        $state = TasteMemory::empty();
        for ($i = 0; $i < 8; $i++) {
            $state = TasteMemory::apply($state, $this->event(['created_at' => sprintf('2026-09-%02dT12:00:00+00:00', $i + 1)]));
        }
        // "Don't like this" — explicit authority (×2 already applied by the recorder).
        $state = TasteMemory::apply($state, $this->event(['signal' => 'why_not', 'value' => -1.2, 'scope' => 'long', 'created_at' => '2026-09-09T12:00:00+00:00']));

        $entry = $state['memory']['longTerm']['category']['nasi'];
        $this->assertGreaterThan(0, TasteMemory::effective($entry, Carbon::parse('2026-09-09T12:00:00+00:00')));
        $this->assertSame(8, $state['signal_count'] - 1);
    }

    public function test_pulse_scope_never_touches_long_term_memory(): void
    {
        $state = TasteMemory::apply(TasteMemory::empty(), $this->event(['signal' => 'why_not', 'scope' => 'pulse', 'value' => -0.8]));

        $this->assertSame([], $state['memory']['longTerm']);
    }

    public function test_accept_feeds_slot_slice_recent_ring_and_distance(): void
    {
        $state = TasteMemory::apply(TasteMemory::empty(), $this->event(['slot' => 'supper']));
        $state = TasteMemory::apply($state, $this->event(['dimension' => 'distance', 'dimension_key' => 'km', 'value' => 1.2, 'scope' => 'long', 'metadata' => null]));
        $state = TasteMemory::apply($state, $this->event(['dimension' => 'distance', 'dimension_key' => 'km', 'value' => 2.2, 'scope' => 'long', 'metadata' => null]));

        $this->assertSame(1, $state['memory']['slots']['supper']['category']['nasi']['n']);
        $this->assertSame('nasi', $state['memory']['recent'][0]['category']);
        $this->assertEqualsWithDelta(1.5, $state['memory']['distance']['ewma'], 0.0001); // 0.3·2.2 + 0.7·1.2
    }

    public function test_reset_clears_state(): void
    {
        $state = TasteMemory::apply(TasteMemory::empty(), $this->event([]));
        $state = TasteMemory::apply($state, $this->event(['signal' => 'reset', 'dimension' => 'boundary']));

        $this->assertSame(TasteMemory::empty(), $state);
    }

    public function test_pulse_weights_session_events_fully_and_fades_the_rest(): void
    {
        $now = Carbon::parse('2026-09-10T12:00:00+00:00');
        $events = [
            $this->event(['signal' => 'reroll', 'scope' => 'pulse', 'value' => -0.2, 'session_id' => 'now', 'created_at' => '2026-09-10T11:59:00+00:00', 'decision_recommendation_id' => 1]),
            $this->event(['signal' => 'reroll', 'scope' => 'pulse', 'value' => -0.2, 'session_id' => 'old', 'created_at' => '2026-09-10T00:00:00+00:00', 'decision_recommendation_id' => 2]),
        ];

        $pulse = MomentPulse::fromEvents($events, 'now', $now);

        // -0.2 (in session) + -0.2 × 0.5 × 0.5^(12/12) = -0.25
        $this->assertEqualsWithDelta(-0.25, $pulse->delta('category', 'nasi'), 0.0001);
        $this->assertSame(1, $pulse->sessionActions);
    }
}
