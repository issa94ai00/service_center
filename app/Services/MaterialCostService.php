<?php

namespace App\Services;

use App\Enums\CostMethod;
use App\Models\Material;
use App\Support\Money;

class MaterialCostService
{
    public function __construct(private readonly FifoCostService $fifo) {}

    /**
     * @param  array{type?: ?string, id?: ?int, date?: ?string}  $source
     */
    public function applyInbound(Material $material, string $quantity, string $netUnitCost, array $source = []): void
    {
        $method = $material->cost_method ?? CostMethod::MANUAL;

        if (! $method->isAutomatic()) {
            return;
        }

        if (bccomp($quantity, '0', 3) !== 1) {
            return;
        }

        if ($method === CostMethod::FIFO) {
            $this->fifo->receive(
                $material,
                $quantity,
                $netUnitCost,
                $source['type'] ?? null,
                $source['id'] ?? null,
                $source['date'] ?? null,
            );

            return;
        }

        if ($method === CostMethod::LAST_PURCHASE) {
            $material->cost_price = Money::fromDecimal($netUnitCost)->toDecimal();
            $material->save();

            return;
        }

        $this->blendWeightedAverage($material, $quantity, $netUnitCost);
    }

    /** Returns the cost of the goods leaving stock, or null for non-FIFO methods. */
    public function applyOutbound(Material $material, string $quantity): ?string
    {
        $method = $material->cost_method ?? CostMethod::MANUAL;

        if ($method !== CostMethod::FIFO || bccomp($quantity, '0', 3) !== 1) {
            return null;
        }

        return $this->fifo->consume($material, $quantity);
    }

    /** Goods leaving stock outside a sale (a cancelled receipt). */
    public function reverseInbound(Material $material, string $quantity): void
    {
        if (($material->cost_method ?? CostMethod::MANUAL) !== CostMethod::FIFO) {
            return;
        }

        $this->fifo->remove($material, $quantity);
    }

    /** Goods returning to stock (a cancelled sale) at their recorded cost. */
    public function reverseOutbound(Material $material, string $quantity, string $unitCost): void
    {
        if (($material->cost_method ?? CostMethod::MANUAL) !== CostMethod::FIFO) {
            return;
        }

        $this->fifo->receive($material, $quantity, $unitCost, 'REVERSAL', null, now()->toDateString());
    }

    public function seedFifoOpening(Material $material): void
    {
        if (($material->cost_method ?? CostMethod::MANUAL) === CostMethod::FIFO) {
            $this->fifo->ensureOpeningLayer($material);
        }
    }

    private function blendWeightedAverage(Material $material, string $quantity, string $netUnitCost): void
    {
        $oldQuantity = (float) $material->stock_quantity;
        $oldCostCents = Money::fromDecimal($material->cost_price)->toCents();
        $newQuantity = $oldQuantity + (float) $quantity;
        $newCostCents = Money::fromDecimal($netUnitCost)->multiplyBy($quantity)->toCents();

        $blendedCents = $newQuantity > 0
            ? (int) round(($oldQuantity * $oldCostCents + $newCostCents) / $newQuantity)
            : $newCostCents;

        $material->cost_price = Money::fromCents($blendedCents)->toDecimal();
        $material->save();
    }
}
