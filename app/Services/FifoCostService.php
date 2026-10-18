<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\Material;
use App\Support\Money;

class FifoCostService
{
    public function receive(
        Material $material,
        string $quantity,
        string $unitCost,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $date = null,
    ): void {
        if (bccomp($quantity, '0', 3) !== 1) {
            return;
        }

        InventoryCostLayer::query()->create([
            'material_id' => $material->id,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => Money::fromDecimal($unitCost)->toDecimal(),
            'received_at' => $date ? substr($date, 0, 10) : now()->toDateString(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        $this->refreshCost($material);
    }

    /** Consumes the oldest layers first and returns the resulting cost. */
    public function consume(Material $material, string $quantity): string
    {
        $remaining = $quantity;
        $costCents = 0;

        $layers = InventoryCostLayer::query()
            ->where('material_id', $material->id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if (bccomp($remaining, '0', 3) !== 1) {
                break;
            }

            $take = bccomp((string) $layer->remaining_quantity, $remaining, 3) === -1
                ? (string) $layer->remaining_quantity
                : $remaining;

            $costCents += Money::fromDecimal($layer->unit_cost)->multiplyBy($take)->toCents();

            $layer->remaining_quantity = $this->subtract((string) $layer->remaining_quantity, $take);
            $layer->save();

            $remaining = $this->subtract($remaining, $take);
        }

        // Stock beyond the recorded layers (negative-stock policy): cost the
        // overflow at the material's last known cost rather than guessing zero.
        if (bccomp($remaining, '0', 3) === 1) {
            $costCents += Money::fromDecimal($material->cost_price)->multiplyBy($remaining)->toCents();
        }

        $this->refreshCost($material);

        return Money::fromCents($costCents)->toDecimal();
    }

    /** Removes the most recently received stock, e.g. on a purchase return. */
    public function remove(Material $material, string $quantity): void
    {
        $remaining = $quantity;

        $layers = InventoryCostLayer::query()
            ->where('material_id', $material->id)
            ->where('remaining_quantity', '>', 0)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if (bccomp($remaining, '0', 3) !== 1) {
                break;
            }

            $take = bccomp((string) $layer->remaining_quantity, $remaining, 3) === -1
                ? (string) $layer->remaining_quantity
                : $remaining;

            $layer->remaining_quantity = $this->subtract((string) $layer->remaining_quantity, $take);
            $layer->save();

            $remaining = $this->subtract($remaining, $take);
        }

        $this->refreshCost($material);
    }

    /**
     * Seeds an opening layer for stock already on hand when a material is
     * switched to FIFO, so its existing balance is not treated as costless.
     */
    public function ensureOpeningLayer(Material $material): void
    {
        if (bccomp((string) $material->stock_quantity, '0', 3) !== 1) {
            return;
        }

        $hasLayers = InventoryCostLayer::query()
            ->where('material_id', $material->id)
            ->exists();

        if ($hasLayers) {
            return;
        }

        $this->receive(
            $material,
            (string) $material->stock_quantity,
            (string) $material->cost_price,
            'OPENING',
            null,
            now()->toDateString(),
        );
    }

    public function refreshCost(Material $material): void
    {
        $row = InventoryCostLayer::query()
            ->where('material_id', $material->id)
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(remaining_quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost), 0) as value')
            ->first();

        $quantity = (float) $row->quantity;

        if ($quantity <= 0) {
            return;
        }

        $material->cost_price = Money::fromDecimal((float) $row->value / $quantity)->toDecimal();
        $material->save();
    }

    private function subtract(string $a, string $b): string
    {
        return number_format((float) $a - (float) $b, 3, '.', '');
    }
}
