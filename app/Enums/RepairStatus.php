<?php

namespace App\Enums;

enum RepairStatus: string
{
    case RECEIVED = 'RECEIVED';
    case DIAGNOSING = 'DIAGNOSING';
    case UNDER_REPAIR = 'UNDER_REPAIR';
    case WAITING_APPROVAL = 'WAITING_APPROVAL';
    case READY = 'READY';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'مستلَم',
            self::DIAGNOSING => 'قيد الفحص',
            self::UNDER_REPAIR => 'قيد الإصلاح',
            self::WAITING_APPROVAL => 'بانتظار موافقة العميل',
            self::READY => 'جاهز للتسليم',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
        };
    }
}
