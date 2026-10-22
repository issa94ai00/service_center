<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SearchesParties;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesInvoiceStoreRequest;
use App\Http\Requests\SalesInvoiceUpdateRequest;
use App\Http\Resources\CustomerOptionResource;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesController extends Controller
{
    use SearchesParties;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesInvoice::class);

        $invoices = SalesInvoice::query()
            // The device comes along so a settlement row can name it; on a page
            // of plain sales this costs one extra query returning nothing.
            ->with(['customer', 'repair'])
            ->withCount(['items', 'returns as active_returns_count' => fn (Builder $q) => $q->where('status', 'POSTED')])
            // 'REPAIR' narrows to maintenance settlements, 'SALES' to counter
            // sales; anything else leaves both in the listing as before.
            ->source($request->query('source'))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('invoice_no', 'like', "%{$request->search}%"))
            ->when($request->filled('customer_id'), fn (Builder $q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('invoice_date', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('invoice_date', '<=', $request->to))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SalesInvoiceResource::collection($invoices);
    }

    public function store(SalesInvoiceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', SalesInvoice::class);

        $invoice = $this->invoices->saveSalesDraft(new SalesInvoice, $request->validated(), auth()->id());
        $invoice->load(['customer', 'items']);

        return response()->json([
            'message' => __('Sales invoice draft created.'),
            'data' => new SalesInvoiceResource($invoice),
        ], 201);
    }

    public function show(SalesInvoice $sale): JsonResponse
    {
        $this->authorize('view', $sale);
        $sale->load(['customer', 'repair', 'items.service.unit', 'items.material', 'items.unit', 'returns']);

        return response()->json(['data' => new SalesInvoiceResource($sale)]);
    }

    public function update(SalesInvoiceUpdateRequest $request, SalesInvoice $sale): JsonResponse
    {
        $this->authorize('update', $sale);
        $this->invoices->deleteDraft($sale);
        $sale = $this->invoices->saveSalesDraft(
            new SalesInvoice, $request->validated() + ['customer_id' => $request->customer_id ?? $sale->customer_id], auth()->id()
        );
        $sale->load(['customer', 'items']);

        return response()->json([
            'message' => __('Sales invoice updated.'),
            'data' => new SalesInvoiceResource($sale),
        ]);
    }

    public function destroy(SalesInvoice $sale): JsonResponse
    {
        $this->authorize('delete', $sale);
        $this->invoices->deleteDraft($sale);

        return response()->json(['message' => __('Draft deleted.')]);
    }

    /**
     * ترحيل الفاتورة، ومعها ما دفعه العميل نقداً إن دفع.
     *
     * `collect_amount` اختياري: صفرُه (أو غيابه) يترك الفاتورة ديناً على العميل
     * كما كانت دائماً، وما فوقه يُنشئ سند قبض مخصَّصاً عليها ويعتمده معها.
     */
    public function post(Request $request, SalesInvoice $sale): JsonResponse
    {
        $this->authorize('post', $sale);

        $data = $request->validate([
            'collect_amount' => ['nullable', 'numeric', 'gte:0'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
        ]);

        $collect = (string) ($data['collect_amount'] ?? '0');

        // القبض هنا يُنشئ سنداً ويعتمده، فمن لا يملك ذلك في شاشة السندات لا
        // يملكه من هذا الباب: صلاحية ترحيل فاتورة ليست صلاحية قبض مال.
        if (Money::fromDecimal($collect)->toCents() > 0) {
            $user = $request->user();
            abort_unless(
                $user?->hasPermission('payments.create') && $user?->hasPermission('payments.confirm'),
                403,
                __('لا تملك صلاحية تسجيل سند قبض.')
            );
        }

        $invoice = $this->invoices->postSalesAndCollect(
            $sale,
            auth()->id(),
            $collect,
            ['payment_method' => $data['payment_method'] ?? 'CASH'],
        );
        $invoice->load(['customer', 'repair', 'items.service.unit', 'items.material', 'items.unit']);

        return response()->json([
            'message' => __('Sales invoice posted.'),
            'data' => new SalesInvoiceResource($invoice),
        ]);
    }

    public function cancel(Request $request, SalesInvoice $sale): JsonResponse
    {
        $this->authorize('cancel', $sale);
        $request->validate(['reason' => ['required', 'string', 'min:3']]);

        $invoice = $this->invoices->cancelSales($sale, auth()->id(), $request->reason);
        $invoice->load(['customer', 'repair', 'items.service.unit', 'items.material', 'items.unit']);

        return response()->json([
            'message' => __('Sales invoice cancelled.'),
            'data' => new SalesInvoiceResource($invoice),
        ]);
    }

    /**
     * The customer picker behind the sales form.
     *
     * With nothing typed yet the clerk is shown whoever was invoiced most
     * recently: at the counter that is far likelier to be the person standing
     * there than whoever happens to sort first alphabetically.
     */
    public function customerOptions(Request $request): JsonResponse
    {
        // Without this, any authenticated user — including one holding no
        // permissions at all — could read the entire customer book, balances
        // and contact details included.
        $this->authorize('create', SalesInvoice::class);

        return $this->partyOptionsResponse(
            $request,
            Customer::class,
            CustomerOptionResource::class,
            fn (Builder $q) => $q->withMax('salesInvoices as last_invoice_at', 'invoice_date')
                ->orderByDesc('last_invoice_at')
                ->orderBy('name'),
        );
    }
}
