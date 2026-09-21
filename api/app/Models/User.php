<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'password', 'role', 'status', 'apple_sub', 'google_sub', 'apple_refresh_token'])]
#[Hidden(['password', 'remember_token', 'apple_refresh_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'apple_refresh_token' => 'encrypted',
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperadmin() && $this->isActive();
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
