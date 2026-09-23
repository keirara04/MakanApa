<?php

namespace App\Support\Halal;

final readonly class HalalHeuristicResult
{
    /**
     * @param  list<array{field: string, term: string, strength: 'strong'|'weak'}>  $matches
     */
    public function __construct(
        public bool $likelyNonHalal,
        public array $matches,
    ) {}

    /** "name: "bak kut teh" (strong)" — used by halal:classify output. */
    public function describe(): string
    {
        return implode(', ', array_map(
            fn (array $m) => "{$m['field']}: \"{$m['term']}\" ({$m['strength']})",
            $this->matches,
        ));
    }
}
