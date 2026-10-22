<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseReturnResource;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Services\ReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseReturnsController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $returns = PurchaseReturn::query()
            ->with(['supplier', 'invoice'])
            ->withCount('items')
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('return_no', 'like', "%{$request->search}%")
                ->orWhereHas('invoice', fn (Builder $i) => $i->where('invoice_no', 'like', "%{$request->search}%"))))
            ->when($request->filled('supplier_id'), fn (Builder $q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->filled('purchase_invoice_id'), fn (Builder $q) => $q->where('purchase_invoice_id', $request->purchase_invoice_id))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('return_date', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('return_date', '<=', $request->to))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return PurchaseReturnResource::collection($returns);
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('view', $purchaseReturn);
        $purchaseReturn->load(['supplier', 'invoice', 'items.material', 'creator']);

        return response()->json(['data' => new PurchaseReturnResource($purchaseReturn)]);
    }

    public function returnable(PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('create', PurchaseReturn::class);
        $purchase->load(['supplier', 'returns.items']);

        return response()->json(['data' => [
            'invoice' => [
                'id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no,
                'invoice_date' => $purchase->purchase_date?->format('Y-m-d'),
                'status' => $purchase->status,
                'party' => $purchase->supplier?->only(['id', 'name', 'phone', 'mobile']),
                'subtotal' => $purchase->subtotal,
                'discount_amount' => $purchase->discount_amount,
                'total' => $purchase->total,
                'paid_amount' => $purchase->paid_amount,
                'returned_amount' => $purchase->returned_amount,
                'remaining' => $purchase->remaining,
                'prior_discount' => (string) $purchase->returns->where('status.value', 'POSTED')->sum('discount_amount'),
            ],
            'lines' => $this->returns->returnablePurchaseLines($purchase)->values(),
            'returns' => PurchaseReturnResource::collection($purchase->returns->sortByDesc('id')->values()),
            'blocked_reason' => $purchase->status->value !== 'POSTED' ? __('لا يُرتجع إلا من فاتورة مرحَّلة.') : null,
        ]]);
    }

    public function store(Request $request, PurchaseInvoice $purchase): JsonResponse
    {
        $this->authorize('create', PurchaseReturn::class);
        $this->authorize('view', $purchase);

        $data = $request->validate([
            // Goods cannot come back before they went out.
            'return_date' => ['nullable', 'date', 'after_or_equal:'.$purchase->purchase_date?->toDateString(), 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'settlement' => ['nullable', 'in:'.ReturnService::SETTLE_CREDIT.','.ReturnService::SETTLE_REFUND],
            'refund_method' => ['nullable', 'in:CASH,BANK,OTHER'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.purchase_invoice_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gte:0'],
        ]);

        // استلام نقد من المورد يُدخل مالاً إلى الصندوق: عملية سند لا عملية مستودع.
        if (($data['settlement'] ?? null) === ReturnService::SETTLE_REFUND) {
            abort_unless($request->user()?->hasPermission('payments.create'), 403, __('لا تملك صلاحية تسجيل مبلغ مستردّ.'));
        }

        $return = $this->returns->createPurchaseReturn($purchase, $data, auth()->id());
        $return->load(['supplier', 'invoice', 'items.material']);

        return response()->json([
            'message' => __('تم تسجيل المرتجع :no.', ['no' => $return->return_no]),
            'data' => new PurchaseReturnResource($return),
        ], 201);
    }

    public function cancel(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('cancel', $purchaseReturn);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $return = $this->returns->cancelPurchaseReturn($purchaseReturn, auth()->id(), $request->reason);
        $return->load(['supplier', 'invoice', 'items.material']);

        return response()->json([
            'message' => __('تم إلغاء المرتجع.'),
            'data' => new PurchaseReturnResource($return),
        ]);
    }
}
