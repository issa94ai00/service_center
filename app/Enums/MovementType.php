<?php

namespace App\Enums;

enum MovementType: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case ADJUSTMENT = 'ADJUSTMENT';
    case PURCHASE_RETURN = 'PURCHASE_RETURN';
    case SALE_RETURN = 'SALE_RETURN';
    case OPENING = 'OPENING';
}
