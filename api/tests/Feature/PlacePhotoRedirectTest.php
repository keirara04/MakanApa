<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PlacePhotoRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO_NAME = 'places/abc123/photos/xyz789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    private function signedPhotoUrl(): string
    {
        return URL::temporarySignedRoute('places.photo', now()->addMinutes(30), ['name' => self::PHOTO_NAME]);
    }

    public function test_photo_redirects_to_googles_public_photo_uri(): void
    {
        Http::fake(['*/media*' => Http::response(['name' => self::PHOTO_NAME, 'photoUri' => 'https://lh3.googleusercontent.com/p/photo=w800'])]);

        $this->get($this->signedPhotoUrl())
            ->assertRedirect('https://lh3.googleusercontent.com/p/photo=w800');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'skipHttpRedirect=true'));
    }

    public function test_photo_returns_502_when_google_fails(): void
    {
        Http::fake(['*/media*' => Http::response(['error' => 'gone'], 404)]);

        $this->get($this->signedPhotoUrl())->assertStatus(502);
    }

    public function test_photo_returns_502_when_google_sends_no_https_uri(): void
    {
        Http::fake(['*/media*' => Http::response(['photoUri' => 'javascript:alert(1)'])]);

        $this->get($this->signedPhotoUrl())->assertStatus(502);
    }

    public function test_unsigned_photo_url_is_rejected(): void
    {
        Http::fake();

        $this->get('/api/v1/places/photo?name='.urlencode(self::PHOTO_NAME))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_reopening_a_places_details_reuses_the_recent_google_details_call(): void
    {
        Http::fake(['*places.googleapis.com/v1/places/*' => Http::response([
            'photos' => [['name' => self::PHOTO_NAME]],
            'reviews' => [],
            'googleMapsUri' => 'https://maps.google.com/?cid=1',
        ])]);
        $restaurant = Restaurant::create([
            'name' => 'Warung Detail', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'provider' => 'google', 'provider_place_id' => 'abc123', 'is_active' => true,
        ]);

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertOk()->assertJsonCount(1, 'photos');
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertOk()->assertJsonCount(1, 'photos');

        Http::assertSentCount(1);
    }
}
