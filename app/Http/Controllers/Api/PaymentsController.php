<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentUpdateRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentsController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->with(['customer', 'supplier', 'technician', 'repair'])
            // 'REPAIR' narrows to money taken against a device, 'DIRECT' to
            // what was booked through this screen; anything else shows both.
            ->source($request->query('source'))
            ->when($request->filled('payment_type'), fn (Builder $q) => $q->where('payment_type', $request->payment_type))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('reference_no', 'like', "%{$request->search}%"))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('payment_date', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('payment_date', '<=', $request->to))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function store(PaymentStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->payments->saveDraft(new Payment(), $request->validated(), auth()->id());
        $payment->load(['customer', 'supplier', 'technician', 'repair', 'allocations']);

        return response()->json([
            'message' => __('Payment draft created.'),
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);
        $payment->load(['customer', 'supplier', 'technician', 'repair', 'allocations.salesInvoice', 'allocations.purchaseInvoice']);

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    public function update(PaymentUpdateRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('update', $payment);

        $payment = $this->payments->saveDraft($payment, $request->validated(), auth()->id());
        $payment->load(['customer', 'supplier', 'technician', 'repair', 'allocations']);

        return response()->json([
            'message' => __('Payment updated.'),
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);
        $this->payments->deleteDraft($payment);

        return response()->json(['message' => __('Draft deleted.')]);
    }

    public function post(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('post', $payment);

        $payment = $this->payments->post($payment, auth()->id());
        $payment->load(['customer', 'supplier', 'technician', 'repair', 'allocations.salesInvoice', 'allocations.purchaseInvoice']);

        return response()->json([
            'message' => __('Payment posted.'),
            'data' => new PaymentResource($payment),
        ]);
    }

    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('cancel', $payment);
        $request->validate(['reason' => ['required', 'string', 'min:3']]);

        $payment = $this->payments->cancel($payment, auth()->id(), $request->reason);
        $payment->load(['customer', 'supplier', 'technician', 'repair', 'allocations.salesInvoice', 'allocations.purchaseInvoice']);

        return response()->json([
            'message' => __('Payment cancelled.'),
            'data' => new PaymentResource($payment),
        ]);
    }

    public function openInvoices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $request->validate([
            'party_type' => ['required', 'in:CUSTOMER,SUPPLIER'],
            'party_id' => ['required', 'integer'],
        ]);

        $invoices = $request->party_type === 'CUSTOMER'
            ? SalesInvoice::query()
                ->where('customer_id', $request->party_id)
                ->where('status', DocumentStatus::POSTED)
                ->whereColumn('paid_amount', '<', 'total')
                ->orderBy('invoice_date')
                ->get(['id', 'invoice_no', 'invoice_date', 'total', 'paid_amount', 'remaining'])
            : PurchaseInvoice::query()
                ->where('supplier_id', $request->party_id)
                ->where('status', DocumentStatus::POSTED)
                ->whereColumn('paid_amount', '<', 'total')
                ->orderBy('purchase_date')
                ->get(['id', 'invoice_no', 'purchase_date', 'total', 'paid_amount', 'remaining']);

        return response()->json([
            'data' => $invoices->map(fn ($i) => [
                'invoice_type' => $request->party_type === 'CUSTOMER' ? 'SALES' : 'PURCHASE',
                'invoice_id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'invoice_date' => ($i->invoice_date ?? $i->purchase_date)?->format('Y-m-d'),
                'total' => $i->total,
                'paid_amount' => $i->paid_amount,
                'remaining' => $i->remaining,
            ]),
        ]);
    }
}