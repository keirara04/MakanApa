<?php

namespace Tests\Unit\Services\Craving;

use App\Services\Craving\CravingIntent;
use App\Services\Craving\CravingResolver;
use App\Services\Craving\DailyAiBudget;
use App\Services\Craving\IntentParser;
use Tests\TestCase;

class CravingResolverTest extends TestCase
{
    public function test_high_confidence_taxonomy_match_never_calls_ai_parser(): void
    {
        $parser = $this->countingParser($calls);
        $resolver = new CravingResolver($parser, $this->unlimitedBudget());

        $intent = $resolver->resolve('nasi kandar');

        $this->assertSame('nasi_kandar', $intent->concept);
        $this->assertSame('taxonomy', $intent->source);
        $this->assertSame(0, $calls());
    }

    public function test_low_confidence_text_calls_ai_parser(): void
    {
        $parser = $this->countingParser($calls, CravingIntent::fromAi('nak ais krim', 'ice_cream', 0.9));
        $resolver = new CravingResolver($parser, $this->unlimitedBudget());

        $intent = $resolver->resolve('nak ais krim');

        $this->assertSame('ice_cream', $intent->concept);
        $this->assertSame('ai', $intent->source);
        $this->assertSame(1, $calls());
    }

    public function test_ai_returning_null_falls_back_to_none_without_throwing(): void
    {
        $parser = $this->countingParser($calls, null);
        $resolver = new CravingResolver($parser, $this->unlimitedBudget());

        $intent = $resolver->resolve('completely unrecognizable gibberish query');

        $this->assertSame('none', $intent->source);
        $this->assertNull($intent->concept);
    }

    public function test_ai_throwing_is_not_propagated_by_a_conforming_parser(): void
    {
        // IntentParser's contract is "never throw, return null on failure" — a resolver caller
        // relies on that; this documents the fallback behavior when a parser upholds it.
        $parser = new class implements IntentParser
        {
            public function parse(string $normalizedText, ?array $localHint): ?CravingIntent
            {
                return null;
            }
        };
        $resolver = new CravingResolver($parser, $this->unlimitedBudget());

        $intent = $resolver->resolve('nonsense query');

        $this->assertSame('none', $intent->source);
    }

    public function test_no_ai_parser_configured_falls_back_to_taxonomy_or_none(): void
    {
        $resolver = new CravingResolver(null, null);

        $recognized = $resolver->resolve('nasi kandar');
        $unrecognized = $resolver->resolve('completely unrecognizable gibberish');

        $this->assertSame('taxonomy', $recognized->source);
        $this->assertSame('none', $unrecognized->source);
    }

    public function test_budget_exhausted_skips_ai_call_entirely(): void
    {
        $parser = $this->countingParser($calls, CravingIntent::fromAi('ice cream', 'ice_cream', 0.9));
        $budget = new class extends DailyAiBudget
        {
            public function __construct() {}

            public function tryConsume(): bool
            {
                return false;
            }
        };
        $resolver = new CravingResolver($parser, $budget);

        $intent = $resolver->resolve('completely unrecognizable gibberish');

        $this->assertSame('none', $intent->source);
        $this->assertSame(0, $calls());
    }

    public function test_second_call_with_same_normalized_text_hits_cache(): void
    {
        $parser = $this->countingParser($calls, CravingIntent::fromAi('ice cream', 'ice_cream', 0.9));
        $resolver = new CravingResolver($parser, $this->unlimitedBudget());

        $resolver->resolve('completely unrecognizable gibberish once');
        $resolver->resolve('  Completely Unrecognizable   Gibberish Once  '); // same normalized text

        $this->assertSame(1, $calls());
    }

    public function test_repeat_query_after_cache_hit_does_not_consume_ai_budget_again(): void
    {
        $parser = $this->countingParser($calls, CravingIntent::fromAi('ice cream', 'ice_cream', 0.9));
        $budget = $this->countingBudget($consumeCalls);
        $resolver = new CravingResolver($parser, $budget);

        $resolver->resolve('completely unrecognizable gibberish twice');
        $resolver->resolve('completely unrecognizable gibberish twice'); // same normalized text — cache hit

        $this->assertSame(1, $calls());
        $this->assertSame(1, $consumeCalls());
    }

    public function test_ai_failure_is_not_cached_so_the_next_call_retries(): void
    {
        $succeedsOnSecondCall = new class implements IntentParser
        {
            public int $calls = 0;

            public function parse(string $normalizedText, ?array $localHint): ?CravingIntent
            {
                $this->calls++;

                return $this->calls === 1 ? null : CravingIntent::fromAi('ice cream', 'ice_cream', 0.9);
            }
        };
        $resolver = new CravingResolver($succeedsOnSecondCall, $this->unlimitedBudget());

        $first = $resolver->resolve('completely unrecognizable gibberish thrice');
        $second = $resolver->resolve('completely unrecognizable gibberish thrice');

        $this->assertSame('none', $first->source);
        $this->assertSame('ai', $second->source);
        $this->assertSame('ice_cream', $second->concept);
        $this->assertSame(2, $succeedsOnSecondCall->calls);
    }

    private function countingBudget(&$consumeCalls): DailyAiBudget
    {
        $count = 0;
        $consumeCalls = function () use (&$count) {
            return $count;
        };

        return new class($count) extends DailyAiBudget
        {
            public function __construct(private int &$count) {}

            public function tryConsume(): bool
            {
                $this->count++;

                return true;
            }
        };
    }

    private function countingParser(&$calls, ?CravingIntent $result = null): IntentParser
    {
        $count = 0;
        $calls = function () use (&$count) {
            return $count;
        };

        return new class($result, $count) implements IntentParser
        {
            public function __construct(private ?CravingIntent $result, private int &$count) {}

            public function parse(string $normalizedText, ?array $localHint): ?CravingIntent
            {
                $this->count++;

                return $this->result;
            }
        };
    }

    private function unlimitedBudget(): DailyAiBudget
    {
        return new class extends DailyAiBudget
        {
            public function __construct() {}

            public function tryConsume(): bool
            {
                return true;
            }
        };
    }
}
