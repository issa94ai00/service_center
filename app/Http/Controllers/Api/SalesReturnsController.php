<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesReturnResource;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\ReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesReturnsController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesReturn::class);

        $returns = SalesReturn::query()
            ->with(['customer', 'invoice'])
            ->withCount('items')
            // One box finds a return by its own number or by the invoice it
            // came off: the customer at the counter holds the invoice, not the return.
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('return_no', 'like', "%{$request->search}%")
                ->orWhereHas('invoice', fn (Builder $i) => $i->where('invoice_no', 'like', "%{$request->search}%"))))
            ->when($request->filled('customer_id'), fn (Builder $q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('sales_invoice_id'), fn (Builder $q) => $q->where('sales_invoice_id', $request->sales_invoice_id))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('return_date', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('return_date', '<=', $request->to))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SalesReturnResource::collection($returns);
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        $this->authorize('view', $salesReturn);
        $salesReturn->load(['customer', 'invoice', 'items.material', 'items.service', 'creator']);

        return response()->json(['data' => new SalesReturnResource($salesReturn)]);
    }

    /**
     * What the return form needs about one invoice: its lines with how much of
     * each can still come back, its open balance, and the returns already on it.
     */
    public function returnable(SalesInvoice $sale): JsonResponse
    {
        $this->authorize('create', SalesReturn::class);
        $sale->load(['customer', 'returns.items']);

        $blocked = match (true) {
            $sale->status->value !== 'POSTED' => __('لا يُرتجع إلا من فاتورة مرحَّلة.'),
            $sale->repair_id !== null => __('فواتير الصيانة لا تُرتجع من هنا؛ تُعالَج من بلاغ الصيانة نفسه.'),
            default => null,
        };

        return response()->json(['data' => [
            'invoice' => [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'invoice_date' => $sale->invoice_date?->format('Y-m-d'),
                'status' => $sale->status,
                'party' => $sale->customer?->only(['id', 'name', 'phone', 'mobile']),
                'subtotal' => $sale->subtotal,
                'discount_amount' => $sale->discount_amount,
                'total' => $sale->total,
                'paid_amount' => $sale->paid_amount,
                'returned_amount' => $sale->returned_amount,
                'remaining' => $sale->remaining,
                'prior_discount' => (string) $sale->returns->where('status.value', 'POSTED')->sum('discount_amount'),
            ],
            'lines' => $this->returns->returnableSalesLines($sale)->values(),
            'returns' => SalesReturnResource::collection($sale->returns->sortByDesc('id')->values()),
            'blocked_reason' => $blocked,
        ]]);
    }

    public function store(Request $request, SalesInvoice $sale): JsonResponse
    {
        $this->authorize('create', SalesReturn::class);
        $this->authorize('view', $sale);

        $data = $request->validate([
            // Goods cannot come back before they went out.
            'return_date' => ['nullable', 'date', 'after_or_equal:'.$sale->invoice_date?->toDateString(), 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'settlement' => ['nullable', 'in:'.ReturnService::SETTLE_CREDIT.','.ReturnService::SETTLE_REFUND],
            'refund_method' => ['nullable', 'in:CASH,BANK,OTHER'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sales_invoice_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gte:0'],
        ]);

        // ردّ النقد يُخرج مالاً من الصندوق، فمن لا يملك صرف السندات لا يملكه
        // من باب المرتجع؛ يبقى له أن يترك المبلغ رصيداً على الحساب.
        if (($data['settlement'] ?? null) === ReturnService::SETTLE_REFUND) {
            abort_unless($request->user()?->hasPermission('payments.create'), 403, __('لا تملك صلاحية ردّ مبلغ نقدي.'));
        }

        $return = $this->returns->createSalesReturn($sale, $data, auth()->id());
        $return->load(['customer', 'invoice', 'items.material', 'items.service']);

        return response()->json([
            'message' => __('تم تسجيل المرتجع :no.', ['no' => $return->return_no]),
            'data' => new SalesReturnResource($return),
        ], 201);
    }

    public function cancel(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->authorize('cancel', $salesReturn);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $return = $this->returns->cancelSalesReturn($salesReturn, auth()->id(), $request->reason);
        $return->load(['customer', 'invoice', 'items.material', 'items.service']);

        return response()->json([
            'message' => __('تم إلغاء المرتجع.'),
            'data' => new SalesReturnResource($return),
        ]);
    }
}
