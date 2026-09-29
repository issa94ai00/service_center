<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerStoreRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
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

        return CustomerResource::collection($customers);
    }

    public function store(CustomerStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        /** @var Customer $customer */
        $customer = Customer::query()->create($request->validated());

        return response()->json([
            'message' => __('Customer created successfully.'),
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    public function update(CustomerUpdateRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());
        $customer->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'message' => __('Customer updated successfully.'),
            'data' => new CustomerResource($customer),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $referenced = DB::table('sales_invoices')->where('customer_id', $customer->id)->exists()
            || DB::table('customer_transactions')->where('customer_id', $customer->id)->exists()
            || DB::table('payments')->where('customer_id', $customer->id)->exists();

        if ($referenced) {
            return response()->json([
                'message' => __('This customer has financial history and cannot be deleted.'),
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => __('Customer deleted successfully.'),
        ]);
    }
}
