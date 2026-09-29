<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentType;
use App\Enums\RepairStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\TechnicianTransaction;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The money side of a repair.
 *
 * A repair earns money in two moments that are hours or weeks apart: the
 * deposit taken while the customer is still at the counter, and the settlement
 * when the device is handed back. Nothing links them in the ledger on its own —
 * the deposit arrives before any invoice exists — so this service owns both
 * ends and the matching between them:
 *
 *   intake      → posted customer payment, no allocation (credit on account)
 *   hand-over   → posted sales invoice, the deposit matched against it, and
 *                 whatever is still owed collected in the same breath
 *
 * Every figure the screens show comes from summary(), so the counter and the
 * ledger can never disagree about what a customer has paid.
 */
class RepairSettlementService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Everything the settlement UI needs about one repair, in one query set.
     *
     * @return array<string, mixed>
     */
    public function summary(Repair $repair): array
    {
        $repair->loadMissing([
            'customer', 'technician', 'payments',
            'salesInvoice.items.service:id,name', 'salesInvoice.items.material:id,name',
        ]);

        $posted = $repair->payments->where('status', DocumentStatus::POSTED);
        $paid = $posted->reduce(
            fn (Money $carry, Payment $payment) => $carry->add(Money::fromDecimal($payment->amount)),
            Money::zero(),
        );

        $cost = Money::fromDecimal($repair->chargeableCost());
        $due = $cost->subtract($paid);

        $invoice = $repair->salesInvoice;

        return [
            'currency' => Setting::get('currency_symbol', 'ل.س'),
            'estimated_cost' => $repair->estimated_cost,
            'final_cost' => $repair->final_cost,
            // What the customer is being charged right now: the agreed price if
            // there is one, otherwise the counter estimate.
            'chargeable_cost' => $repair->chargeableCost(),
            'paid' => $paid->toDecimal(),
            // Positive: still owed. Negative: the shop is holding the
            // customer's money — a deposit larger than the final bill.
            'due' => $due->toDecimal(),
            'credit' => $due->isNegative() ? $due->absolute()->toDecimal() : '0.00',
            'technician' => $repair->technician ? [
                'id' => $repair->technician->id,
                'name' => $repair->technician->name,
                // النسبة المقيَّدة على البلاغ بعد تسويته، وإلّا نسبة الخبير الحالية.
                'commission_rate' => $repair->commission_rate ?? $repair->technician->commission_rate,
                'commission_amount' => $repair->commission_amount,
            ] : null,
            'is_settled' => $repair->isSettled(),
            'settled_at' => $repair->settled_at?->format('Y-m-d H:i'),
            'can_settle' => $this->canSettle($repair),
            // The series the hand-over is about to draw on, so the screen can
            // say which numbers are coming before anything is posted.
            'series' => [
                'invoice_prefix' => Setting::get('repair_invoice_prefix', 'RS-'),
                'payment_prefix' => Setting::get('repair_payment_prefix', 'RCV-'),
            ],
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'status' => $invoice->status->value,
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'remaining' => $invoice->remaining,
                // What was actually billed — labour and any part fitted — so a
                // settled repair can be read back without opening the invoice.
                'items' => $invoice->items->map(fn ($item) => [
                    'line_type' => $item->line_type->value,
                    'description' => $item->description
                        ?: ($item->service?->name ?? $item->material?->name ?? '—'),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'line_total' => $item->line_total,
                ])->all(),
            ] : null,
            'payments' => $repair->payments
                ->sortByDesc('id')
                ->values()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'reference_no' => $payment->reference_no,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method->value,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'status' => $payment->status->value,
                    'notes' => $payment->notes,
                ])->all(),
        ];
    }

    /**
     * Take money against a repair.
     *
     * Before the repair is invoiced this is a deposit: it posts as a credit on
     * the customer's account and waits there. Afterwards the same call collects
     * against the settlement invoice, so "the customer came back and paid the
     * rest" needs no trip through the payments screen.
     *
     * @param  array{amount: string|float, payment_method?: string, payment_date?: string, notes?: ?string}  $data
     */
    public function deposit(Repair $repair, array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($repair, $data, $userId) {
            $repair = $this->lock($repair);

            if ($repair->status === RepairStatus::CANCELLED) {
                throw new BusinessRuleException(__('لا يمكن تحصيل مبلغ على بلاغ ملغي.'));
            }

            $amount = Money::fromDecimal($data['amount']);
            if ($amount->isZero() || $amount->isNegative()) {
                throw new BusinessRuleException(__('المبلغ يجب أن يكون أكبر من صفر.'), 'amount');
            }

            $invoice = $repair->isSettled() ? $repair->salesInvoice : null;

            // Once the repair carries a live invoice the money has somewhere to
            // land, so it must land there rather than float on the account.
            if ($invoice) {
                $remaining = Money::fromDecimal($invoice->remaining);
                if ($remaining->isZero()) {
                    throw new BusinessRuleException(__('فاتورة هذا البلاغ مسدَّدة بالكامل.'), 'amount');
                }
                if ($amount->toCents() > $remaining->toCents()) {
                    throw new BusinessRuleException(__('المبلغ يتجاوز المتبقي على الفاتورة (:remaining).', [
                        'remaining' => $remaining->toDecimal(),
                    ]), 'amount');
                }
            }

            $payment = $this->payments->saveDraft(new Payment, [
                'payment_type' => PaymentType::CUSTOMER->value,
                'customer_id' => $repair->customer_id,
                'repair_id' => $repair->id,
                'amount' => $amount->toDecimal(),
                'payment_method' => $data['payment_method'] ?? 'CASH',
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? $this->defaultNote($repair, $invoice !== null),
                'allocations' => $invoice ? [[
                    'invoice_type' => 'SALES',
                    'sales_invoice_id' => $invoice->id,
                    'amount' => $amount->toDecimal(),
                ]] : [],
            ], $userId);

            return $this->payments->post($payment, $userId);
        });
    }

    /**
     * Invoice the repair, match the deposits against it, collect the rest and
     * hand the device over — one confirmation instead of four screens.
     *
     * The bill may be a single agreed figure, or a breakdown of labour and the
     * spare parts that went into the device; see bill(). Either way the total
     * that reaches the ledger is the one this service computed, never one the
     * browser sent.
     *
     * @param  array{total?: string|float, items?: array<int, array<string, mixed>>, collect_amount?: string|float|null, payment_method?: string, notes?: ?string, delivered_at?: ?string}  $data
     * @return array<string, mixed> the fresh summary
     */
    public function settle(Repair $repair, array $data, int $userId): array
    {
        $settled = DB::transaction(function () use ($repair, $data, $userId) {
            $repair = $this->lock($repair);
            $this->assertSettleable($repair);

            $bill = $this->bill($repair, $data);

            $repair->final_cost = $bill['total']->toDecimal();

            $invoice = $bill['needs_invoice']
                ? $this->issueInvoice($repair, $bill['items'], $data['notes'] ?? null, $userId)
                : null;

            if ($invoice) {
                $this->applyDeposits($repair, $invoice);
                $invoice->refresh();
            }

            $collect = Money::fromDecimal($data['collect_amount'] ?? 0);
            if (! $collect->isZero()) {
                $this->collectOnSettlement($repair, $invoice, $collect, $data, $userId);
            }

            // A device already handed over (status moved by hand, settled
            // afterwards) keeps the moment it actually left the shop.
            $delivered = isset($data['delivered_at']) && $data['delivered_at']
                ? Carbon::parse($data['delivered_at'])
                : ($repair->delivered_at ?? now());

            $this->postCommission($repair, $bill['labour'], $data, $userId);

            $repair->sales_invoice_id = $invoice?->id;
            $repair->settled_at = now();
            $repair->status = RepairStatus::DELIVERED;
            $repair->delivered_at = $delivered;
            $repair->save();

            return $repair;
        });

        return $this->summary($settled->fresh(['customer', 'technician', 'payments']));
    }

    /**
     * Turn what the hand-over screen agreed into the lines the invoice carries.
     *
     * Two shapes reach here. A plain `total` is the quick hand-over: one lump
     * service line, the way the counter has always closed a repair. An `items`
     * breakdown is the detailed bill — labour plus the parts fitted — and it is
     * priced here rather than trusted, so a tampered browser cannot lower a
     * total or invent a line total that does not match its own quantity.
     *
     * @param  array<string, mixed>  $data
     * @return array{items: array<int, array<string, mixed>>, total: Money, labour: Money, needs_invoice: bool}
     */
    protected function bill(Repair $repair, array $data): array
    {
        $rows = array_values($data['items'] ?? []);

        if ($rows === []) {
            $total = Money::fromDecimal($data['total'] ?? 0);

            if ($total->isNegative()) {
                throw new BusinessRuleException(__('التكلفة النهائية لا يمكن أن تكون سالبة.'), 'total');
            }

            return [
                'items' => [[
                    'line_type' => 'SERVICE',
                    'service_id' => null,
                    'material_id' => null,
                    'description' => $this->lineDescription($repair),
                    'quantity' => 1,
                    'unit_price' => $total->toDecimal(),
                    'discount_amount' => 0,
                ]],
                'total' => $total,
                // Nothing was itemised, so the whole bill is the shop's work
                // and the whole bill is what the technician earns against.
                'labour' => $total,
                'needs_invoice' => ! $total->isZero(),
            ];
        }

        $items = [];
        $total = Money::zero();
        $labour = Money::zero();
        $hasMaterial = false;

        foreach ($rows as $index => $row) {
            $isMaterial = ($row['line_type'] ?? 'SERVICE') === 'MATERIAL';

            $unitPrice = Money::fromDecimal($row['unit_price'] ?? 0);
            $discount = Money::fromDecimal($row['discount_amount'] ?? 0);

            if ($unitPrice->isNegative() || $discount->isNegative()) {
                throw new BusinessRuleException(__('سطر :no يحمل مبلغاً سالباً.', ['no' => $index + 1]), 'items');
            }

            $lineTotal = $unitPrice->multiplyBy($row['quantity'])->subtract($discount);

            if ($lineTotal->isNegative()) {
                throw new BusinessRuleException(
                    __('خصم السطر :no أكبر من قيمته.', ['no' => $index + 1]),
                    'items'
                );
            }

            $items[] = [
                'line_type' => $isMaterial ? 'MATERIAL' : 'SERVICE',
                'service_id' => $isMaterial ? null : ($row['service_id'] ?? null),
                'material_id' => $isMaterial ? $row['material_id'] : null,
                'description' => $this->rowDescription($repair, $row, $isMaterial),
                'unit_id' => $row['unit_id'] ?? null,
                'quantity' => $row['quantity'],
                'unit_price' => $unitPrice->toDecimal(),
                'discount_amount' => $discount->toDecimal(),
            ];

            $total = $total->add($lineTotal);

            if ($isMaterial) {
                $hasMaterial = true;
            } else {
                $labour = $labour->add($lineTotal);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            // العمولة على أجرة الورشة وحدها: قطعة الغيار بضاعة مُعاد بيعها،
            // ومنح الخبير نسبة منها يقتطع من هامشها لا من عمله.
            'labour' => $labour,
            // A part leaves the shelf even when the work is free — a warranty
            // job still has to move the stock, so it still needs an invoice.
            'needs_invoice' => ! $total->isZero() || $hasMaterial,
        ];
    }

    /**
     * What a billed line is called on the printed invoice.
     *
     * A part keeps whatever the clerk typed, falling back to the material's own
     * name at print time. Labour says which device it was spent on, so a line
     * on the customer's statement is still readable a year later.
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowDescription(Repair $repair, array $row, bool $isMaterial): ?string
    {
        $typed = trim((string) ($row['description'] ?? ''));

        if ($typed !== '') {
            return mb_substr($typed, 0, 255);
        }

        return $isMaterial || ! empty($row['service_id'])
            ? null
            : $this->lineDescription($repair);
    }

    // ---------------------------------------------------------------- internals

    /**
     * Re-read the repair inside the transaction with a row lock: two clerks
     * settling the same device at once would otherwise raise two invoices for
     * it, and the deposit would only pay one of them.
     */
    protected function lock(Repair $repair): Repair
    {
        return Repair::query()
            ->whereKey($repair->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function canSettle(Repair $repair): bool
    {
        return $repair->status !== RepairStatus::CANCELLED && ! $repair->isSettled();
    }

    protected function assertSettleable(Repair $repair): void
    {
        if ($repair->status === RepairStatus::CANCELLED) {
            throw new BusinessRuleException(__('لا يمكن تسوية بلاغ ملغي.'));
        }

        if ($repair->isSettled()) {
            throw new BusinessRuleException(__('هذا البلاغ مسوّى مسبقاً بالفاتورة :no.', [
                'no' => $repair->salesInvoice?->invoice_no ?? '',
            ]));
        }
    }

    /**
     * One posted sales invoice carrying the agreed bill.
     *
     * Posting it is what moves any spare part off the shelf: the material lines
     * go through the same stock path as a counter sale, so a part fitted to a
     * device is never quietly missing from inventory.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function issueInvoice(Repair $repair, array $items, ?string $notes, int $userId): SalesInvoice
    {
        $draft = $this->invoices->saveSalesDraft(new SalesInvoice, [
            'customer_id' => $repair->customer_id,
            'invoice_date' => now()->toDateString(),
            'discount_type' => 'NONE',
            'discount_value' => 0,
            'notes' => $notes ?: __('تسوية بلاغ صيانة :no', ['no' => $repair->receipt_no]),
            'items' => $items,
        ], $userId);

        // The link back to the repair is what tells the ledger this invoice is
        // a settlement, and picks the repair numbering series at post time.
        $draft->repair_id = $repair->id;
        $draft->save();

        return $this->invoices->postSales($draft, $userId);
    }

    /**
     * Spend the credit already sitting on the account against the new invoice,
     * oldest receipt first, and stop once the invoice is covered. A deposit
     * larger than the final bill leaves the difference on the account, where
     * the statement can see it.
     */
    protected function applyDeposits(Repair $repair, SalesInvoice $invoice): void
    {
        $deposits = Payment::query()
            ->where('repair_id', $repair->id)
            ->where('status', DocumentStatus::POSTED)
            ->orderBy('id')
            ->get();

        foreach ($deposits as $deposit) {
            $invoice->refresh();
            $remaining = Money::fromDecimal($invoice->remaining);
            if ($remaining->isZero() || $remaining->isNegative()) {
                return;
            }

            $available = $this->payments->unallocated($deposit);
            if ($available->isZero() || $available->isNegative()) {
                continue;
            }

            $apply = $available->toCents() <= $remaining->toCents() ? $available : $remaining;

            $this->payments->applyCredit($deposit, $invoice, $apply->toDecimal());
        }
    }

    /**
     * The money handed over at the counter as the device leaves.
     */
    protected function collectOnSettlement(Repair $repair, ?SalesInvoice $invoice, Money $collect, array $data, int $userId): void
    {
        if ($collect->isNegative()) {
            throw new BusinessRuleException(__('المبلغ المحصَّل لا يمكن أن يكون سالباً.'), 'collect_amount');
        }

        if (! $invoice) {
            throw new BusinessRuleException(__('لا يمكن تحصيل مبلغ على تسوية بدون تكلفة.'), 'collect_amount');
        }

        $remaining = Money::fromDecimal($invoice->remaining);
        if ($collect->toCents() > $remaining->toCents()) {
            throw new BusinessRuleException(__('المبلغ المحصَّل (:collect) يتجاوز المتبقي على الفاتورة (:remaining).', [
                'collect' => $collect->toDecimal(),
                'remaining' => $remaining->toDecimal(),
            ]), 'collect_amount');
        }

        $payment = $this->payments->saveDraft(new Payment, [
            'payment_type' => PaymentType::CUSTOMER->value,
            'customer_id' => $repair->customer_id,
            'repair_id' => $repair->id,
            'amount' => $collect->toDecimal(),
            'payment_method' => $data['payment_method'] ?? 'CASH',
            'payment_date' => now()->toDateString(),
            'notes' => __('تسديد عند تسليم بلاغ الصيانة :no', ['no' => $repair->receipt_no]),
            'allocations' => [[
                'invoice_type' => 'SALES',
                'sales_invoice_id' => $invoice->id,
                'amount' => $collect->toDecimal(),
            ]],
        ], $userId);

        $this->payments->post($payment, $userId);
    }

    /**
     * قيد عمولة خبير الصيانة عن هذا البلاغ.
     *
     * النسبة تُلتقط على البلاغ لحظة التسوية ولا تبقى مرجعاً حيّاً لنسبة الخبير:
     * رفع نسبته غداً يجب ألّا يغيّر محاسبة بلاغ أُقفل وصُرفت عمولته.
     *
     * الوعاء هو أجرة الورشة لا إجمالي الفاتورة: قطع الغيار تمرّ على البلاغ
     * بضاعةً مُعاد بيعها، والنسبة عليها تقتطع من هامش المحل لا من عمل الخبير.
     *
     * @param  array<string, mixed>  $data
     */
    protected function postCommission(Repair $repair, Money $base, array $data, int $userId): void
    {
        // التسوية قد تتكرّر: إلغاء فاتورة البلاغ يعيد فتحه للتسوية. الدفتر
        // قيدي لا يُعدَّل، فتُعكس العمولة السابقة ثم تُقيَّد الجديدة.
        $this->reverseCommission($repair, $userId);

        $repair->loadMissing('technician');
        $technician = $repair->technician;

        if (! $technician) {
            $repair->commission_rate = null;
            $repair->commission_amount = null;

            return;
        }

        $rate = isset($data['commission_rate']) && $data['commission_rate'] !== ''
            ? (float) $data['commission_rate']
            : (float) $technician->commission_rate;

        if ($rate < 0 || $rate > 100) {
            throw new BusinessRuleException(__('نسبة الخبير يجب أن تكون بين ٠ و ١٠٠.'), 'commission_rate');
        }

        $commission = $base->percentage($rate);

        $repair->commission_rate = number_format($rate, 2, '.', '');
        $repair->commission_amount = $commission->toDecimal();

        // بلاغ بلا تكلفة (كفالة أو مجاملة) لا يترك أثراً في دفتر الخبير.
        if ($commission->isZero()) {
            return;
        }

        TechnicianTransaction::query()->create([
            'technician_id' => $technician->id,
            'transaction_date' => now()->toDateString(),
            'type' => TransactionType::REPAIR_COMMISSION,
            'reference_type' => Repair::class,
            'reference_id' => $repair->id,
            'debit' => '0.00',
            'credit' => $commission->toDecimal(),
            'description' => __('عمولة صيانة :device — إيصال :no', [
                'device' => collect([$repair->brand, $repair->model])->filter()->join(' ') ?: $repair->device_type,
                'no' => $repair->receipt_no,
            ]),
            'created_by' => $userId,
        ]);
    }

    /**
     * تصفير ما قُيِّد سابقاً لهذا البلاغ في دفاتر الخبراء.
     *
     * يُجمَع صافي كل خبير على حدة — البلاغ قد يكون أُعيد إسناده بين تسويتين —
     * ويُكتب قيد عكسي واحد لكل دفتر لم يعد صافيه صفراً.
     */
    protected function reverseCommission(Repair $repair, int $userId): void
    {
        $ledgers = TechnicianTransaction::query()
            ->where('reference_type', Repair::class)
            ->where('reference_id', $repair->id)
            ->groupBy('technician_id')
            ->selectRaw('technician_id, COALESCE(SUM(credit - debit), 0) as net')
            ->get();

        foreach ($ledgers as $ledger) {
            $net = Money::fromDecimal($ledger->net);

            if ($net->isZero()) {
                continue;
            }

            TechnicianTransaction::query()->create([
                'technician_id' => $ledger->technician_id,
                'transaction_date' => now()->toDateString(),
                'type' => TransactionType::REVERSAL,
                'reference_type' => Repair::class,
                'reference_id' => $repair->id,
                'debit' => $net->isNegative() ? '0.00' : $net->toDecimal(),
                'credit' => $net->isNegative() ? $net->absolute()->toDecimal() : '0.00',
                'description' => __('عكس عمولة إيصال :no', ['no' => $repair->receipt_no]),
                'created_by' => $userId,
            ]);
        }
    }

    protected function lineDescription(Repair $repair): string
    {
        $device = collect([$repair->brand, $repair->model])->filter()->join(' ') ?: $repair->device_type;

        return mb_substr(
            __('صيانة :device — إيصال :no', ['device' => $device, 'no' => $repair->receipt_no]),
            0,
            255,
        );
    }

    protected function defaultNote(Repair $repair, bool $againstInvoice): string
    {
        return $againstInvoice
            ? __('تسديد على بلاغ الصيانة :no', ['no' => $repair->receipt_no])
            : __('عربون بلاغ صيانة :no', ['no' => $repair->receipt_no]);
    }
}
