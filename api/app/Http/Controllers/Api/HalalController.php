<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantOwner;
use App\Models\RestaurantSubmission;
use App\Services\Halal\HalalPresenter;
use App\Services\Halal\HalalReportService;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * User-facing halal trust endpoints: open a halal report (evidence), claim ownership, and read
 * a restaurant's public verification history. Reports/claims are community submissions and go
 * through the same draft -> submit -> moderation flow as everything else (photos via
 * community/submissions/{id}/photos, submit via community/submissions/{id}/submit).
 */
class HalalController extends Controller
{
    public function __construct(
        private readonly HalalReportService $reports,
        private readonly HalalPresenter $presenter,
    ) {}

    public function storeReport(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'claim' => ['required', Rule::in(array_map(fn (HalalStatus $s) => $s->value, HalalStatus::claimable()))],
            'comment' => ['nullable', 'string', 'max:1000'],
            'certificationAuthority' => ['nullable', Rule::enum(CertificationAuthority::class)],
            'certificateNumber' => ['nullable', 'string', 'max:60'],
            'certificateIssuedAt' => ['nullable', 'date'],
            'certificateExpiresAt' => ['nullable', 'date'],
        ]);
        if (isset($data['comment'])) {
            $data['comment'] = trim($data['comment']) ?: null;
        }

        $submission = $this->reports->open($request->user(), $restaurant, $data);

        return response()->json(['submission' => $this->presentReport($submission)], $submission->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Ownership proof goes to moderation as an `owner_claim` submission; the proof photo
     * (business licence / storefront) is uploaded to it like any other submission photo.
     */
    public function storeOwnerClaim(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'contactPhone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        $restaurant = $restaurant->canonicalRestaurant();

        abort_if(RestaurantOwner::isVerifiedOwner($user->id, $restaurant->id), 422, 'You are already the verified owner of this place.');

        $open = RestaurantSubmission::where('user_id', $user->id)->where('restaurant_id', $restaurant->id)
            ->where('submission_type', 'owner_claim')->whereIn('status', HalalReportService::OPEN_STATUSES)->first();
        abort_if($open && $open->status !== 'draft', 422, 'You already have an ownership claim open for this place.');

        // A draft left by a failed proof upload is resumed, not a 7-day lockout until it's pruned.
        if ($open) {
            $open->update([
                'contact_phone' => trim($data['contactPhone']),
                'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            ]);

            return response()->json(['submission' => ['id' => $open->id, 'submissionType' => 'owner_claim', 'status' => 'draft']]);
        }

        $submission = RestaurantSubmission::create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'submission_type' => 'owner_claim',
            'source_type' => 'manual',
            'name' => $restaurant->name,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'location_source' => 'current_location',
            'changed_fields' => [],
            'contact_phone' => trim($data['contactPhone']),
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'status' => 'draft',
        ]);

        return response()->json(['submission' => ['id' => $submission->id, 'submissionType' => 'owner_claim', 'status' => 'draft']], 201);
    }

    public function history(Restaurant $restaurant): JsonResponse
    {
        $page = RestaurantHalalVerification::where('restaurant_id', $restaurant->id)
            ->with('certificate:id,authority')
            ->latest('effective_from')->latest('id')
            ->paginate(20);

        return response()->json([
            'entries' => collect($page->items())->map(fn ($v) => $this->presenter->historyEntry($v)),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    private function presentReport(RestaurantSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'restaurantId' => $submission->restaurant_id,
            'submissionType' => 'halal_report',
            'status' => $submission->status,
            'halalClaim' => $submission->halal_claim?->value,
            'halalComment' => $submission->halal_comment,
            'certificationAuthority' => $submission->certification_authority?->value,
            'certificateExpiresAt' => $submission->certificate_expires_at?->toDateString(),
            'reviewNote' => $submission->review_note,
        ];
    }
}
