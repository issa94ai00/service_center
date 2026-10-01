<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\MovementDirection;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DocumentLockedException;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\Material;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Manual stock corrections.
 *
 * An adjustment is posted the moment it is created — there is no draft stage,
 * because the only reason to record one is that the physical count has already
 * changed. Like an invoice it never edits history: cancelling appends a
 * compensating movement rather than deleting the original.
 */
class InventoryService
{
    public function __construct(private readonly NumberingService $numbers) {}

    /**
     * @param  array{reason: string, items: array<int, array{material_id: int, direction: string, quantity: string, unit_cost?: ?string, reason?: ?string}>}  $data
     */
    public function adjust(array $data, int $userId): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            if (empty($data['items'])) {
                throw new BusinessRuleException(__('An adjustment must contain at least one line.'), 'items');
            }

            $adjustment = InventoryAdjustment::query()->create([
                'adjustment_no' => $this->numbers->next('INVENTORY_ADJUSTMENT'),
                'status' => DocumentStatus::POSTED,
                'reason' => $data['reason'],
                'adjusted_by' => $userId,
                'posted_at' => now(),
            ]);

            $date = now()->toDateString();
            $strict = Setting::get('inventory_sale_policy', 'strict') === 'strict';

            foreach ($data['items'] as $raw) {
                $material = Material::withTrashed()->whereKey($raw['material_id'])->lockForUpdate()->firstOrFail();

                $direction = MovementDirection::from($raw['direction']);
                $quantity = $this->quantity($raw['quantity']);

                if (bccomp($quantity, '0', 3) !== 1) {
                    throw new BusinessRuleException(__('Adjustment quantity must be greater than zero.'), 'items');
                }

                // An outward correction cannot take more than is on hand, unless
                // the inventory policy explicitly permits negative stock.
                if ($strict && $direction === MovementDirection::OUT
                    && bccomp((string) $material->stock_quantity, $quantity, 3) === -1) {
                    throw new InsufficientStockException($material, $quantity);
                }

                $unitCost = $raw['unit_cost'] ?? $material->cost_price;

                InventoryAdjustmentItem::query()->create([
                    'inventory_adjustment_id' => $adjustment->id,
                    'material_id' => $material->id,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'unit_cost' => Money::fromDecimal($unitCost)->toDecimal(),
                    'reason' => $raw['reason'] ?? null,
                ]);

                StockMovement::query()->create([
                    'material_id' => $material->id,
                    'type' => MovementType::ADJUSTMENT,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'unit_price' => Money::fromDecimal($unitCost)->toDecimal(),
                    'source_type' => MovementSourceType::INVENTORY_ADJUSTMENT,
                    'source_id' => $adjustment->id,
                    'reference_no' => $adjustment->adjustment_no,
                    'movement_date' => $date,
                    'description' => __('Inventory adjustment :no', ['no' => $adjustment->adjustment_no]),
                    'created_by' => $userId,
                ]);

                $this->applyToStock($material->id, $direction, $quantity);
            }

            return $adjustment->fresh(['items.material', 'adjuster']);
        });
    }

    public function cancel(InventoryAdjustment $adjustment, int $userId, string $reason): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $userId, $reason) {
            if ($adjustment->status !== DocumentStatus::POSTED) {
                throw new DocumentLockedException;
            }

            $adjustment->load('items');
            $date = now()->toDateString();

            foreach ($adjustment->items as $item) {
                $material = Material::withTrashed()->whereKey($item->material_id)->lockForUpdate()->firstOrFail();

                $original = StockMovement::query()
                    ->where('source_type', MovementSourceType::INVENTORY_ADJUSTMENT)
                    ->where('source_id', $adjustment->id)
                    ->where('material_id', $item->material_id)
                    ->whereNull('cancels_id')
                    ->first();

                $opposite = $item->direction === MovementDirection::IN
                    ? MovementDirection::OUT
                    : MovementDirection::IN;

                StockMovement::query()->create([
                    'material_id' => $item->material_id,
                    'type' => MovementType::ADJUSTMENT,
                    'direction' => $opposite,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_cost ?? '0.00',
                    'source_type' => MovementSourceType::REVERSAL,
                    'source_id' => $original?->id ?? $adjustment->id,
                    'reference_no' => $adjustment->adjustment_no,
                    'movement_date' => $date,
                    'description' => __('Reversal: ').$reason,
                    'created_by' => $userId,
                    'cancels_id' => $original?->id,
                    'cancelled_reason' => $reason,
                ]);

                $original?->update(['cancelled_reason' => $reason]);

                $this->applyToStock($item->material_id, $opposite, (string) $item->quantity);
            }

            $adjustment->update([
                'status' => DocumentStatus::CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $adjustment->fresh(['items.material', 'adjuster']);
        });
    }

    // ---------------------------------------------------------------- internals

    protected function applyToStock(int $materialId, MovementDirection $direction, string $quantity): void
    {
        $column = Material::withTrashed()->whereKey($materialId);

        $direction === MovementDirection::IN
            ? $column->increment('stock_quantity', $quantity)
            : $column->decrement('stock_quantity', $quantity);
    }

    /** Quantities carry three decimals throughout the schema. */
    protected function quantity(int|float|string $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
