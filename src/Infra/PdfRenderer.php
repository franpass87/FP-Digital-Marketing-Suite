<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

class PdfRenderer
{
    public function render(string $html, string $targetPath): void
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException(__('mPDF library is not available. Run composer install on the server.', 'fp-dms'));
        }

        $uploadDir = wp_upload_dir();
        $tempDir = trailingslashit($uploadDir['basedir']) . 'fpdms-temp';
        wp_mkdir_p($tempDir);
        wp_mkdir_p(dirname($targetPath));

        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
            'format' => 'A4',
        ]);

        $mpdf->WriteHTML($html);
        $mpdf->Output($targetPath, Destination::FILE);
    }
}
