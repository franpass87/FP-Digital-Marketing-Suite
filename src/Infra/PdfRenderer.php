<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Support\Wp;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use function __;

class PdfRenderer
{
    public function render(string $html, string $targetPath): void
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException(__('mPDF library is not available. Run composer install on the server.', 'fp-dms'));
        }

        $uploadDir = Wp::uploadDir();
        if (! empty($uploadDir['error']) || empty($uploadDir['basedir'])) {
            throw new RuntimeException(__('Uploads directory is not available. Check WordPress configuration.', 'fp-dms'));
        }
        $tempDir = Wp::trailingSlashIt($uploadDir['basedir']) . 'fpdms-temp';

        try {
            Wp::mkdirP($tempDir);
            Wp::mkdirP((string) dirname($targetPath));
        } catch (RuntimeException $exception) {
            throw new RuntimeException(sprintf(__('Unable to prepare report directories: %s', 'fp-dms'), $exception->getMessage()), 0, $exception);
        }

        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
            'format' => 'A4',
        ]);

        $mpdf->WriteHTML($html);
        $mpdf->Output($targetPath, Destination::FILE);
    }
}
