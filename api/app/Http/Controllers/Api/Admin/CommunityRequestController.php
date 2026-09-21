<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityRequest;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityRequestController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $requests = CommunityRequest::where('status', $status)
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(fn (CommunityRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'name' => $r->name,
                'status' => $r->status,
                'createdAt' => $r->created_at?->toIso8601String(),
                'requesterEmail' => $r->user?->email,
            ]);

        return response()->json(['requests' => $requests]);
    }

    public function resolve(CommunityRequest $request): JsonResponse
    {
        $request->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->auditLogger->log(auth()->user(), 'community_request.resolve', $request);

        return response()->json(['resolved' => true]);
    }

    public function dismiss(CommunityRequest $request): JsonResponse
    {
        $request->update(['status' => 'dismissed', 'resolved_at' => now()]);
        $this->auditLogger->log(auth()->user(), 'community_request.dismiss', $request);

        return response()->json(['dismissed' => true]);
    }
}
