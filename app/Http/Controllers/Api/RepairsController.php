<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Enums\RepairStatus;
use App\Http\Controllers\Concerns\SearchesParties;
use App\Http\Controllers\Controller;
use App\Http\Requests\RepairDepositRequest;
use App\Http\Requests\RepairSettleRequest;
use App\Http\Requests\RepairStoreRequest;
use App\Http\Requests\RepairUpdateRequest;
use App\Http\Resources\CustomerOptionResource;
use App\Http\Resources\RepairResource;
use App\Models\Customer;
use App\Models\Repair;
use App\Models\Setting;
use App\Services\RepairSettlementService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairsController extends Controller
{
    use SearchesParties;

    public function __construct(private readonly RepairSettlementService $settlements) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Repair::class);

        // Built without the status filter so the status chips can show how many
        // records each status holds *under the current search and date range*.
        $filtered = $this->filteredQuery($request);

        $counts = (clone $filtered)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $repairs = $filtered
            ->with(['customer', 'technician', 'salesInvoice'])
            // What each device has been paid, in one query rather than one per
            // row: the list shows the outstanding balance on every card.
            ->withSum(
                ['payments as paid_total' => fn (Builder $q) => $q->where('status', DocumentStatus::POSTED)],
                'amount'
            )
            // Accepts one status or a comma-separated set, so the list's
            // "still on the bench" chip is one server-side filter rather than a
            // client-side sieve that would break the page counts.
            ->when($request->filled('status'), function (Builder $q) use ($request) {
                $statuses = array_values(array_intersect(
                    array_map('trim', explode(',', (string) $request->status)),
                    array_map(fn ($case) => $case->value, RepairStatus::cases()),
                ));

                $q->whereIn('status', $statuses ?: ['__none__']);
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $statusCounts = [];
        foreach (RepairStatus::cases() as $case) {
            $statusCounts[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return RepairResource::collection($repairs)->additional([
            'meta' => [
                'status_counts' => $statusCounts,
                'total_unfiltered' => array_sum($statusCounts),
            ],
        ]);
    }

    public function store(RepairStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Repair::class);

        $data = $this->normalise($request->validated());
        $deposit = $data['deposit_amount'] ?? null;
        $method = $data['deposit_method'] ?? 'CASH';
        unset($data['deposit_amount'], $data['deposit_method']);

        // Handing over a deposit is part of the same conversation at the
        // counter, so it is part of the same request — and the same
        // transaction: a device must never be registered with money that was
        // not booked, nor money booked against a device that was not saved.
        if ($deposit !== null) {
            $this->authorize('settle', new Repair);
        }

        [$repair, $payment] = DB::transaction(function () use ($data, $deposit, $method) {
            $repair = Repair::query()->create($data + ['received_at' => now()]);

            $payment = $deposit === null ? null : $this->settlements->deposit($repair, [
                'amount' => $deposit,
                'payment_method' => $method,
            ], auth()->id());

            return [$repair, $payment];
        });

        $fresh = $this->hydrate($repair);

        return response()->json([
            'message' => $deposit === null
                ? __('Maintenance device received.')
                : __('تم استلام الجهاز وتسجيل العربون.'),
            'data' => new RepairResource($fresh),
            'settlement' => $this->settlements->summary($fresh),
            // The customer is still at the counter: the receipt for the money
            // they just handed over has to be one click away, not a hunt
            // through the payments screen.
            'payment_id' => $payment?->id,
        ], 201);
    }

    public function show(Repair $repair): JsonResponse
    {
        $this->authorize('view', $repair);
        $repair->load(['customer', 'technician', 'salesInvoice']);

        return response()->json([
            'data' => new RepairResource($repair),
            'settlement' => $this->settlements->summary($repair),
        ]);
    }

    /**
     * The financial snapshot behind the settlement dialog: what was quoted,
     * what has been paid, what is still owed and which documents say so.
     */
    public function settlement(Repair $repair): JsonResponse
    {
        $this->authorize('view', $repair);

        return response()->json(['data' => $this->settlements->summary($repair)]);
    }

    /**
     * Take money against a repair — the deposit at the counter, or the balance
     * when the customer comes back.
     */
    public function deposit(RepairDepositRequest $request, Repair $repair): JsonResponse
    {
        $this->authorize('settle', $repair);

        $payment = $this->settlements->deposit($repair, $request->validated(), auth()->id());
        $fresh = $this->hydrate($repair);

        return response()->json([
            'message' => __('تم تسجيل المبلغ برقم :no.', ['no' => $payment->reference_no]),
            'data' => new RepairResource($fresh),
            'settlement' => $this->settlements->summary($fresh),
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Invoice the repair, spend the deposit on it, collect the rest and mark
     * the device delivered.
     */
    public function settle(RepairSettleRequest $request, Repair $repair): JsonResponse
    {
        $this->authorize('settle', $repair);

        $summary = $this->settlements->settle($repair, $request->validated(), auth()->id());

        return response()->json([
            'message' => __('تمت التسوية وتسليم الجهاز.'),
            'data' => new RepairResource($this->hydrate($repair)),
            'settlement' => $summary,
        ]);
    }

    public function update(RepairUpdateRequest $request, Repair $repair): JsonResponse
    {
        $this->authorize('update', $repair);

        $repair->update($this->normalise($request->validated()));

        return response()->json([
            'message' => __('Repair updated successfully.'),
            'data' => new RepairResource($this->hydrate($repair)),
        ]);
    }

    public function destroy(Repair $repair): JsonResponse
    {
        $this->authorize('delete', $repair);

        $repair->delete();

        return response()->json(['message' => __('Repair deleted successfully.')]);
    }

    /**
     * The customer picker.
     *
     * With nothing typed yet the clerk is shown whoever most recently brought a
     * device in: at the counter that is far likelier to be the person standing
     * there than whoever happens to sort first alphabetically.
     */
    public function customerOptions(Request $request): JsonResponse
    {
        // Same guard as the invoice pickers: reading the customer list is a
        // create-level action, not a browse.
        $this->authorize('create', Repair::class);

        return $this->partyOptionsResponse(
            $request,
            Customer::class,
            CustomerOptionResource::class,
            fn (Builder $q) => $q->withMax('repairs as last_repair_at', 'received_at')
                ->orderByDesc('last_repair_at')
                ->orderBy('name'),
        );
    }

    /**
     * The customer's standing account, as the deposit is being agreed.
     *
     * A clerk quoting a deposit needs to know whether this customer already
     * owes the shop money or is owed some, and how many of their devices are
     * still unpaid on the bench — it is the difference between "half up front"
     * and "let us clear the old bill first". Kept out of the customer picker on
     * purpose: that payload is read by anyone who may register a device, while
     * a balance belongs to whoever may take money.
     */
    public function customerAccount(Request $request): JsonResponse
    {
        $this->authorize('settle', new Repair);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()
            ->withAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'))
            ->findOrFail($validated['customer_id']);

        $balance = Money::fromDecimal($customer->opening_balance)
            ->add(Money::fromDecimal($customer->tx_net));

        $open = Repair::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [RepairStatus::DELIVERED, RepairStatus::CANCELLED])
            ->withSum(
                ['payments as paid_total' => fn (Builder $q) => $q->where('status', DocumentStatus::POSTED)],
                'amount'
            )
            ->get();

        // Only what is still owed counts: a device over-deposited does not
        // cancel out one that is unpaid, it is already visible in the balance.
        $openDue = $open->reduce(function (Money $carry, Repair $repair) {
            $due = Money::fromDecimal($repair->chargeableCost())
                ->subtract(Money::fromDecimal($repair->paid_total));

            return $due->isNegative() ? $carry : $carry->add($due);
        }, Money::zero());

        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'currency' => Setting::get('currency_symbol', 'ل.س'),
                // Positive: the customer owes the shop. Negative: the shop is
                // holding the customer's money.
                'balance' => $balance->toDecimal(),
                'open_repairs' => $open->count(),
                'open_due' => $openDue->toDecimal(),
            ],
        ]);
    }

    public function brandOptions(Request $request): JsonResponse
    {
        $this->authorize('create', Repair::class);

        $brands = Repair::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values();

        return response()->json(['data' => $brands]);
    }

    /**
     * Re-read a repair the way the list reads it, so a row patched into the
     * table after a settlement carries the same money figures as its
     * neighbours instead of blanking them out.
     */
    protected function hydrate(Repair $repair): Repair
    {
        return Repair::query()
            ->with(['customer', 'technician', 'salesInvoice'])
            ->withSum(
                ['payments as paid_total' => fn (Builder $q) => $q->where('status', DocumentStatus::POSTED)],
                'amount'
            )
            ->findOrFail($repair->getKey());
    }

    /**
     * Everything the list filters on except the status, which is applied later
     * so the same query can also count the records behind each status chip.
     */
    protected function filteredQuery(Request $request): Builder
    {
        return Repair::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = trim((string) $request->string('search'));
                $like = '%'.$term.'%';
                // "RPR-42", "rpr 42" and "42" should all find receipt 42.
                $id = (int) preg_replace('/\D+/', '', $term);

                $query->where(function (Builder $q) use ($like, $id, $term) {
                    $q->where('serial_number', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('device_type', 'like', $like)
                        ->orWhere('reported_issue', 'like', $like)
                        ->orWhereHas('customer', function (Builder $c) use ($like) {
                            $c->where('name', 'like', $like)
                                ->orWhere('company', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('mobile', 'like', $like);
                        });

                    if ($id > 0 && $this->looksLikeReceiptNumber($term)) {
                        $q->orWhere('id', $id);
                    }
                });
            })
            ->when($request->filled('customer_id'), fn (Builder $q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('received_at', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('received_at', '<=', $request->to));
    }

    /**
     * A search term counts as a receipt number when it is only digits, or when
     * it carries the configured receipt prefix. This keeps a serial number that
     * merely contains digits from dragging in an unrelated receipt id.
     */
    protected function looksLikeReceiptNumber(string $term): bool
    {
        if (preg_match('/^\d+$/', $term) === 1) {
            return true;
        }

        $prefix = trim((string) Setting::get('repair_receipt_prefix', 'RPR-'));
        $prefix = rtrim($prefix, '-');

        return $prefix !== '' && stripos($term, $prefix) === 0;
    }

    /**
     * Normalise the validated payload: dates come from the datetime-local input
     * as "Y-m-d\TH:i" strings and the status must set/clear delivered_at so the
     * record can never claim a delivery it did not perform.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalise(array $data): array
    {
        if (isset($data['received_at'])) {
            $data['received_at'] = Carbon::parse($data['received_at']);
        }

        $status = isset($data['status']) ? RepairStatus::from((string) $data['status']) : null;

        if ($status === RepairStatus::DELIVERED) {
            $data['delivered_at'] = isset($data['delivered_at'])
                ? Carbon::parse($data['delivered_at'])
                : now();
        } elseif ($status !== null) {
            // Moved back out of "delivered": the timestamp no longer makes sense.
            $data['delivered_at'] = null;
        }

        return $data;
    }
}
