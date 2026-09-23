<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Services\Halal\HalalReportService;
use App\Services\Halal\Registry\HalalRegistry;
use App\Services\RestaurantPhotoPromotionService;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\CertificateVerificationMethod;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\TriageBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Thin HTTP wrapper around RestaurantSubmissionModerationService — all actual moderation logic
 * (approve/link/reject/requestChanges/releaseFieldOverride/remove, plus the duplicate-place hint)
 * lives in the service so the Filament admin panel can call the exact same code path and never
 * diverge from what this JSON API does.
 */
class RestaurantSubmissionController extends Controller
{
    public function __construct(private readonly RestaurantSubmissionModerationService $moderation) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        // `type=halal_report` powers the iOS halal review queue — highest priority first.
        $submissions = RestaurantSubmission::where('status', $status)
            ->when($request->query('type'), fn ($q, $type) => $q->where('submission_type', $type)->orderByDesc('review_priority'))
            ->with(['user', 'university', 'restaurant'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'submissions' => $submissions->map(fn (RestaurantSubmission $s) => $this->presentForAdmin($s)),
        ]);
    }

    public function photos(RestaurantSubmission $submission): JsonResponse
    {
        $photos = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->get();

        return response()->json([
            'photos' => $photos->map(fn (RestaurantPhoto $photo) => [
                'id' => $photo->id,
                'photoType' => $photo->photo_type,
                'url' => URL::temporarySignedRoute('admin.submission-photos.show', now()->addMinutes(30), ['photo' => $photo->id]),
            ]),
        ]);
    }

    public function approve(Request $request, RestaurantSubmission $submission, RestaurantPhotoPromotionService $photoPromotion): JsonResponse
    {
        $halal = $request->validate([
            'resolvedStatus' => ['nullable', Rule::in(['certified', 'muslim_friendly', 'non_halal'])],
            'evidenceSummary' => ['nullable', 'string', 'max:500'],
            'certificate' => ['nullable', 'array'],
            'certificate.authority' => ['nullable', Rule::enum(CertificationAuthority::class)],
            'certificate.certificateNumber' => ['nullable', 'string', 'max:60'],
            'certificate.expiresAt' => ['nullable', 'date'],
            'certificate.issuedAt' => ['nullable', 'date'],
            'certificate.verificationMethod' => ['nullable', Rule::enum(CertificateVerificationMethod::class)],
            'certificate.confirmed' => ['nullable', 'boolean'],
        ]);

        $restaurant = $this->moderation->approve($submission, $request->user(), $request->boolean('releaseToGoogle'), array_filter([
            'resolved_status' => $halal['resolvedStatus'] ?? null,
            'evidence_summary' => $halal['evidenceSummary'] ?? null,
            'certificate' => isset($halal['certificate']) ? [
                'authority' => $halal['certificate']['authority'] ?? null,
                'certificate_number' => $halal['certificate']['certificateNumber'] ?? null,
                'expires_at' => $halal['certificate']['expiresAt'] ?? null,
                'issued_at' => $halal['certificate']['issuedAt'] ?? null,
                'verification_method' => $halal['certificate']['verificationMethod'] ?? null,
                'confirmed' => $halal['certificate']['confirmed'] ?? false,
            ] : null,
        ]));

        // Filesystem work happens outside the DB transaction on purpose — see
        // RestaurantPhotoPromotionService's doc comment for why.
        // Owner-claim proof (licence, IC) is private evidence — never promoted to public photos.
        if ($submission->submission_type !== 'owner_claim') {
            $photoPromotion->promote($submission->fresh(), $restaurant);
        }

        return response()->json(['approved' => true, 'restaurantId' => $restaurant->id]);
    }

    public function link(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['restaurantId' => ['required', 'integer', 'exists:restaurants,id']]);

        $this->moderation->link($submission, $data['restaurantId'], $request->user());

        return response()->json(['linked' => true]);
    }

    public function reject(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['reviewNote' => ['required', 'string', 'max:500']]);

        $this->moderation->reject($submission, $data['reviewNote'], $request->user());

        return response()->json(['rejected' => true]);
    }

    public function requestChanges(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['reviewNote' => ['required', 'string', 'max:500']]);

        $this->moderation->requestChanges($submission, $data['reviewNote'], $request->user());

        return response()->json(['changesRequested' => true]);
    }

    public function releaseFieldOverride(Request $request, Restaurant $restaurant, string $field): JsonResponse
    {
        $this->moderation->releaseFieldOverride($restaurant, $field, $request->user());

        return response()->json(['released' => true]);
    }

    public function remove(Request $request, Restaurant $restaurant): JsonResponse
    {
        $this->moderation->remove($restaurant, $request->user());

        return response()->json(['removed' => true]);
    }

    private function presentForAdmin(RestaurantSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'submissionType' => $submission->submission_type,
            'sourceType' => $submission->source_type,
            'name' => $submission->name,
            'address' => $submission->address,
            'foodCategory' => $submission->food_category,
            'priceLevel' => $submission->price_level,
            'phone' => $submission->phone,
            'instagramHandle' => $submission->instagram_handle,
            'tiktokHandle' => $submission->tiktok_handle,
            'websiteUrl' => $submission->website_url,
            'menuItems' => $submission->menu_items,
            'changedFields' => $submission->changed_fields ?? [],
            'latitude' => (float) $submission->latitude,
            'longitude' => (float) $submission->longitude,
            'notes' => $submission->notes,
            'status' => $submission->status,
            'restaurantId' => $submission->restaurant_id,
            'submitter' => [
                'email' => $submission->user?->email,
                'affiliationType' => $submission->university_id ? 'university' : 'public',
                'university' => $submission->university?->short_name,
            ],
            'possibleDuplicate' => $this->moderation->duplicateHint($submission),
            'halal' => $submission->isHalalReport() ? [
                'claim' => $submission->halal_claim?->value,
                'comment' => $submission->halal_comment,
                'certificationAuthority' => $submission->certification_authority?->value,
                'certificateNumber' => $submission->certificate_number,
                'certificateExpiresAt' => $submission->certificate_expires_at?->toDateString(),
                'currentStatus' => $submission->restaurant?->effectiveHalalStatus()->value,
                'reviewPriority' => $submission->review_priority,
                'duplicatePhoto' => app(HalalReportService::class)->hasDuplicatePhoto($submission),
                'registryUrl' => app(HalalRegistry::class)->directoryUrl($submission->certification_authority),
                // Advisory only — the admin still decides.
                'aiBadges' => array_column(TriageBadges::badges($submission->triage), 'label'),
                'priorityExplanation' => TriageBadges::priorityExplanation($submission->review_priority_breakdown),
            ] : null,
            'contactPhone' => $submission->submission_type === 'owner_claim' ? $submission->contact_phone : null,
            'createdAt' => $submission->created_at?->toIso8601String(),
        ];
    }
}
