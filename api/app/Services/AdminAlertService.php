<?php

namespace App\Services;

use App\Filament\Resources\CommunityPosts\CommunityPostResource;
use App\Filament\Resources\CommunityRequests\CommunityRequestResource;
use App\Filament\Resources\RestaurantSubmissions\RestaurantSubmissionResource;
use App\Models\CommunityPost;
use App\Models\CommunityRequest;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The admin panel's notification bell: new moderation work lands here the moment it's created,
 * so nobody has to keep a badge-count tab open. Filament database notifications only — they're
 * excluded from the app's own inbox (NotificationController), since superadmins are app users too.
 */
class AdminAlertService
{
    /** @return Collection<int, User> */
    public function superadmins(): Collection
    {
        return User::where('role', 'superadmin')->where('status', 'active')->get();
    }

    public function postReported(CommunityPost $post, bool $autoHidden): void
    {
        $this->send(
            $autoHidden ? 'Reported post auto-hidden' : 'Community post reported',
            Str::limit($post->body, 120),
            CommunityPostResource::getUrl('view', ['record' => $post], panel: 'admin'),
            $autoHidden ? 'danger' : 'warning',
        );
    }

    public function submissionPending(RestaurantSubmission $submission): void
    {
        $title = match ($submission->submission_type) {
            'halal_report' => 'New halal report',
            'owner_claim' => 'New owner claim',
            'new_place' => 'New place submitted',
            default => 'Place change submitted',
        };

        $this->send(
            $title,
            $submission->name,
            RestaurantSubmissionResource::getUrl('view', ['record' => $submission], panel: 'admin'),
        );
    }

    public function communityRequestCreated(CommunityRequest $request): void
    {
        $this->send(
            'New community request',
            ucfirst($request->type).': '.$request->name,
            CommunityRequestResource::getUrl('index', panel: 'admin'),
            'info',
        );
    }

    public function send(string $title, ?string $body, string $url, string $status = 'warning'): void
    {
        $admins = $this->superadmins();
        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->actions([
                Action::make('open')->label('Open')->url($url)->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }
}
