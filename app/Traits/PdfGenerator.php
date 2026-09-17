<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait  PdfGenerator
{
    public static function generatePdf($view, $filePrefix, $filePostfix, $pdfType = null, $requestFrom = 'admin'): string
    {
        $mpdf = new \Mpdf\Mpdf(['default_font' => 'dejavusans', 'mode' => 'utf-8', 'format' => [190, 250], 'autoLangToFont' => true]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality('rtl');
        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        self::writeInvoiceHtml($mpdf, $mpdf_view);
        $mpdf->Output($filePrefix . $filePostfix . '.pdf', 'D');
    }

    /**
     * Write invoice HTML into an existing mPDF instance.
     * Strips a full HTML document wrapper, remote font imports, and bidi control
     * characters that make mPDF throw on PHP 8 (undefined OTL "type").
     */
    public static function writeInvoiceHtml(\Mpdf\Mpdf $mpdf, string $html, bool $includeCss = true): void
    {
        $css = '';
        if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $html, $styleMatch)) {
            $css = preg_replace('/@import\s+url\([^)]+\);/i', '', $styleMatch[1]) ?? $styleMatch[1];
        }
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }
        $html = preg_replace('/@import\s+url\([^)]+\);/i', '', $html) ?? $html;
        $html = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}-\x{200F}\x{FEFF}]/u', '', $html) ?? $html;
        if ($includeCss && $css !== '') {
            $mpdf->WriteHTML('<style>' . $css . '</style>', 1);
        }
        $mpdf->WriteHTML($html, 2);
    }

    public static function storePdf($view, $filePrefix, $filePostfix, $pdfType = null, $requestFrom = 'admin'): string
    {
        $mpdf = new \Mpdf\Mpdf(['default_font' => 'dejavusans', 'mode' => 'utf-8', 'format' => [190, 250], 'autoLangToFont' => true]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality('rtl');
        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        self::writeInvoiceHtml($mpdf, $mpdf_view);

        $fileName = $filePrefix . $filePostfix . '.pdf';
        $directory = 'invoices';
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }
        $filePath = Storage::disk('public')->path($directory . '/' . $fileName);
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        return $filePath;
    }

    public static function footerHtml(string $requestFrom): string
    {
        $getCompanyPhone = getWebConfig(name: 'company_phone');
        $getCompanyEmail = getWebConfig(name: 'company_email');
        if ($requestFrom == 'web' && theme_root_path() == 'theme_aster' || theme_root_path() == 'theme_fashion') {
            return '<div style="width:560px;margin: 0 auto;background-color: #1455AC">
                <table class="fs-10">
                    <tr>
                        <td style="padding: 10px">
                            <span style="color:#ffffff;">' . url('/') . '</span>
                        </td>
                        <td style="padding: 10px">
                            <span style="color:#ffffff;">' . $getCompanyPhone . '</span>
                        </td>
                        <td style="padding: 10px">
                            <span style="color:#ffffff;">' . $getCompanyEmail . '</span>
                        </td>
                    </tr>
                </table>
            </div>';
        } else {
            return '<div style="width:520px;margin: 0 auto;background-color: #F2F4F7;padding: 11px 19px 10px 32px;">
            <table class="fs-10">
                <tr>
                    <td>
                        <span>' . url('/') . '</span>
                    </td>
                    <td>
                        <span>' . $getCompanyPhone . '</span>
                    </td>
                    <td>
                        <span>' . $getCompanyEmail . '</span>
                    </td>
                </tr>
            </table>
        </div>';
        }

    }
}
