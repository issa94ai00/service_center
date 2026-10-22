<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SearchesParties;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseInvoiceStoreRequest;
use App\Http\Requests\PurchaseInvoiceUpdateRequest;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Http\Resources\SupplierOptionResource;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\InvoiceService;
use App\Services\SupplierPriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchasesController extends Controller
{
    use SearchesParties;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseInvoice::class);

        $invoices = PurchaseInvoice::query()
            ->with(['supplier'])
            ->withCount(['items', 'returns as active_returns_count' => fn (Builder $q) => $q->where('status', 'POSTED')])
            ->when($request->filled('search'), fn (Builder $q) => $q->where('invoice_no', 'like', "%{$request->search}%"))
            ->when($request->filled('supplier_id'), fn (Builder $q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('purchase_date', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('purchase_date', '<=', $request->to))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return PurchaseInvoiceResource::collection($invoices);
    }

    public function store(PurchaseInvoiceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseInvoice::class);

        $invoice = $this->invoices->savePurchaseDraft(new PurchaseInvoice, $request->validated(), auth()->id());
        $invoice->load(['supplier', 'items']);

        return response()->json([
            'message' => __('Purchase invoice draft created.'),
            'data' => new PurchaseInvoiceResource($invoice),
        ], 201);
    }

    public function show(PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);
        $purchase->load(['supplier', 'items.material', 'items.unit', 'returns']);

        return response()->json(['data' => new PurchaseInvoiceResource($purchase)]);
    }

    public function update(PurchaseInvoiceUpdateRequest $request, PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);
        $this->invoices->deleteDraft($purchase);
        $purchase = $this->invoices->savePurchaseDraft(
            new PurchaseInvoice, $request->validated() + ['supplier_id' => $request->supplier_id ?? $purchase->supplier_id], auth()->id()
        );
        $purchase->load(['supplier', 'items']);

        return response()->json([
            'message' => __('Purchase invoice updated.'),
            'data' => new PurchaseInvoiceResource($purchase),
        ]);
    }

    public function destroy(PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);
        $this->invoices->deleteDraft($purchase);

        return response()->json(['message' => __('Draft deleted.')]);
    }

    public function post(Request $request, PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('post', $purchase);

        $invoice = $this->invoices->postPurchase($purchase, auth()->id());
        $invoice->load(['supplier', 'items.material', 'items.unit']);

        return response()->json([
            'message' => __('Purchase invoice posted.'),
            'data' => new PurchaseInvoiceResource($invoice),
        ]);
    }

    public function cancel(Request $request, PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('cancel', $purchase);
        $request->validate(['reason' => ['required', 'string', 'min:3']]);

        $invoice = $this->invoices->cancelPurchase($purchase, auth()->id(), $request->reason);
        $invoice->load(['supplier', 'items.material', 'items.unit']);

        return response()->json([
            'message' => __('Purchase invoice cancelled.'),
            'data' => new PurchaseInvoiceResource($invoice),
        ]);
    }

    /**
     * The supplier picker behind the purchase form.
     *
     * With nothing typed yet the storekeeper is shown whoever was last bought
     * from: a shop reorders from the same handful of wholesalers week after
     * week, so recency beats the alphabet by a wide margin here.
     */
    public function supplierOptions(Request $request): JsonResponse
    {
        // Without this, any authenticated user could read the entire supplier
        // book — balances, contact details and buying relationships included.
        $this->authorize('create', PurchaseInvoice::class);

        return $this->partyOptionsResponse(
            $request,
            Supplier::class,
            SupplierOptionResource::class,
            fn (Builder $q) => $q->withMax('purchaseInvoices as last_invoice_at', 'purchase_date')
                ->orderByDesc('last_invoice_at')
                ->orderBy('name'),
        );
    }

    /**
     * أفضل سعر مورد لكل مادة على فاتورة قيد الكتابة، من فواتير الشراء
     * المُرحَّلة. تُرسل المواد دفعة واحدة لأن النموذج يسأل عن كل أسطره معاً.
     */
    public function priceSuggestions(Request $request, SupplierPriceService $prices): JsonResponse
    {
        $this->authorize('create', PurchaseInvoice::class);

        $data = $request->validate([
            'material_ids' => ['required', 'array', 'max:200'],
            'material_ids.*' => ['integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'data' => array_values($prices->suggestions($data['material_ids'], $data['supplier_id'] ?? null)),
        ]);
    }
}
