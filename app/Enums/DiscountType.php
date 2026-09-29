<?php

namespace App\Enums;

enum DiscountType: string
{
    case NONE = 'NONE';
    case PERCENT = 'PERCENT';
    case FIXED = 'FIXED';
}
