<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatementRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Technician;
use App\Services\StatementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class StatementsController extends Controller
{
    public function __construct(protected StatementService $statements) {}

    /**
     * Customer ledger statement: opening balance, paginated rows with a
     * running balance, period totals and closing balance.
     */
    public function customer(StatementRequest $request, Customer $customer): JsonResponse
    {
        ['statement' => $statement, 'paginator' => $paginator] = $this->statements
            ->forCustomer($customer, $request->filters());

        return $this->respond($statement, $paginator);
    }

    /**
     * Supplier ledger statement, same shape as the customer statement.
     */
    public function supplier(StatementRequest $request, Supplier $supplier): JsonResponse
    {
        ['statement' => $statement, 'paginator' => $paginator] = $this->statements
            ->forSupplier($supplier, $request->filters());

        return $this->respond($statement, $paginator);
    }

    /**
     * كشف حساب خبير صيانة، بنفس شكل كشف المورد.
     */
    public function technician(StatementRequest $request, Technician $technician): JsonResponse
    {
        ['statement' => $statement, 'paginator' => $paginator] = $this->statements
            ->forTechnician($technician, $request->filters());

        return $this->respond($statement, $paginator);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $statement
     */
    protected function respond(array $statement, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $statement,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
