<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TasteProfile;
use App\Services\Brain\ContextEngine;
use App\Services\Brain\SeleraTraits;
use App\Services\Brain\TasteEventRecorder;
use App\Services\Brain\TasteOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Your Selera" — the visible, editable side of Makan Brain, plus the "Right now" context
 * strip. Every correction is an explicit, high-authority taste event (never an in-place edit),
 * and Reset is a boundary event: history stays auditable, replay just starts after it.
 */
class SeleraController extends Controller
{
    public function __construct(
        private readonly SeleraTraits $traits,
        private readonly TasteEventRecorder $recorder,
        private readonly ContextEngine $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $owner = TasteOwner::resolve($request->user(), null);
        $profile = TasteProfile::query()->where($owner->profileKey())->first();

        return response()->json($this->traits->describe($profile, $owner, $request->user()));
    }

    public function feedback(Request $request, string $trait): JsonResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['not_really', 'more', 'less'])]]);
        abort_unless(preg_match('/^[a-z_]+:[a-z0-9_:\-]+$/', $trait) === 1, 422, 'Unknown trait.');

        $owner = TasteOwner::resolve($request->user(), null);
        $this->recorder->traitFeedback($owner, $trait, $data['kind']);

        return $this->show($request);
    }

    public function mute(Request $request, string $trait): JsonResponse
    {
        abort_unless(preg_match('/^[a-z_]+:[a-z0-9_:\-]+$/', $trait) === 1, 422, 'Unknown trait.');

        $this->recorder->traitMute(TasteOwner::resolve($request->user(), null), $trait);

        return $this->show($request);
    }

    public function reset(Request $request): JsonResponse
    {
        $this->recorder->reset(TasteOwner::resolve($request->user(), null));

        return $this->show($request);
    }

    /** The "Right now ☔ Hujan · 🌙 Supper" strip — cache reads only. */
    public function context(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'ignore' => ['nullable', 'array'],
            'ignore.*' => ['string', Rule::in(ContextEngine::SIGNALS)],
        ]);

        $snapshot = $this->context->resolve(
            (float) $data['latitude'], (float) $data['longitude'], (bool) $request->user()?->halal_preference, false, $data['ignore'] ?? [],
        );

        return response()->json([
            'mealSlot' => $snapshot->mealSlot,
            'signals' => ContextEngine::present($snapshot),
        ]);
    }
}
