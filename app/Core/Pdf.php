<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Génération PDF via Dompdf (composer require dompdf/dompdf).
 * Si Dompdf n'est pas installé, on bascule sur une version HTML
 * imprimable qui ouvre la boîte de dialogue d'impression du navigateur
 * (l'utilisateur peut alors choisir « Enregistrer en PDF »).
 */
final class Pdf
{
    public static function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public static function stream(string $html, string $filename, string $orientation = 'portrait', bool $inline = false): never
    {
        if (self::available()) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('tempDir', sys_get_temp_dir());
            $options->set('chroot', BASE_PATH);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            // Pied de page : numérotation
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $w = $canvas->get_width();
            $h = $canvas->get_height();
            $canvas->page_text($w - 110, $h - 28, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);
            $canvas->page_text(36, $h - 28, 'Édité le ' . date('d/m/Y H:i'), $font, 8, [0.4, 0.4, 0.4]);

            $dompdf->stream($filename, ['Attachment' => !$inline]);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo str_replace('</body>', '<script>window.addEventListener("load",()=>window.print());</script></body>', $html);
        exit;
    }
}
