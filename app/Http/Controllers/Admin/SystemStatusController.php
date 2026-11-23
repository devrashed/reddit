<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemStatusService;
use Illuminate\Http\JsonResponse;

class SystemStatusController extends Controller
{
    /**
     * Return a JSON snapshot of the application health.
     */
    public function __invoke(SystemStatusService $status): JsonResponse
    {
        return response()->json($status->snapshot());
    }
}
