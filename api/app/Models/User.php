<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'avatar_url', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
