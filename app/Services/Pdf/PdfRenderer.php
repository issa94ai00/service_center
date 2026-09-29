<?php

namespace App\Services\Pdf;

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
     * Render a Blade view to raw PDF bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = [], ?string $footerTitle = null): string
    {
        $html = view($view, $data)->render();

        try {
            $pdf = $this->engine();

            if ($footerTitle !== null) {
                $pdf->SetHTMLFooter($this->footer($footerTitle));
            }

            $pdf->WriteHTML($html);

            return $pdf->Output('', 'S');
        } catch (MpdfException $e) {
            report($e);

            throw new BusinessRuleException('تعذّر إنشاء ملف PDF.');
        }
    }

    protected function engine(): Mpdf
    {
        $temp = storage_path('app/mpdf');

        if (! is_dir($temp)) {
            mkdir($temp, 0775, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'default_font' => self::ARABIC_FONT,
            'default_font_size' => 9,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_footer' => 6,
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
