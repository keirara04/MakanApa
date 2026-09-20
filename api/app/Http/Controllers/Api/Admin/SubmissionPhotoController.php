<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Pending photos live on the private disk (never web-reachable directly) — this is the only
 * way an admin can view one before approval, and only via a short-lived signed URL (see
 * Admin\RestaurantSubmissionController::photos(), routes/api.php `signed` middleware).
 */
class SubmissionPhotoController extends Controller
{
    public function __invoke(RestaurantPhoto $photo): Response
    {
        $contents = Storage::disk($photo->disk)->get($photo->path);
        abort_if($contents === null, 404);

        return response($contents, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }
}
