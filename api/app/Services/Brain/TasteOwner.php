<?php

namespace App\Services\Brain;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Who a profile/event belongs to: the signed-in user, else the anonymous installation. */
final readonly class TasteOwner
{
    public function __construct(
        public ?int $userId,
        public ?string $installationId,
    ) {}

    public static function resolve(?User $user, ?string $installationId): ?self
    {
        // An unsaved user (no id) can't own anything — treat it as anonymous.
        $userId = $user?->getKey();
        if ($userId === null && ($installationId === null || $installationId === '')) {
            return null;
        }

        return new self($userId, $installationId ?: null);
    }

    /** Scope a taste_events/taste_profiles/decisions query to this owner. */
    public function scope(Builder $query): Builder
    {
        return $this->userId !== null
            ? $query->where('user_id', $this->userId)
            : $query->whereNull('user_id')->where('installation_id', $this->installationId);
    }

    public function profileKey(): array
    {
        return $this->userId !== null ? ['user_id' => $this->userId] : ['installation_id' => $this->installationId];
    }
}
