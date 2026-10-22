<?php

namespace App\Enums;

enum MovementSourceType: string
{
    case SALES_INVOICE = 'SALES_INVOICE';
    case PURCHASE_INVOICE = 'PURCHASE_INVOICE';
    case INVENTORY_ADJUSTMENT = 'INVENTORY_ADJUSTMENT';
    case MANUAL = 'MANUAL';
    case REVERSAL = 'REVERSAL';
    case SALES_RETURN = 'SALES_RETURN';
    case PURCHASE_RETURN = 'PURCHASE_RETURN';
}
