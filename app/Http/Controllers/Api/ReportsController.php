<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    /**
     * Materials at or below their minimum stock level.
     */
    public function lowStock(ReportRequest $request): JsonResponse
    {
        $materials = $this->reports->lowStock($request->filters());

        return response()->json([
            'data' => $materials,
            'meta' => ['total' => $materials->count()],
        ]);
    }

    /**
     * Posted sales totals and per-day breakdown for a period.
     */
    public function salesSummary(ReportRequest $request): JsonResponse
    {
        $period = $request->validate($request->periodRules(), $request->messages());

        return response()->json([
            'data' => $this->reports->salesSummary($period['from'], $period['to']),
        ]);
    }

    /**
     * Posted purchase totals and per-day breakdown for a period.
     */
    public function purchasesSummary(ReportRequest $request): JsonResponse
    {
        $period = $request->validate($request->periodRules(), $request->messages());

        return response()->json([
            'data' => $this->reports->purchasesSummary($period['from'], $period['to']),
        ]);
    }

    /**
     * Profit & Loss and profitability summary for a period.
     */
    public function profitSummary(ReportRequest $request): JsonResponse
    {
        $period = $request->validate($request->periodRules(), $request->messages());

        return response()->json([
            'data' => $this->reports->profitSummary($period['from'], $period['to']),
        ]);
    }

    /**
     * Outstanding customer balances (what the company is owed).
     */
    public function receivables(ReportRequest $request): JsonResponse
    {
        return $this->respondWithParties($this->reports->receivables($request->filters()));
    }

    /**
     * Outstanding supplier balances (what the company owes).
     */
    public function payables(ReportRequest $request): JsonResponse
    {
        return $this->respondWithParties($this->reports->payables($request->filters()));
    }

    /**
     * محاسبة خبراء الصيانة: صف لكل خبير بعمولته ومصروفه ورصيده.
     */
    public function technicianSummary(ReportRequest $request): JsonResponse
    {
        return $this->respondWithParties($this->reports->technicianSummary($request->filters()));
    }

    /**
     * تفصيل بلاغات الخبراء المسوّاة، لمراجعة احتساب العمولة بلاغاً بلاغاً.
     */
    public function technicianRepairs(ReportRequest $request): JsonResponse
    {
        return $this->respondWithParties($this->reports->technicianRepairs($request->filters()));
    }

    /**
     * Stock movement history, with a running quantity when a single material
     * is selected.
     */
    public function stockMovements(ReportRequest $request): JsonResponse
    {
        ['rows' => $rows, 'running' => $running, 'paginator' => $paginator] = $this->reports
            ->stockMovements($request->filters());

        return response()->json([
            'data' => ['rows' => $rows, 'has_running_quantity' => $running],
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array{rows: mixed, totals: array<string, string>, paginator: LengthAwarePaginator}  $result
     */
    protected function respondWithParties(array $result): JsonResponse
    {
        return response()->json([
            'data' => [
                'rows' => $result['rows'],
                'totals' => $result['totals'],
            ],
            'meta' => $this->paginationMeta($result['paginator']),
        ]);
    }

    /**
     * @return array<string, int|null>
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
