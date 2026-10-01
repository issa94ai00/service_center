<?php

namespace App\Services\Pdf;

use App\Enums\PaperSize;
use App\Exceptions\BusinessRuleException;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Thin wrapper around mPDF.
 *
 * All engine knowledge lives here: every document elsewhere in the app is a
 * Blade view plus data, so swapping the engine means rewriting this class only.
 *
 * mPDF is used because it performs Arabic letter joining and bidirectional
 * reordering itself — DomPDF renders Arabic as disconnected, reversed glyphs.
 * The bundled XB Riyaz face carries the Arabic coverage plus a real bold, which
 * the document headings need.
 */
class PdfRenderer
{
    protected const ARABIC_FONT = 'xbriyaz';

    /**
     * A thermal receipt is laid out on a page this tall first, to measure it.
     * Anything longer than this paginates onto further roll-width pages.
     */
    protected const THERMAL_PROBE_HEIGHT = 1500;

    /** No receipt is shorter than this, so a near-empty one still tears cleanly. */
    protected const THERMAL_MIN_HEIGHT = 60;

    /**
     * Render a Blade view to raw PDF bytes.
     *
     * The view receives the paper as $paper so it can size its type to it.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = [], ?string $footerTitle = null, PaperSize $paper = PaperSize::A4): string
    {
        $html = view($view, $data + ['paper' => $paper])->render();

        try {
            $pdf = $paper->isThermal()
                ? $this->thermal($html, $paper)
                : $this->sheet($html, $paper, $footerTitle);

            return $pdf->Output('', 'S');
        } catch (MpdfException $e) {
            report($e);

            throw new BusinessRuleException('تعذّر إنشاء ملف PDF.');
        }
    }

    protected function sheet(string $html, PaperSize $paper, ?string $footerTitle): Mpdf
    {
        $pdf = $this->engine($paper, $paper === PaperSize::A5 ? 'A5' : 'A4');

        if ($footerTitle !== null) {
            $pdf->SetHTMLFooter($this->footer($footerTitle));
        }

        $pdf->WriteHTML($html);

        return $pdf;
    }

    /**
     * A roll has no page length, so the receipt is laid out twice: once on a
     * very tall page to measure where the content ends, then again on a page
     * cut to exactly that height. A receipt too long for the probe page keeps
     * the probe's pagination rather than being truncated.
     */
    protected function thermal(string $html, PaperSize $paper): Mpdf
    {
        $probe = $this->engine($paper, [$paper->width(), self::THERMAL_PROBE_HEIGHT]);
        $probe->WriteHTML($html);

        if ($probe->page > 1) {
            return $probe;
        }

        $height = max(self::THERMAL_MIN_HEIGHT, (int) ceil($probe->y + $paper->margins()['bottom']));

        $pdf = $this->engine($paper, [$paper->width(), $height]);
        $pdf->WriteHTML($html);

        return $pdf;
    }

    /**
     * @param  string|array{0: int, 1: int}  $format
     */
    protected function engine(PaperSize $paper, string|array $format): Mpdf
    {
        $temp = storage_path('app/mpdf');

        if (! is_dir($temp)) {
            mkdir($temp, 0775, true);
        }

        $margins = $paper->margins();

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'directionality' => 'rtl',
            'default_font' => self::ARABIC_FONT,
            'default_font_size' => $paper->fontSize(),
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            'margin_left' => $margins['left'],
            'margin_right' => $margins['right'],
            'margin_footer' => $margins['footer'],
            'tempDir' => $temp,
            // Keep Latin runs (document numbers, dates) in a Latin face rather
            // than forcing them through the Arabic one.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
    }

    protected function footer(string $title): string
    {
        $title = e($title);
        $printed = e(now()->format('Y-m-d H:i'));

        return <<<HTML
        <table width="100%" style="font-size:7pt;color:#666;border-top:0.5px solid #ccc;padding-top:2mm;">
            <tr>
                <td width="40%" style="text-align:right;">{$title}</td>
                <td width="30%" style="text-align:center;">صفحة {PAGENO} من {nbpg}</td>
                <td width="30%" style="text-align:left;" dir="ltr">{$printed}</td>
            </tr>
        </table>
        HTML;
    }
}
