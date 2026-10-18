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
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\SupplierTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly NumberingService $numbers,
        private readonly PaymentService $payments,
        private readonly MaterialCostService $costs,
    ) {}

    /**
     * Persist a draft purchase invoice with its items (draft only).
     */
    public function savePurchaseDraft(PurchaseInvoice $invoice, array $data, int $userId): PurchaseInvoice
    {
        // The invoice row and its items are two tables: without a transaction a
        // failure part-way through syncItems() leaves a headless invoice behind.
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice->fill($this->draftPayload($data, $userId));
            $this->fillDraftTotals($invoice, $data);
            $invoice->save();

            return $this->syncItems($invoice, $data['items']);
        });
    }

    public function saveSalesDraft(SalesInvoice $invoice, array $data, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice->fill($this->draftPayload($data, $userId));
            $this->fillDraftTotals($invoice, $data);
            $invoice->save();

            return $this->syncItems($invoice, $data['items']);
        });
    }

    public function postPurchase(PurchaseInvoice $invoice, int $userId): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            /** @var PurchaseInvoice $invoice */
            $invoice->lockForUpdate();
            $this->assertStatus($invoice, DocumentStatus::DRAFT);

            $invoice->load('items');
            $totals = $this->recomputeTotals($invoice->items, $invoice->discount_type->value, (string) $invoice->discount_value);

            $invoiceNo = $this->numbers->next('PURCHASE_INVOICE');
            $invoice->invoice_no = $invoiceNo;
            $invoice->subtotal = $totals['subtotal'];
            $invoice->discount_amount = $totals['discount_amount'];
            $invoice->total = $totals['total'];
            $invoice->paid_amount = '0.00';
            $invoice->remaining = $totals['total'];
            $invoice->status = DocumentStatus::POSTED;
            $invoice->posted_by = $userId;
            $invoice->posted_at = now();
            $invoice->save();

            foreach ($invoice->items as $item) {
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                $this->createMovement(
                    materialId: $item->material_id,
                    type: MovementType::PURCHASE,
                    direction: MovementDirection::IN,
                    quantity: $item->quantity,
                    unitPrice: $item->unit_price,
                    sourceType: MovementSourceType::PURCHASE_INVOICE,
                    sourceId: $invoice->id,
                    referenceNo: $invoiceNo,
                    date: $invoice->purchase_date,
                    description: __('Purchase invoice :no', ['no' => $invoiceNo]),
                    userId: $userId
                );

                if ($material) {
                    $this->costs->applyInbound($material, (string) $item->quantity, $this->purchaseUnitCost($item), [
                        'type' => MovementSourceType::PURCHASE_INVOICE->value,
                        'id' => $invoice->id,
                        'date' => $invoice->purchase_date,
                    ]);
                    $material->increment('stock_quantity', $item->quantity);
                }
            }

            SupplierTransaction::query()->create([
                'supplier_id' => $invoice->supplier_id,
                'transaction_date' => $invoice->purchase_date,
                'type' => TransactionType::PURCHASE_INVOICE,
                'reference_type' => PurchaseInvoice::class,
                'reference_id' => $invoice->id,
                'credit' => $totals['total'],
                'debit' => '0.00',
                'description' => __('Purchase invoice :no', ['no' => $invoiceNo]),
                'created_by' => $userId,
            ]);

            return $invoice->fresh();
        });
    }

    public function postSales(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            /** @var SalesInvoice $invoice */
            $invoice->lockForUpdate();
            $this->assertStatus($invoice, DocumentStatus::DRAFT);
            $invoice->load('items');

            // The seeded setting is inventory_sale_policy ('strict' | 'permissive');
            // reading a key that is never seeded meant the configured policy was
            // silently ignored and strict was always in force.
            $allowNegative = Setting::get('inventory_sale_policy', 'strict') !== 'strict';

            foreach ($invoice->items->where('line_type', LineType::MATERIAL) as $item) {
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->firstOrFail();
                if (! $allowNegative && Money::fromDecimal($material->stock_quantity)->toCents() < Money::fromDecimal($item->quantity)->toCents()) {
                    throw new InsufficientStockException($material, $item->quantity);
                }
            }

            $totals = $this->recomputeTotals($invoice->items, $invoice->discount_type->value, (string) $invoice->discount_value);

            // An invoice issued from a repair settlement numbers itself on the
            // repair series, so it is never confused with counter sales.
            $isRepair = $invoice->repair_id !== null;
            $invoiceNo = $this->numbers->next($isRepair ? 'REPAIR_INVOICE' : 'SALES_INVOICE');
            // …and says so in every ledger line it writes: a customer reading
            // their statement should see which of these was a device repair
            // without looking the number up.
            $label = $isRepair
                ? __('فاتورة صيانة :no', ['no' => $invoiceNo])
                : __('Sales invoice :no', ['no' => $invoiceNo]);
            $invoice->invoice_no = $invoiceNo;
            $invoice->subtotal = $totals['subtotal'];
            $invoice->discount_amount = $totals['discount_amount'];
            $invoice->total = $totals['total'];
            $invoice->paid_amount = '0.00';
            $invoice->remaining = $totals['total'];
            $invoice->status = DocumentStatus::POSTED;
            $invoice->posted_by = $userId;
            $invoice->posted_at = now();
            $invoice->save();

            foreach ($invoice->items as $item) {
                if ($item->line_type === LineType::MATERIAL) {
                    $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                    $this->createMovement(
                        materialId: $item->material_id,
                        type: MovementType::SALE,
                        direction: MovementDirection::OUT,
                        quantity: $item->quantity,
                        unitPrice: $item->unit_price,
                        sourceType: MovementSourceType::SALES_INVOICE,
                        sourceId: $invoice->id,
                        referenceNo: $invoiceNo,
                        date: $invoice->invoice_date,
                        description: $label,
                        userId: $userId
                    );

                    if ($material) {
                        $cogs = $this->costs->applyOutbound($material, (string) $item->quantity);

                        if ($cogs !== null) {
                            $item->cost_amount = $cogs;
                            $item->save();
                        }

                        $material->decrement('stock_quantity', $item->quantity);
                    }
                }
            }

            CustomerTransaction::query()->create([
                'customer_id' => $invoice->customer_id,
                'transaction_date' => $invoice->invoice_date,
                'type' => TransactionType::SALES_INVOICE,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'debit' => $totals['total'],
                'credit' => '0.00',
                'description' => $label,
                'created_by' => $userId,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * ترحيل فاتورة بيع وقبض ما دفعه العميل عليها في عملية واحدة.
     *
     * الترحيل والقبض حدثان في الدفتر، لكنّهما لحظة واحدة عند الصندوق. جمعهما
     * في معاملة واحدة يعني أنّ سنداً يفشل لا يترك فاتورة مرحَّلة يظنّ الدفتر
     * أنّها دَين، وأنّ فاتورة تفشل لا تترك سند قبض معلَّقاً بلا فاتورة.
     *
     * @param  array{payment_method?: string, notes?: ?string}  $collection
     */
    public function postSalesAndCollect(SalesInvoice $invoice, int $userId, string $amount = '0', array $collection = []): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $userId, $amount, $collection) {
            $posted = $this->postSales($invoice, $userId);

            if (Money::fromDecimal($amount)->isZero()) {
                return $posted;
            }

            $this->payments->collectOnSalesInvoice($posted, $amount, $collection, $userId);

            return $posted->fresh();
        });
    }

    public function cancelPurchase(PurchaseInvoice $invoice, int $userId, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $userId, $reason) {
            /** @var PurchaseInvoice $invoice */
            $invoice->lockForUpdate();
            $this->assertStatus($invoice, DocumentStatus::POSTED);
            $this->assertUnpaid($invoice);

            $invoice->load('items');

            foreach ($invoice->items as $item) {
                $this->reverseMovements(
                    materialId: $item->material_id,
                    sourceType: MovementSourceType::PURCHASE_INVOICE,
                    sourceId: $invoice->id,
                    reversalType: MovementType::PURCHASE_RETURN,
                    referenceNo: $invoice->invoice_no,
                    date: now()->toDateString(),
                    reason: $reason,
                    userId: $userId
                );

                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                if ($material) {
                    $this->costs->reverseInbound($material, (string) $item->quantity);
                    $material->decrement('stock_quantity', $item->quantity);
                }
            }

            SupplierTransaction::query()->create([
                'supplier_id' => $invoice->supplier_id,
                'transaction_date' => now()->toDateString(),
                'type' => TransactionType::REVERSAL,
                'reference_type' => PurchaseInvoice::class,
                'reference_id' => $invoice->id,
                'debit' => $invoice->total,
                'credit' => '0.00',
                'description' => __('Cancelled purchase invoice :no', ['no' => $invoice->invoice_no]),
                'created_by' => $userId,
            ]);

            $invoice->status = DocumentStatus::CANCELLED;
            $invoice->cancelled_by = $userId;
            $invoice->cancelled_at = now();
            $invoice->cancellation_reason = $reason;
            $invoice->save();

            return $invoice->fresh();
        });
    }

    public function cancelSales(SalesInvoice $invoice, int $userId, string $reason): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $userId, $reason) {
            /** @var SalesInvoice $invoice */
            $invoice->lockForUpdate();
            $this->assertStatus($invoice, DocumentStatus::POSTED);
            $this->assertUnpaid($invoice);
            $invoice->load('items');

            foreach ($invoice->items->where('line_type', LineType::MATERIAL) as $item) {
                $this->reverseMovements(
                    materialId: $item->material_id,
                    sourceType: MovementSourceType::SALES_INVOICE,
                    sourceId: $invoice->id,
                    reversalType: MovementType::SALE_RETURN,
                    referenceNo: $invoice->invoice_no,
                    date: now()->toDateString(),
                    reason: $reason,
                    userId: $userId
                );

                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->first();

                if ($material) {
                    $this->costs->reverseOutbound($material, (string) $item->quantity, $this->returnUnitCost($item, $material));
                    $material->increment('stock_quantity', $item->quantity);
                }
            }

            CustomerTransaction::query()->create([
                'customer_id' => $invoice->customer_id,
                'transaction_date' => now()->toDateString(),
                'type' => TransactionType::REVERSAL,
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'debit' => '0.00',
                'credit' => $invoice->total,
                'description' => $invoice->repair_id
                    ? __('إلغاء فاتورة صيانة :no', ['no' => $invoice->invoice_no])
                    : __('Cancelled sales invoice :no', ['no' => $invoice->invoice_no]),
                'created_by' => $userId,
            ]);

            $invoice->status = DocumentStatus::CANCELLED;
            $invoice->cancelled_by = $userId;
            $invoice->cancelled_at = now();
            $invoice->cancellation_reason = $reason;
            $invoice->save();

            return $invoice->fresh();
        });
    }

    /**
     * Delete a draft invoice with its items.
     */
    public function deleteDraft(PurchaseInvoice|SalesInvoice $invoice): void
    {
        $this->assertStatus($invoice, DocumentStatus::DRAFT);
        $invoice->items()->delete();
        $invoice->delete();
    }

    // ---------------------------------------------------------------- internals

    protected function draftPayload(array $data, int $userId): array
    {
        $base = [
            // Set explicitly rather than leaning on the column default: create()
            // does not read defaults back, so the model returned to the caller
            // (and serialised into the API response) would carry a null status.
            'status' => DocumentStatus::DRAFT,
            'discount_type' => $data['discount_type'] ?? 'NONE',
            'discount_value' => $data['discount_value'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ];

        return match (true) {
            isset($data['supplier_id']) => [...$base,
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
            ],
            isset($data['customer_id']) => [...$base,
                'customer_id' => $data['customer_id'],
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
            ],
            default => throw new BusinessRuleException('Invalid invoice data.'),
        };
    }

    protected function fillDraftTotals(PurchaseInvoice|SalesInvoice $invoice, array $data): void
    {
        $totals = $this->computeRawTotals(
            $data['items'],
            $data['discount_type'] ?? 'NONE',
            (string) ($data['discount_value'] ?? 0),
        );

        $invoice->subtotal = $totals['subtotal'];
        $invoice->discount_amount = $totals['discount_amount'];
        $invoice->total = $totals['total'];
        $invoice->paid_amount = '0.00';
        $invoice->remaining = $totals['total'];
    }

    protected function computeRawTotals(array $items, string $discountType, string $discountValue): array
    {
        $subtotal = Money::zero();
        foreach ($items as $raw) {
            $lineTotal = Money::fromDecimal($raw['unit_price'])
                ->multiplyBy($raw['quantity'])
                ->subtract(Money::fromDecimal($raw['discount_amount'] ?? 0));
            if ($lineTotal->isNegative()) {
                throw new BusinessRuleException(__('Line total cannot be negative.'), 'items');
            }
            $subtotal = $subtotal->add($lineTotal);
        }

        $discountAmount = match (strtoupper($discountType)) {
            'PERCENT' => $subtotal->percentage($discountValue),
            'FIXED' => Money::fromDecimal($discountValue),
            default => Money::zero(),
        };

        if ($discountAmount->isNegative() || $discountAmount->toCents() > $subtotal->toCents()) {
            throw new BusinessRuleException(__('Discount cannot exceed subtotal.'), 'discount_value');
        }

        return [
            'subtotal' => $subtotal->toDecimal(),
            'discount_amount' => $discountAmount->toDecimal(),
            'total' => $subtotal->subtract($discountAmount)->toDecimal(),
        ];
    }

    protected function syncItems(PurchaseInvoice|SalesInvoice $invoice, array $items): PurchaseInvoice|SalesInvoice
    {
        foreach (array_values($items) as $index => $raw) {
            $unitId = $raw['unit_id'] ?? null;

            // The payload carries the line type as a string; comparing it to the
            // enum case was always false, so a service line never inherited its
            // service's unit and printed without one.
            if ($invoice instanceof SalesInvoice && $raw['line_type'] === LineType::SERVICE->value && ! empty($raw['service_id'])) {
                $unitId ??= Service::withTrashed()->whereKey($raw['service_id'])->value('unit_id');
            } elseif (! empty($raw['material_id'])) {
                $unitId ??= Material::withTrashed()->whereKey($raw['material_id'])->value('unit_id');
            }

            $common = [
                'description' => $raw['description'] ?? null,
                'unit_id' => $raw['unit_id'] ?? $unitId,
                'quantity' => $raw['quantity'],
                'unit_price' => $raw['unit_price'],
                'discount_amount' => $raw['discount_amount'] ?? 0,
                'sort' => $index,
            ];

            $lineTotal = Money::fromDecimal($raw['unit_price'])
                ->multiplyBy($raw['quantity'])
                ->subtract(Money::fromDecimal($raw['discount_amount'] ?? 0));

            if ($lineTotal->isNegative()) {
                throw new BusinessRuleException(__('Line total cannot be negative.'), 'items');
            }
            $common['line_total'] = $lineTotal->toDecimal();

            $payload = collect($common)->union(match (true) {
                $invoice instanceof PurchaseInvoice => [
                    'purchase_invoice_id' => $invoice->id,
                    'material_id' => $raw['material_id'],
                ],
                default => [
                    'sales_invoice_id' => $invoice->id,
                    'line_type' => $raw['line_type'],
                    'service_id' => $raw['service_id'] ?? null,
                    'material_id' => $raw['material_id'] ?? null,
                ],
            })->all();

            if ($invoice instanceof PurchaseInvoice) {
                PurchaseInvoiceItem::query()->create($payload);
            } else {
                SalesInvoiceItem::query()->create($payload);
            }
        }

        return $invoice;
    }

    protected function purchaseUnitCost(PurchaseInvoiceItem $item): string
    {
        $netCents = Money::fromDecimal($item->unit_price)
            ->multiplyBy($item->quantity)
            ->subtract(Money::fromDecimal($item->discount_amount))
            ->toCents();

        $quantity = (float) $item->quantity;

        if ($quantity <= 0) {
            return Money::fromDecimal($item->unit_price)->toDecimal();
        }

        return Money::fromCents((int) round($netCents / $quantity))->toDecimal();
    }

    protected function returnUnitCost(SalesInvoiceItem $item, Material $material): string
    {
        $quantity = (float) $item->quantity;

        if ($item->cost_amount !== null && $quantity > 0) {
            return Money::fromDecimal($item->cost_amount)->multiplyBy(1 / $quantity)->toDecimal();
        }

        return (string) $material->cost_price;
    }

    protected function recomputeTotals($items, string $discountType, string $discountValue): array
    {
        $subtotal = Money::zero();
        foreach ($items as $item) {
            $lineTotal = Money::fromDecimal($item->unit_price)
                ->multiplyBy($item->quantity)
                ->subtract(Money::fromDecimal($item->discount_amount));
            if ($lineTotal->isNegative()) {
                throw new BusinessRuleException(__('Line total cannot be negative.'), 'items');
            }
            $subtotal = $subtotal->add($lineTotal);
        }

        $discountAmount = match (strtoupper($discountType)) {
            'PERCENT' => $subtotal->percentage($discountValue),
            'FIXED' => Money::fromDecimal($discountValue),
            default => Money::zero(),
        };

        if ($discountAmount->isNegative() || $discountAmount->toCents() > $subtotal->toCents()) {
            throw new BusinessRuleException(__('Discount cannot exceed subtotal.'), 'discount_value');
        }

        return [
            'subtotal' => $subtotal->toDecimal(),
            'discount_amount' => $discountAmount->toDecimal(),
            'total' => $subtotal->subtract($discountAmount)->toDecimal(),
        ];
    }

    protected function createMovement(
        int $materialId,
        MovementType $type,
        MovementDirection $direction,
        string $quantity,
        string $unitPrice,
        MovementSourceType $sourceType,
        int $sourceId,
        ?string $referenceNo,
        string $date,
        string $description,
        int $userId,
    ): void {
        StockMovement::query()->create([
            'material_id' => $materialId,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_no' => $referenceNo,
            'movement_date' => $date,
            'description' => $description,
            'created_by' => $userId,
        ]);
    }

    protected function reverseMovements(
        int $materialId,
        MovementSourceType $sourceType,
        int $sourceId,
        MovementType $reversalType,
        ?string $referenceNo,
        string $date,
        string $reason,
        int $userId,
    ): void {
        /** @var StockMovement[] $originals */
        $originals = StockMovement::query()
            ->where('material_id', $materialId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNull('cancels_id')
            ->get();

        if ($originals->isEmpty()) {
            return;
        }

        foreach ($originals as $original) {
            StockMovement::query()->create([
                'material_id' => $materialId,
                'type' => $reversalType,
                'direction' => $original->direction->value === MovementDirection::IN->value
                    ? MovementDirection::OUT
                    : MovementDirection::IN,
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

    protected function assertStatus(PurchaseInvoice|SalesInvoice $invoice, DocumentStatus $expected): void
    {
        if ($invoice->status !== $expected) {
            throw new DocumentLockedException;
        }
    }

    protected function assertUnpaid(PurchaseInvoice|SalesInvoice $invoice): void
    {
        if (Money::fromDecimal($invoice->paid_amount)->toCents() > 0) {
            throw new BusinessRuleException(__('This document has payments (allocations) and cannot be cancelled automatically.'));
        }
    }
}
