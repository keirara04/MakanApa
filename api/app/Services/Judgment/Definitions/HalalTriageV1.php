<?php

namespace App\Services\Judgment\Definitions;

use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantSubmission;
use App\Services\Judgment\JudgmentDefinition;
use App\Services\Judgment\Questions\BinaryQuestion;
use App\Services\Judgment\Questions\ChoiceQuestion;
use App\Services\Judgment\Questions\ScoreQuestion;
use App\Services\Judgment\StateTrimmer;

/**
 * Advisory triage of a community halal vouch. Deliberately narrow, concrete questions — never
 * "should the admin approve?" or "is this place halal?". The listing and its current status are
 * CONTEXT ONLY (anchoring guard): a vouch may legitimately contradict them.
 */
final class HalalTriageV1 extends JudgmentDefinition
{
    public function purpose(): string
    {
        return 'halal_triage';
    }

    public function version(): int
    {
        return 1;
    }

    public function questions(): array
    {
        return [
            new BinaryQuestion(
                'mentions_certificate',
                'Does `evidence` describe the reporter actually seeing a halal certificate (e.g. JAKIM or a state Islamic council) for THIS premise — in the comment, the certificate fields, or an attached photo of type halal_cert?',
                yesMeans: 'A specific halal certificate for this premise is described, filled in, or photographed.',
                noMeans: 'No certificate is described, or only a vague "it is halal" / brand-level claim.',
                samples: 3,
            ),
            new BinaryQuestion(
                'mentions_pork_alcohol',
                'Does `evidence` describe pork, lard, or alcohol being served or sold at THIS place?',
                yesMeans: 'The reporter says they saw pork/lard/alcohol on the menu or being served here.',
                noMeans: 'No such mention, or only saying the place does NOT serve them.',
                samples: 3,
            ),
            new ScoreQuestion(
                'supports_claim',
                'How directly does `evidence` support the claimed status in `evidence.claim`?',
                [
                    'No support: the evidence is empty, unrelated, or argues the opposite of the claim.',
                    'Weak: a general assertion ("it is halal", "Muslim owner I think") with no specific observation.',
                    'Moderate: a specific first-hand observation that fits the claim (e.g. menu has no pork/alcohol, staff said the supplier is halal) but no document.',
                    'Strong: a specific document or unambiguous first-hand observation that directly establishes the claim (e.g. certificate details or cert photo for a certified claim; pork on the menu for a non-halal claim).',
                ],
            ),
            new BinaryQuestion(
                'is_spam_or_irrelevant',
                'Is `evidence` spam, a joke, abusive, off-topic, or clearly about a different place than the one in `context.listing`?',
                yesMeans: 'Not a genuine report about this place\'s halal status.',
                noMeans: 'A genuine attempt to report this place\'s halal status, even if weak.',
            ),
            new ChoiceQuestion(
                'claim_vs_listing',
                'Compare `evidence.claim` with what the listing in `context.listing` suggests (name, category, cuisines, menu). Remember the listing may be incomplete or wrong.',
                [
                    'consistent' => 'Nothing in the listing conflicts with the claim.',
                    'contradicts' => 'The listing clearly conflicts with the claim (e.g. claim halal but the menu lists pork dishes; claim non-halal for a nasi kandar stall with no such signs).',
                    'unclear' => 'The listing has too little information to tell.',
                ],
            ),
        ];
    }

    /** @param array{submission: RestaurantSubmission, restaurant: Restaurant, photoTypes: list<string>} $context */
    public function buildState(array $context): array
    {
        $s = $context['submission'];
        $r = $context['restaurant'];
        $menu = RestaurantMenuItem::where('restaurant_id', $r->id)->orderBy('sort_order')->limit($this->limit('max_menu_items'))->pluck('name')->all();

        return [
            'evidence' => array_filter([
                'claim' => $s->halal_claim?->value,
                'comment' => StateTrimmer::text($s->halal_comment, $this->limit('max_comment_chars')),
                'certificate_authority' => $s->certification_authority?->value,
                'certificate_number_provided' => filled($s->certificate_number),
                'certificate_expires_at' => $s->certificate_expires_at?->toDateString(),
                'attached_photo_types' => StateTrimmer::list($context['photoTypes'], $this->limit('max_list_items')),
            ], fn ($v) => $v !== null),
            'context' => [
                'listing' => array_filter([
                    'name' => StateTrimmer::text($r->name, 120),
                    'category' => $r->food_category,
                    'cuisines' => $r->cuisines()->pluck('slug')->take($this->limit('max_list_items'))->values()->all(),
                    'signature_dish' => StateTrimmer::text($r->signature_dish, 120),
                    'menu_items' => array_map(fn ($n) => StateTrimmer::text($n, 80), $menu),
                ], fn ($v) => $v !== null && $v !== []),
                'current_halal_status' => $r->effectiveHalalStatus()->value,
            ],
        ];
    }

    /**
     * Truth from the admin decision. Approximate by design — calibration compares trends, it
     * does not grade individual answers. Rejections say nothing about certificate/pork mentions.
     */
    public function calibrationTargets(array $outcome): array
    {
        $decision = $outcome['decision'] ?? null;
        $resolved = $outcome['resolved'] ?? null;
        $claim = $outcome['claim'] ?? null;

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            return [];
        }

        $targets = [
            'is_spam_or_irrelevant' => $decision === 'rejected',
            'supports_claim' => $decision === 'approved' && $resolved === $claim,
        ];
        if ($decision === 'approved') {
            $targets['mentions_certificate'] = $resolved === 'certified';
            $targets['mentions_pork_alcohol'] = $resolved === 'non_halal';
        }

        return $targets;
    }
}
