<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AdminTimezone;
use App\Support\NotificationCategory;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'avatar_key', 'password', 'role', 'status', 'apple_sub', 'google_sub', 'apple_refresh_token', 'notification_preferences', 'display_timezone', 'halal_preference', 'trusted_contributor', 'contribution_stats'])]
#[Hidden(['password', 'remember_token', 'apple_refresh_token'])]
class User extends Authenticatable implements FilamentUser, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'apple_refresh_token' => 'encrypted',
            'notification_preferences' => 'array',
            'halal_preference' => 'boolean',
            'trusted_contributor' => 'boolean',
            'contribution_stats' => 'array',
        ];
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** Required by laravel-notification-channels/apn — routes a `->notify()` call to this user's live device tokens. */
    public function routeNotificationForApn(): array
    {
        return $this->deviceTokens()
            ->whereNull('invalidated_at')
            ->pluck('token')
            ->all();
    }

    public function mealNudgeState(): HasOne
    {
        return $this->hasOne(MealNudgeState::class);
    }

    public function wantsNotification(string $category): bool
    {
        $preferences = $this->notification_preferences ?? [];

        return $preferences[$category] ?? in_array($category, NotificationCategory::DEFAULT_TRUE, strict: true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** Filament-only display preference — see AdminTimezone. Never null in practice past this getter. */
    public function displayTimezone(): string
    {
        return $this->display_timezone ?? AdminTimezone::DEFAULT;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperadmin() && $this->isActive();
    }

    public function hasEmailAuthentication(): bool
    {
        return $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->has_email_authentication = $condition;
        $this->save();
    }

    public function affiliation(): HasOne
    {
        return $this->hasOne(UserAffiliation::class);
    }

    public function universityId(): ?int
    {
        return $this->affiliation?->university_id;
    }

    public function universityShortName(): ?string
    {
        return $this->affiliation?->university?->short_name;
    }

    public function areaId(): ?int
    {
        return $this->affiliation?->area_id;
    }

    public function areaShortName(): ?string
    {
        return $this->affiliation?->area?->short_name;
    }
}
