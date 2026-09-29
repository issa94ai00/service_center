<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierStoreRequest;
use App\Http\Requests\SupplierUpdateRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SuppliersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where(function (Builder $q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('company', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->withAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return SupplierResource::collection($suppliers);
    }

    public function store(SupplierStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        /** @var Supplier $supplier */
        $supplier = Supplier::query()->create($request->validated());

        return response()->json([
            'message' => __('Supplier created successfully.'),
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $supplier->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function update(SupplierUpdateRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());
        $supplier->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'message' => __('Supplier updated successfully.'),
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $referenced = DB::table('purchase_invoices')->where('supplier_id', $supplier->id)->exists()
            || DB::table('supplier_transactions')->where('supplier_id', $supplier->id)->exists()
            || DB::table('payments')->where('supplier_id', $supplier->id)->exists();

        if ($referenced) {
            return response()->json([
                'message' => __('This supplier has financial history and cannot be deleted.'),
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => __('Supplier deleted successfully.'),
        ]);
    }
}
