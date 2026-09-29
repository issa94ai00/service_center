<?php

namespace App\Enums;

enum ServiceType: string
{
    case METER_BASED = 'METER_BASED';
    case MANUAL = 'MANUAL';
}
