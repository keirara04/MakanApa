<?php

namespace App\Services;

use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Every upload is decoded and re-encoded through GD before it ever touches disk — regardless of
 * what the client already did. GD's re-encode strips EXIF as a side effect (no GPS/device
 * metadata persisted) and guarantees the stored file is a format GD itself produced, not
 * whatever the client sent. iOS re-encodes HEIC to JPEG before upload as defense in depth, but
 * this service never assumes that happened.
 */
class RestaurantPhotoUploadService
{
    public function storePending(RestaurantSubmission $submission, UploadedFile $file, string $photoType, int $uploadedBy): RestaurantPhoto
    {
        // Hash of the ORIGINAL bytes (pre-re-encode) — re-encoding is deterministic per input,
        // but hashing the upload itself is what flags the same evidence image submitted twice.
        $contentHash = hash_file('sha256', $file->getRealPath());
        [$reencoded, $width, $height] = $this->reencode($file->getRealPath());

        $disk = Config::get('restaurant_photos.pending_disk');
        $path = "restaurant-submissions/{$submission->id}/".Str::uuid().'.jpg';
        // Disks are configured `throw => false` — a failed write only shows up as `false`.
        if (! Storage::disk($disk)->put($path, $reencoded)) {
            throw new RuntimeException("Could not store photo on the {$disk} disk.");
        }

        return RestaurantPhoto::create([
            'restaurant_submission_id' => $submission->id,
            'restaurant_id' => null,
            'disk' => $disk,
            'path' => $path,
            'photo_type' => $photoType,
            'width' => $width,
            'height' => $height,
            'size_bytes' => strlen($reencoded),
            'uploaded_by' => $uploadedBy,
            'content_hash' => $contentHash,
        ]);
    }

    /** @return array{0: string, 1: int, 2: int} [jpegBytes, width, height] */
    private function reencode(string $path): array
    {
        $maxDimension = (int) Config::get('restaurant_photos.max_dimension_px', 1600);

        $image = @imagecreatefromstring(file_get_contents($path));
        // Passed the `image` rule but GD can't read it (truncated/odd encoding) — a 422 the app
        // can show, not a 500 it reads as a dropped connection.
        if ($image === false) {
            throw ValidationException::withMessages(['photo' => "We couldn't read that photo. Try a different one."]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $maxDimension / max($width, $height));

        if ($scale < 1.0) {
            $newWidth = (int) round($width * $scale);
            $newHeight = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        ob_start();
        imagejpeg($image, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return [$bytes, $width, $height];
    }
}
