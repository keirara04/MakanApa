<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickAddRestaurantPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // CI's .env points these at Spaces with no credentials — never touch a real bucket.
        Storage::fake(config('restaurant_photos.pending_disk'));
        Storage::fake(config('restaurant_photos.public_disk'));
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'FST Diners',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'places/fake-id',
        ]);
    }

    public function test_quick_add_creates_a_pending_submission_with_the_photo(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);
        $restaurant = $this->restaurant();

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->postJson("/api/v1/restaurants/{$restaurant->id}/photos/quick-add", [
            'photo' => $file,
        ])->assertStatus(201);

        $photoId = $response->json('photo.id');
        $photo = RestaurantPhoto::find($photoId);

        $this->assertNotNull($photo);
        // Still pending moderation, same as every other community photo — quick-add closes the
        // UX gap, not the approval gate.
        $this->assertNull($photo->restaurant_id);

        $submission = RestaurantSubmission::find($photo->restaurant_submission_id);
        $this->assertSame('edit_place', $submission->submission_type);
        $this->assertSame($restaurant->id, $submission->restaurant_id);
        $this->assertSame($restaurant->name, $submission->name);
        $this->assertSame('pending', $submission->status);
        $this->assertSame($user->id, $submission->user_id);
    }

    public function test_quick_add_rejects_a_non_image_file(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);
        $restaurant = $this->restaurant();

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/photos/quick-add", [
            'photo' => $file,
        ])->assertStatus(422);
    }

    public function test_quick_add_404s_for_an_unknown_restaurant(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->postJson('/api/v1/restaurants/999999/photos/quick-add', [
            'photo' => $file,
        ])->assertStatus(404);
    }
}
