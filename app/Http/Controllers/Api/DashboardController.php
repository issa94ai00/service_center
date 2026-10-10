<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    /**
     * Everything the dashboard renders, in one request.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'compare' => ['nullable', 'boolean'],
        ], [
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويًا له.',
        ]);

        return response()->json([
            'data' => $this->dashboard->build(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $request->boolean('compare'),
                $request->user(),
            ),
        ]);
    }
}
