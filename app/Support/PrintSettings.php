<?php

namespace App\Support;

use App\Enums\PaperSize;
use App\Models\Setting;

/**
 * The shop-wide printing defaults, read from the settings table.
 *
 * Each printable document kind has its own default paper — a repair counter
 * wants a thermal slip while purchase invoices still go out on A4 — and the
 * kinds differ in which papers they can be laid out on at all.
 */
class PrintSettings
{
    public const INVOICE = 'invoice';

    public const PAYMENT = 'payment';

    public const REPAIR = 'repair';

    public const STATEMENT = 'statement';

    /**
     * Papers each document kind has a layout for. A running statement on a
     * till roll is an unreadable ribbon, so statements stay on sheets.
     *
     * @return array<string, array<int, PaperSize>>
     */
    public static function supported(): array
    {
        $all = PaperSize::cases();

        return [
            self::INVOICE => $all,
            self::PAYMENT => $all,
            self::REPAIR => $all,
            self::STATEMENT => [PaperSize::A4, PaperSize::A5],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedValues(string $kind): array
    {
        return array_map(fn (PaperSize $paper) => $paper->value, self::supported()[$kind] ?? []);
    }

    public static function settingKey(string $kind): string
    {
        return 'print_paper_'.$kind;
    }

    /**
     * The paper a document prints on: the one asked for if this kind supports
     * it, otherwise the shop default, otherwise A4.
     */
    public function paperFor(string $kind, ?PaperSize $requested = null): PaperSize
    {
        $supported = self::supported()[$kind] ?? [PaperSize::A4];

        if ($requested !== null && in_array($requested, $supported, true)) {
            return $requested;
        }

        $default = PaperSize::tryFrom((string) Setting::get(self::settingKey($kind), ''));

        return $default !== null && in_array($default, $supported, true) ? $default : PaperSize::A4;
    }

    /**
     * The default paper per document kind.
     *
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return collect(array_keys(self::supported()))
            ->mapWithKeys(fn (string $kind) => [$kind => $this->paperFor($kind)->value])
            ->all();
    }

    /**
     * Printed extras shared by every document.
     *
     * @return array{footer_note: ?string, repair_terms: ?string, show_signatures: bool}
     */
    public function options(): array
    {
        return [
            'footer_note' => filled($note = Setting::get('print_footer_note')) ? $note : null,
            'repair_terms' => filled($terms = Setting::get('print_repair_terms')) ? $terms : null,
            'show_signatures' => (bool) Setting::get('print_show_signatures', true),
        ];
    }
}
