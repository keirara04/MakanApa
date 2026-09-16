<?php

namespace Tests\Unit\Services\Craving;

use App\Services\Craving\OpenRouterIntentParser;
use App\Support\FoodTaxonomy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterIntentParserTest extends TestCase
{
    private function parser(): OpenRouterIntentParser
    {
        return new OpenRouterIntentParser('fake-api-key', 'google/gemma-4-26b-a4b-it-20260403:free');
    }

    private function fakeChatResponse(array $content): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($content)]],
                ],
            ], 200),
        ]);
    }

    public function test_valid_response_with_allowed_category_resolves_via_taxonomy_search_terms(): void
    {
        $this->fakeChatResponse(['category' => 'dessert', 'subcategory' => 'ice_cream', 'confidence' => 0.92]);

        $intent = $this->parser()->parse('nak benda sejuk manis', null);

        $this->assertNotNull($intent);
        $this->assertSame('ice_cream', $intent->concept);
        $this->assertSame('ai', $intent->source);
        $this->assertSame(FoodTaxonomy::searchTermsFor('ice_cream'), $intent->searchTerms);
        $this->assertSame(0.92, $intent->confidence);
    }

    public function test_confidence_above_1_is_clamped_to_1(): void
    {
        $this->fakeChatResponse(['category' => 'dessert', 'subcategory' => 'ice_cream', 'confidence' => 200]);

        $intent = $this->parser()->parse('nak ice cream', null);

        $this->assertSame(1.0, $intent->confidence);
    }

    public function test_negative_confidence_is_clamped_to_0(): void
    {
        $this->fakeChatResponse(['category' => 'dessert', 'subcategory' => 'ice_cream', 'confidence' => -5]);

        $intent = $this->parser()->parse('nak ice cream', null);

        $this->assertSame(0.0, $intent->confidence);
    }

    public function test_invented_category_is_dropped_and_falls_back_to_local_hint(): void
    {
        $this->fakeChatResponse(['category' => 'super_amazing_food', 'confidence' => 0.99]);

        $hint = FoodTaxonomy::resolve('burger');
        $intent = $this->parser()->parse('something burgery', $hint);

        $this->assertNotNull($intent);
        $this->assertSame('burger', $intent->concept);
        $this->assertSame('taxonomy', $intent->source); // fell back to the hint, not the invented category
    }

    public function test_invented_category_with_no_hint_returns_null(): void
    {
        $this->fakeChatResponse(['category' => 'super_amazing_food', 'confidence' => 0.99]);

        $intent = $this->parser()->parse('something weird', null);

        $this->assertNull($intent);
    }

    public function test_malformed_json_returns_null(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'not json at all {{{']]],
            ], 200),
        ]);

        $this->assertNull($this->parser()->parse('anything', null));
    }

    public function test_non_200_response_returns_null(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $this->assertNull($this->parser()->parse('anything', null));
    }

    public function test_connection_failure_returns_null_never_throws(): void
    {
        Http::fake(['openrouter.ai/*' => fn () => throw new ConnectionException('timed out')]);

        $this->assertNull($this->parser()->parse('anything', null));
    }
}
