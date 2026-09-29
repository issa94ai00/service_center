<?php

namespace App\Exceptions;

use App\Models\Material;

class InsufficientStockException extends BusinessRuleException
{
    public function __construct(public readonly Material $material, public readonly string $requestedQuantity)
    {
        parent::__construct(
            __("Insufficient stock for material ':material' (:requested requested, :available available).", [
                'material' => $material->name,
                'requested' => rtrim(rtrim($requestedQuantity, '0'), '.'),
                'available' => rtrim(rtrim($material->stock_quantity, '0'), '.'),
            ]),
            'items'
        );
    }
}
