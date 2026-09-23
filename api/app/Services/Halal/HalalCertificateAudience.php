<?php

namespace App\Services\Halal;

use App\Models\RestaurantHalalCertificate;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantOwner;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who hears about a certificate's lifecycle: verified owners + contributors whose reports established it. */
class HalalCertificateAudience
{
    /** @return Collection<int, User> */
    public function for(RestaurantHalalCertificate $certificate): Collection
    {
        $ownerIds = RestaurantOwner::where('restaurant_id', $certificate->restaurant_id)
            ->where('status', 'verified')
            ->pluck('user_id');

        $contributorIds = RestaurantHalalVerification::where('certificate_id', $certificate->id)
            ->whereNotNull('submission_id')
            ->with('submission:id,user_id')
            ->get()
            ->pluck('submission.user_id')
            ->filter();

        return User::whereIn('id', $ownerIds->merge($contributorIds)->unique())->get();
    }
}
