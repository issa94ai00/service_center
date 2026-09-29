<?php

namespace App\Support;

/**
 * Spells a money amount in Arabic words, for the "المبلغ كتابةً" line that
 * Arabic receipts are expected to carry.
 *
 * Arabic number grammar inflects the counted noun by the count: dual forms for
 * two, the plural of paucity for 3–10, and the accusative singular for 11–99.
 * Those three cases are handled per scale (thousand / million / billion).
 */
final class ArabicNumberToWords
{
    private const ONES = [
        '', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة',
        'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر',
        'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر',
    ];

    private const TENS = [
        20 => 'عشرون', 30 => 'ثلاثون', 40 => 'أربعون', 50 => 'خمسون',
        60 => 'ستون', 70 => 'سبعون', 80 => 'ثمانون', 90 => 'تسعون',
    ];

    private const HUNDREDS = [
        100 => 'مئة', 200 => 'مئتان', 300 => 'ثلاثمئة', 400 => 'أربعمئة', 500 => 'خمسمئة',
        600 => 'ستمئة', 700 => 'سبعمئة', 800 => 'ثمانمئة', 900 => 'تسعمئة',
    ];

    /** [singular, dual, plural (3–10), accusative singular (11–99)] per scale. */
    private const SCALES = [
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات', 'مليارًا'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين', 'مليونًا'],
        1_000 => ['ألف', 'ألفان', 'آلاف', 'ألفًا'],
    ];

    /**
     * @param  string  $currency  the major unit, e.g. "ليرة سورية"
     * @param  string  $fraction  the minor unit, e.g. "قرش"
     */
    public static function money(
        int|float|string $amount,
        string $currency = 'ليرة سورية',
        string $fraction = 'قرش',
    ): string {
        $cents = Money::fromDecimal($amount)->toCents();
        $negative = $cents < 0;
        $cents = abs($cents);

        $major = intdiv($cents, 100);
        $minor = $cents % 100;

        $parts = [];

        if ($major > 0 || $minor === 0) {
            $parts[] = trim(self::integer($major).' '.$currency);
        }

        if ($minor > 0) {
            $parts[] = trim(self::integer($minor).' '.$fraction);
        }

        $words = implode(' و', $parts);

        // Conventional Arabic receipt formula: فقط … لا غير.
        return 'فقط '.trim(($negative ? 'سالب ' : '').$words).' لا غير';
    }

    /**
     * Spell a non-negative integer.
     */
    public static function integer(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $parts = [];

        foreach (self::SCALES as $value => $forms) {
            if ($number < $value) {
                continue;
            }

            $count = intdiv($number, $value);
            $number %= $value;
            $parts[] = self::scale($count, $forms);
        }

        if ($number > 0) {
            $parts[] = self::underThousand($number);
        }

        return implode(' و', $parts);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $forms
     */
    private static function scale(int $count, array $forms): string
    {
        [$singular, $dual, $plural, $accusative] = $forms;

        // The counted noun agrees with the FINAL constituent of the count, so
        // 500 ("خمسمئة مليون") takes the singular while 85 ("خمسة وثمانون
        // ألفًا") takes the accusative.
        $remainder = $count % 100;

        $noun = match (true) {
            $count === 1 => $singular,
            $count === 2 => $dual,
            $remainder === 0 => $singular,
            // Singular (not dual) past two: the spelled count already carries
            // the "two", so the dual would say it twice — "مئة واثنان ألفان".
            $remainder <= 2 => $singular,
            $remainder <= 10 => $plural,
            default => $accusative,
        };

        return $count <= 2
            ? $noun
            : self::underThousand($count).' '.$noun;
    }

    private static function underThousand(int $number): string
    {
        $parts = [];

        if ($number >= 100) {
            $hundreds = intdiv($number, 100) * 100;
            $parts[] = self::HUNDREDS[$hundreds];
            $number %= 100;
        }

        if ($number >= 20) {
            $tens = intdiv($number, 10) * 10;
            $unit = $number % 10;
            // Arabic reads the unit before the ten: "واحد وعشرون".
            $parts[] = $unit > 0
                ? self::ONES[$unit].' و'.self::TENS[$tens]
                : self::TENS[$tens];
        } elseif ($number > 0) {
            $parts[] = self::ONES[$number];
        }

        return implode(' و', $parts);
    }
}
