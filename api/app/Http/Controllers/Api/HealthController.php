<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'unreachable';
        }

        return response()->json([
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'environment' => config('app.env'),
            'database' => $database,
        ], $database === 'ok' ? 200 : 503);
    }
}
