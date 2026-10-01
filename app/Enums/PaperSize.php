<?php

namespace App\Enums;

/**
 * The paper a document can be printed on.
 *
 * Sheet sizes (A4, A5) paginate with a running footer. Thermal rolls have a
 * fixed width and no page length — the renderer sizes the single page to the
 * content, so a receipt never feeds a metre of blank paper.
 */
enum PaperSize: string
{
    case A4 = 'a4';
    case A5 = 'a5';
    case THERMAL_80 = '80mm';
    case THERMAL_58 = '58mm';

    public function label(): string
    {
        return match ($this) {
            self::A4 => 'A4',
            self::A5 => 'A5',
            self::THERMAL_80 => 'حراري 80 مم',
            self::THERMAL_58 => 'حراري 58 مم',
        };
    }

    public function isThermal(): bool
    {
        return $this === self::THERMAL_80 || $this === self::THERMAL_58;
    }

    /** Paper width in millimetres. */
    public function width(): int
    {
        return match ($this) {
            self::A4 => 210,
            self::A5 => 148,
            self::THERMAL_80 => 80,
            self::THERMAL_58 => 58,
        };
    }

    /**
     * Page margins in millimetres: top, right, bottom, left.
     *
     * Thermal heads cannot print to the edge either, but the printable band is
     * narrow enough that every millimetre of margin is a millimetre of text lost.
     *
     * @return array{top: int, right: int, bottom: int, left: int, footer: int}
     */
    public function margins(): array
    {
        return match ($this) {
            self::A4 => ['top' => 12, 'right' => 10, 'bottom' => 16, 'left' => 10, 'footer' => 6],
            self::A5 => ['top' => 8, 'right' => 7, 'bottom' => 13, 'left' => 7, 'footer' => 5],
            self::THERMAL_80 => ['top' => 3, 'right' => 4, 'bottom' => 4, 'left' => 4, 'footer' => 0],
            self::THERMAL_58 => ['top' => 2, 'right' => 2, 'bottom' => 4, 'left' => 2, 'footer' => 0],
        };
    }

    /** Base body font size in points. */
    public function fontSize(): float
    {
        return match ($this) {
            self::A4 => 9,
            self::A5 => 8,
            self::THERMAL_80 => 8.5,
            self::THERMAL_58 => 7.5,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
