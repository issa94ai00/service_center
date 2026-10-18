<?php

namespace App\Enums;

enum CostMethod: string
{
    case MANUAL = 'MANUAL';
    case WEIGHTED_AVERAGE = 'WEIGHTED_AVERAGE';
    case LAST_PURCHASE = 'LAST_PURCHASE';
    case FIFO = 'FIFO';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'يدوية',
            self::WEIGHTED_AVERAGE => 'المتوسط المرحّل',
            self::LAST_PURCHASE => 'آخر سعر شراء',
            self::FIFO => 'الوارد أولاً (FIFO)',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::MANUAL => 'التكلفة تبقى كما تُدخلها يدوياً ولا تتغيّر مع عمليات الشراء',
            self::WEIGHTED_AVERAGE => 'تتحدّث التكلفة مع كل توريد بمتوسط مرجّح للكميات والأسعار',
            self::LAST_PURCHASE => 'تُستبدل التكلفة بسعر آخر فاتورة شراء مرحّلة',
            self::FIFO => 'تُصرف الكميات من أقدم دفعات الشراء أولاً بتكلفتها الفعلية',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::MANUAL;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
