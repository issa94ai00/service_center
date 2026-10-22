<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\LineType;
use App\Enums\MovementDirection;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DocumentLockedException;
use App\Exceptions\InsufficientStockException;
use App\Models\CustomerTransaction;
use App\Models\Material;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\SupplierTransaction;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales and purchase returns.
 *
 * A return is posted the moment it is recorded — the goods are already back on
 * the counter (or already on the supplier's van) — and, like every other
 * document here, it never edits history: the invoice keeps what was sold and
 * cancelling a return appends compensating rows instead of deleting any.
 *
 * Money follows one rule on both sides. The return's value first comes off
 * what is still open on the invoice; only what exceeds that was actually paid
 * for, and that excess is either handed back (a refund) or left on the
 * account as credit for the next invoice.
 */
class ReturnService
{
    public const SETTLE_CREDIT = 'CREDIT';

    public const SETTLE_REFUND = 'REFUND';

    public function __construct(
        private readonly NumberingService $numbers,
        private readonly MaterialCostService $costs,
    ) {}

    // ------------------------------------------------------------------ sales

    /**
     * Each line of a posted sales invoice with how much of it can still come back.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function returnableSalesLines(SalesInvoice $invoice): Collection
    {
        $invoice->loadMissing(['items.activeReturnItems', 'items.material', 'items.service', 'items.unit']);

        return $invoice->items->map(fn (SalesInvoiceItem $item) => $this->lineSummary(
            $item,
            $item->line_type->value,
            $item->service?->name ?? $item->material?->name ?? $item->description,
        ));
    }

    /**
     * @param  array{return_date?: ?string, reason: string, notes?: ?string, settlement?: ?string, refund_method?: ?string, items: array<int, array{sales_invoice_item_id: int, quantity: string|int|float}>}  $data
     */
    public function createSalesReturn(SalesInvoice $invoice, array $data, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertReturnable($invoice);

            // فاتورة الصيانة تحمل عمولة خبير وتسوية بلاغ؛ مرتجعها يعني إعادة فتح
            // البلاغ، وذلك يُدار من شاشة الصيانة لا من هنا.
            if ($invoice->repair_id !== null) {
                throw new BusinessRuleException(__('فواتير الصيانة لا تُرتجع من هنا؛ تُعالَج من بلاغ الصيانة نفسه.'));
            }

            $invoice->load('items.activeReturnItems');
            $quote = $this->quote($invoice, $data['items'] ?? [], 'sales_invoice_item_id', SalesReturn::query()
                ->where('sales_invoice_id', $invoice->id)
                ->where('status', DocumentStatus::POSTED));

            $settlement = $this->settle($invoice, $quote['total'], $data);
            $date = $data['return_date'] ?? now()->toDateString();
            $returnNo = $this->numbers->next('SALES_RETURN');
            $label = __('مرتجع بيع :no عن الفاتورة :invoice', ['no' => $returnNo, 'invoice' => $invoice->invoice_no]);

            $return = SalesReturn::query()->create([
                'return_no' => $returnNo,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'return_date' => $date,
                'status' => DocumentStatus::POSTED,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $quote['subtotal'],
                'discount_amount' => $quote['discount_amount'],
                'total' => $quote['total'],
                'applied_amount' => $settlement['applied'],
                'refunded_amount' => $settlement['refunded'],
                'refund_method' => $settlement['method'],
                'created_by' => $userId,
            ]);

            $cost = Money::zero();

            foreach ($quote['lines'] as $line) {
                /** @var SalesInvoiceItem $item */
                $item = $line['item'];
                $lineCost = null;

                if ($item->line_type === LineType::MATERIAL && $item->material_id) {
                    $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                    // The goods come back at what they cost when they left, not
                    // at today's price: otherwise a return books a profit or a
                    // loss the sale never made.
                    $unitCost = $this->soldUnitCost($item, $material);
                    $lineCost = Money::fromDecimal($unitCost)->multiplyBy($line['quantity']);
                    $cost = $cost->add($lineCost);

                    $this->movement($item->material_id, MovementType::SALE_RETURN, MovementDirection::IN, $line['quantity'],
                        (string) $item->unit_price, MovementSourceType::SALES_RETURN, $return->id, $returnNo, $date, $label, $userId);

                    if ($material) {
                        $this->costs->reverseOutbound($material, $line['quantity'], $unitCost);
                        $material->increment('stock_quantity', $line['quantity']);
                    }
                }

                SalesReturnItem::query()->create([
                    'sales_return_id' => $return->id,
                    'sales_invoice_item_id' => $item->id,
                    'line_type' => $item->line_type,
                    'service_id' => $item->service_id,
                    'material_id' => $item->material_id,
                    'description' => $item->description,
                    'quantity' => $line['quantity'],
                    'unit_price' => $item->unit_price,
                    'line_total' => $line['line_total'],
                    'cost_amount' => $lineCost?->toDecimal(),
                ]);
            }

            $return->update(['cost_amount' => $cost->toDecimal()]);
            $this->applyToInvoice($invoice, $settlement['applied']);

            CustomerTransaction::query()->create([
                'customer_id' => $invoice->customer_id,
                'transaction_date' => $date,
                'type' => TransactionType::SALES_RETURN,
                'reference_type' => SalesReturn::class,
                'reference_id' => $return->id,
                'debit' => '0.00',
                'credit' => $quote['total'],
                'description' => $label,
                'created_by' => $userId,
            ]);

            if (Money::fromDecimal($settlement['refunded'])->toCents() > 0) {
                CustomerTransaction::query()->create([
                    'customer_id' => $invoice->customer_id,
                    'transaction_date' => $date,
                    'type' => TransactionType::CUSTOMER_REFUND,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $return->id,
                    'debit' => $settlement['refunded'],
                    'credit' => '0.00',
                    'description' => __('ردّ مبلغ للعميل عن المرتجع :no', ['no' => $returnNo]),
                    'created_by' => $userId,
                ]);
            }

            return $return->fresh(['items', 'invoice', 'customer']);
        });
    }

    public function cancelSalesReturn(SalesReturn $return, int $userId, string $reason): SalesReturn
    {
        return DB::transaction(function () use ($return, $userId, $reason) {
            /** @var SalesReturn $return */
            $return = SalesReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== DocumentStatus::POSTED) {
                throw new DocumentLockedException;
            }

            $invoice = SalesInvoice::query()->whereKey($return->sales_invoice_id)->lockForUpdate()->firstOrFail();
            $return->load('items');
            $date = now()->toDateString();
            $strict = $this->strictStock();

            foreach ($return->items->where('line_type', LineType::MATERIAL) as $item) {
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                // Undoing a return sends the goods out again; they may already
                // have been sold to someone else.
                if ($strict && $material && bccomp((string) $material->stock_quantity, (string) $item->quantity, 3) === -1) {
                    throw new InsufficientStockException($material, (string) $item->quantity);
                }

                $this->reverseMovement(MovementSourceType::SALES_RETURN, $return->id, $item->material_id,
                    MovementType::SALE_RETURN, $return->return_no, $date, $reason, $userId);

                if ($material) {
                    $this->costs->reverseInbound($material, (string) $item->quantity);
                    $material->decrement('stock_quantity', $item->quantity);
                }
            }

            $this->applyToInvoice($invoice, Money::fromDecimal($return->applied_amount)->multiplyBy(-1)->toDecimal());

            $description = __('إلغاء مرتجع البيع :no', ['no' => $return->return_no]);
            CustomerTransaction::query()->create([
                'customer_id' => $return->customer_id,
                'transaction_date' => $date,
                'type' => TransactionType::REVERSAL,
                'reference_type' => SalesReturn::class,
                'reference_id' => $return->id,
                'debit' => $return->total,
                'credit' => '0.00',
                'description' => $description,
                'created_by' => $userId,
            ]);

            if (Money::fromDecimal($return->refunded_amount)->toCents() > 0) {
                CustomerTransaction::query()->create([
                    'customer_id' => $return->customer_id,
                    'transaction_date' => $date,
                    'type' => TransactionType::REVERSAL,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $return->id,
                    'debit' => '0.00',
                    'credit' => $return->refunded_amount,
                    'description' => __('عكس المبلغ المردود عن المرتجع :no', ['no' => $return->return_no]),
                    'created_by' => $userId,
                ]);
            }

            $return->update([
                'status' => DocumentStatus::CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $return->fresh(['items', 'invoice', 'customer']);
        });
    }

    // -------------------------------------------------------------- purchases

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function returnablePurchaseLines(PurchaseInvoice $invoice): Collection
    {
        $invoice->loadMissing(['items.activeReturnItems', 'items.material', 'items.unit']);

        return $invoice->items->map(fn (PurchaseInvoiceItem $item) => $this->lineSummary(
            $item,
            LineType::MATERIAL->value,
            $item->material?->name ?? $item->description,
        ));
    }

    /**
     * @param  array{return_date?: ?string, reason: string, notes?: ?string, settlement?: ?string, refund_method?: ?string, items: array<int, array{purchase_invoice_item_id: int, quantity: string|int|float}>}  $data
     */
    public function createPurchaseReturn(PurchaseInvoice $invoice, array $data, int $userId): PurchaseReturn
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertReturnable($invoice);

            $invoice->load('items.activeReturnItems');
            $quote = $this->quote($invoice, $data['items'] ?? [], 'purchase_invoice_item_id', PurchaseReturn::query()
                ->where('purchase_invoice_id', $invoice->id)
                ->where('status', DocumentStatus::POSTED));

            $strict = $this->strictStock();
            $materials = [];

            // Every line is checked before anything is written, so the clerk
            // hears about all shortfalls at once instead of one per attempt.
            foreach ($quote['lines'] as $line) {
                $item = $line['item'];
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();
                $materials[$item->id] = $material;

                if ($strict && $material && bccomp((string) $material->stock_quantity, $line['quantity'], 3) === -1) {
                    throw new InsufficientStockException($material, $line['quantity']);
                }
            }

            $settlement = $this->settle($invoice, $quote['total'], $data);
            $date = $data['return_date'] ?? now()->toDateString();
            $returnNo = $this->numbers->next('PURCHASE_RETURN');
            $label = __('مرتجع شراء :no عن الفاتورة :invoice', ['no' => $returnNo, 'invoice' => $invoice->invoice_no]);

            $return = PurchaseReturn::query()->create([
                'return_no' => $returnNo,
                'purchase_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'return_date' => $date,
                'status' => DocumentStatus::POSTED,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $quote['subtotal'],
                'discount_amount' => $quote['discount_amount'],
                'total' => $quote['total'],
                'applied_amount' => $settlement['applied'],
                'refunded_amount' => $settlement['refunded'],
                'refund_method' => $settlement['method'],
                'created_by' => $userId,
            ]);

            foreach ($quote['lines'] as $line) {
                /** @var PurchaseInvoiceItem $item */
                $item = $line['item'];
                $material = $materials[$item->id];
                $unitCost = $this->boughtUnitCost($item);

                $this->movement($item->material_id, MovementType::PURCHASE_RETURN, MovementDirection::OUT, $line['quantity'],
                    (string) $item->unit_price, MovementSourceType::PURCHASE_RETURN, $return->id, $returnNo, $date, $label, $userId);

                if ($material) {
                    $this->costs->reverseInbound($material, $line['quantity']);
                    $material->decrement('stock_quantity', $line['quantity']);
                }

                PurchaseReturnItem::query()->create([
                    'purchase_return_id' => $return->id,
                    'purchase_invoice_item_id' => $item->id,
                    'material_id' => $item->material_id,
                    'description' => $item->description,
                    'quantity' => $line['quantity'],
                    'unit_price' => $item->unit_price,
                    'line_total' => $line['line_total'],
                    'unit_cost' => $unitCost,
                ]);
            }

            $this->applyToInvoice($invoice, $settlement['applied']);

            SupplierTransaction::query()->create([
                'supplier_id' => $invoice->supplier_id,
                'transaction_date' => $date,
                'type' => TransactionType::PURCHASE_RETURN,
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $return->id,
                'debit' => $quote['total'],
                'credit' => '0.00',
                'description' => $label,
                'created_by' => $userId,
            ]);

            if (Money::fromDecimal($settlement['refunded'])->toCents() > 0) {
                SupplierTransaction::query()->create([
                    'supplier_id' => $invoice->supplier_id,
                    'transaction_date' => $date,
                    'type' => TransactionType::SUPPLIER_REFUND,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $return->id,
                    'debit' => '0.00',
                    'credit' => $settlement['refunded'],
                    'description' => __('مبلغ ردّه المورد عن المرتجع :no', ['no' => $returnNo]),
                    'created_by' => $userId,
                ]);
            }

            return $return->fresh(['items', 'invoice', 'supplier']);
        });
    }

    public function cancelPurchaseReturn(PurchaseReturn $return, int $userId, string $reason): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $userId, $reason) {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== DocumentStatus::POSTED) {
                throw new DocumentLockedException;
            }

            $invoice = PurchaseInvoice::query()->whereKey($return->purchase_invoice_id)->lockForUpdate()->firstOrFail();
            $return->load('items');
            $date = now()->toDateString();

            foreach ($return->items as $item) {
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                $this->reverseMovement(MovementSourceType::PURCHASE_RETURN, $return->id, $item->material_id,
                    MovementType::PURCHASE_RETURN, $return->return_no, $date, $reason, $userId);

                if ($material) {
                    $this->costs->reverseOutbound($material, (string) $item->quantity, (string) $item->unit_cost);
                    $material->increment('stock_quantity', $item->quantity);
                }
            }

            $this->applyToInvoice($invoice, Money::fromDecimal($return->applied_amount)->multiplyBy(-1)->toDecimal());

            SupplierTransaction::query()->create([
                'supplier_id' => $return->supplier_id,
                'transaction_date' => $date,
                'type' => TransactionType::REVERSAL,
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $return->id,
                'debit' => '0.00',
                'credit' => $return->total,
                'description' => __('إلغاء مرتجع الشراء :no', ['no' => $return->return_no]),
                'created_by' => $userId,
            ]);

            if (Money::fromDecimal($return->refunded_amount)->toCents() > 0) {
                SupplierTransaction::query()->create([
                    'supplier_id' => $return->supplier_id,
                    'transaction_date' => $date,
                    'type' => TransactionType::REVERSAL,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $return->id,
                    'debit' => $return->refunded_amount,
                    'credit' => '0.00',
                    'description' => __('عكس المبلغ المردود عن المرتجع :no', ['no' => $return->return_no]),
                    'created_by' => $userId,
                ]);
            }

            $return->update([
                'status' => DocumentStatus::CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $return->fresh(['items', 'invoice', 'supplier']);
        });
    }

    // ---------------------------------------------------------------- internals

    protected function assertReturnable(SalesInvoice|PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== DocumentStatus::POSTED) {
            throw new BusinessRuleException(__('لا يُرتجع إلا من فاتورة مرحَّلة.'));
        }
    }

    /**
     * Price the requested lines.
     *
     * Each line comes back at its share of what the customer was charged for
     * it — line discount included — and the invoice-level discount follows in
     * proportion. A line (or the whole invoice) returned in full takes exactly
     * what is left of it instead, so a run of partial returns can never add
     * up to a cent more or less than the invoice itself.
     *
     * @param  array<int, array<string, mixed>>  $requested
     * @return array{lines: array<int, array{item: SalesInvoiceItem|PurchaseInvoiceItem, quantity: string, line_total: string}>, subtotal: string, discount_amount: string, total: string}
     */
    protected function quote(SalesInvoice|PurchaseInvoice $invoice, array $requested, string $key, $priorReturns): array
    {
        $wanted = [];
        foreach ($requested as $raw) {
            $id = (int) ($raw[$key] ?? 0);
            $quantity = $this->quantity($raw['quantity'] ?? 0);

            if (bccomp($quantity, '0', 3) !== 1) {
                continue;
            }

            if (isset($wanted[$id])) {
                throw new BusinessRuleException(__('السطر مكرّر في المرتجع.'), 'items');
            }

            $wanted[$id] = $quantity;
        }

        if ($wanted === []) {
            throw new BusinessRuleException(__('حدّد كمية مرتجعة لبند واحد على الأقل.'), 'items');
        }

        $items = $invoice->items->keyBy('id');
        $lines = [];
        $subtotal = Money::zero();
        $leftOnInvoice = false;

        foreach ($items as $item) {
            $returned = $this->returnedQuantity($item);
            $returnable = $this->subtractQty((string) $item->quantity, $returned);
            $quantity = $wanted[$item->id] ?? null;

            if ($quantity === null) {
                $leftOnInvoice = $leftOnInvoice || bccomp($returnable, '0', 3) === 1;

                continue;
            }

            if (bccomp($quantity, $returnable, 3) === 1) {
                $name = $item->material?->name ?? $item->service?->name ?? $item->description ?? "#{$item->id}";
                throw new BusinessRuleException(__('الكمية المرتجعة من «:name» (:qty) أكبر من المتبقّي القابل للإرجاع (:left).', [
                    'name' => $name,
                    'qty' => $this->trimQty($quantity),
                    'left' => $this->trimQty($returnable),
                ]), 'items');
            }

            $closesLine = bccomp($quantity, $returnable, 3) === 0;
            $leftOnInvoice = $leftOnInvoice || ! $closesLine;

            $lineTotal = $closesLine
                ? Money::fromDecimal($item->line_total)->subtract(Money::fromDecimal($item->activeReturnItems->sum('line_total')))
                : Money::fromDecimal($item->line_total)->multiplyBy(bcdiv($quantity, (string) $item->quantity, 10));

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'line_total' => $lineTotal->toDecimal()];
            $subtotal = $subtotal->add($lineTotal);
        }

        if (count($lines) !== count($wanted)) {
            throw new BusinessRuleException(__('أحد البنود المطلوبة ليس على هذه الفاتورة.'), 'items');
        }

        $invoiceDiscount = Money::fromDecimal($invoice->discount_amount);
        $invoiceSubtotal = Money::fromDecimal($invoice->subtotal);

        $discount = match (true) {
            $invoiceDiscount->isZero() => Money::zero(),
            ! $leftOnInvoice => $invoiceDiscount->subtract(Money::fromDecimal((clone $priorReturns)->sum('discount_amount'))),
            $invoiceSubtotal->toCents() > 0 => $invoiceDiscount->multiplyBy($subtotal->toCents() / $invoiceSubtotal->toCents()),
            default => Money::zero(),
        };

        if ($discount->isNegative() || $discount->toCents() > $subtotal->toCents()) {
            $discount = $discount->isNegative() ? Money::zero() : $subtotal;
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal->toDecimal(),
            'discount_amount' => $discount->toDecimal(),
            'total' => $subtotal->subtract($discount)->toDecimal(),
        ];
    }

    /**
     * Split a return's value between the invoice's open balance and money that
     * was already paid.
     *
     * @return array{applied: string, refunded: string, method: ?string}
     */
    protected function settle(SalesInvoice|PurchaseInvoice $invoice, string $total, array $data): array
    {
        $value = Money::fromDecimal($total);
        $open = Money::fromDecimal($invoice->remaining);
        $open = $open->isNegative() ? Money::zero() : $open;

        $applied = $value->toCents() <= $open->toCents() ? $value : $open;
        $excess = $value->subtract($applied);

        $refund = ($data['settlement'] ?? self::SETTLE_CREDIT) === self::SETTLE_REFUND && $excess->toCents() > 0;

        return [
            'applied' => $applied->toDecimal(),
            'refunded' => $refund ? $excess->toDecimal() : '0.00',
            'method' => $refund ? ($data['refund_method'] ?? 'CASH') : null,
        ];
    }

    /** Positive amounts close part of the invoice, negative ones reopen it. */
    protected function applyToInvoice(SalesInvoice|PurchaseInvoice $invoice, string $amount): void
    {
        $delta = Money::fromDecimal($amount);

        if ($delta->isZero()) {
            return;
        }

        $invoice->update([
            'returned_amount' => Money::fromDecimal($invoice->returned_amount)->add($delta)->toDecimal(),
            'remaining' => Money::fromDecimal($invoice->remaining)->subtract($delta)->toDecimal(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineSummary(SalesInvoiceItem|PurchaseInvoiceItem $item, string $lineType, ?string $name): array
    {
        $returned = $this->returnedQuantity($item);

        return [
            'id' => $item->id,
            'line_type' => $lineType,
            'name' => $name ?: "#{$item->id}",
            'code' => $item->material?->barcode ?: $item->material?->sku,
            'unit' => $item->unit?->name,
            'material_id' => $item->material_id,
            'stock_quantity' => $item->material?->stock_quantity,
            'quantity' => (string) $item->quantity,
            'returned_quantity' => $returned,
            'returnable_quantity' => $this->subtractQty((string) $item->quantity, $returned),
            'unit_price' => (string) $item->unit_price,
            'line_total' => (string) $item->line_total,
            'returned_total' => Money::fromDecimal($item->activeReturnItems->sum('line_total'))->toDecimal(),
        ];
    }

    protected function returnedQuantity(SalesInvoiceItem|PurchaseInvoiceItem $item): string
    {
        return $this->quantity($item->activeReturnItems->sum(fn ($line) => (float) $line->quantity));
    }

    /** What one unit cost when it left on the sale (FIFO records it; others read the card). */
    protected function soldUnitCost(SalesInvoiceItem $item, ?Material $material): string
    {
        $quantity = (float) $item->quantity;

        if ($item->cost_amount !== null && $quantity > 0) {
            return Money::fromDecimal($item->cost_amount)->multiplyBy(1 / $quantity)->toDecimal();
        }

        return Money::fromDecimal($material?->cost_price ?? 0)->toDecimal();
    }

    /** Net cost of one unit on the purchase line, line discount included. */
    protected function boughtUnitCost(PurchaseInvoiceItem $item): string
    {
        $quantity = (float) $item->quantity;

        if ($quantity <= 0) {
            return Money::fromDecimal($item->unit_price)->toDecimal();
        }

        return Money::fromCents((int) round(Money::fromDecimal($item->line_total)->toCents() / $quantity))->toDecimal();
    }

    protected function movement(
        int $materialId,
        MovementType $type,
        MovementDirection $direction,
        string $quantity,
        string $unitPrice,
        MovementSourceType $sourceType,
        int $sourceId,
        string $referenceNo,
        string $date,
        string $description,
        int $userId,
    ): void {
        StockMovement::query()->create([
            'material_id' => $materialId,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_price' => Money::fromDecimal($unitPrice)->toDecimal(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_no' => $referenceNo,
            'movement_date' => $date,
            'description' => $description,
            'created_by' => $userId,
        ]);
    }

    protected function reverseMovement(
        MovementSourceType $sourceType,
        int $sourceId,
        int $materialId,
        MovementType $type,
        string $referenceNo,
        string $date,
        string $reason,
        int $userId,
    ): void {
        $originals = StockMovement::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('material_id', $materialId)
            ->whereNull('cancels_id')
            ->get();

        foreach ($originals as $original) {
            StockMovement::query()->create([
                'material_id' => $materialId,
                'type' => $type,
                'direction' => $original->direction === MovementDirection::IN ? MovementDirection::OUT : MovementDirection::IN,
                'quantity' => $original->quantity,
                'unit_price' => $original->unit_price,
                'source_type' => MovementSourceType::REVERSAL,
                'source_id' => $original->id,
                'reference_no' => $referenceNo,
                'movement_date' => $date,
                'description' => __('Reversal: ').$reason,
                'created_by' => $userId,
                'cancels_id' => $original->id,
                'cancelled_reason' => $reason,
            ]);

            $original->update(['cancelled_reason' => $reason]);
        }
    }

    protected function strictStock(): bool
    {
        return Setting::get('inventory_sale_policy', 'strict') === 'strict';
    }

    /** Quantities carry three decimals throughout the schema. */
    protected function quantity(int|float|string $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    protected function subtractQty(string $a, string $b): string
    {
        return bcsub($a, $b, 3);
    }

    protected function trimQty(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
