<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A filesystem move can't roll back the way a Postgres transaction can, so promotion is its own
 * small pipeline rather than being inlined into the submission-approval transaction: copy first
 * (source untouched), verify, commit the DB change, only then delete the source — and clean up
 * the new copy if the DB step fails, so a partial failure never leaves an orphaned "public file,
 * DB thinks it's private" or "DB thinks it's public, file never arrived" state.
 */
class RestaurantPhotoPromotionService
{
    public function promote(RestaurantSubmission $submission, Restaurant $restaurant): void
    {
        $photos = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->get();
        if ($photos->isEmpty()) {
            return;
        }

        $publicDisk = Config::get('restaurant_photos.public_disk');
        $copied = [];

        foreach ($photos as $photo) {
            $contents = Storage::disk($photo->disk)->get($photo->path);
            // Source gone (pending disk wiped by a redeploy, or pruned) — `get` returns null on
            // these non-throwing disks, and copying that would publish an empty file. Drop the
            // dead row instead of blocking the approval on a photo nobody can see anyway.
            if ($contents === null) {
                Log::warning('Pending photo missing at promotion, dropping it', ['photo' => $photo->id, 'disk' => $photo->disk, 'path' => $photo->path]);
                $photo->delete();

                continue;
            }

            $newPath = "restaurants/{$restaurant->id}/".Str::uuid().'.jpg';
            Storage::disk($publicDisk)->put($newPath, $contents);

            if (Storage::disk($publicDisk)->size($newPath) !== strlen($contents)) {
                $this->cleanUp($publicDisk, array_column($copied, 'path'));
                throw new \RuntimeException("Photo promotion verification failed for photo {$photo->id}.");
            }

            $copied[] = ['photo' => $photo, 'oldDisk' => $photo->disk, 'oldPath' => $photo->path, 'path' => $newPath];
        }

        try {
            DB::transaction(function () use ($copied, $restaurant, $publicDisk) {
                foreach ($copied as $entry) {
                    $entry['photo']->update([
                        'disk' => $publicDisk,
                        'path' => $entry['path'],
                        'restaurant_id' => $restaurant->id,
                    ]);
                }
            });
        } catch (Throwable $e) {
            $this->cleanUp($publicDisk, array_column($copied, 'path'));
            Log::error('Photo promotion DB update failed, cleaned up orphaned public copies', ['error' => $e->getMessage()]);
            throw $e;
        }

        foreach ($copied as $entry) {
            Storage::disk($entry['oldDisk'])->delete($entry['oldPath']);
        }
    }

    private function cleanUp(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
