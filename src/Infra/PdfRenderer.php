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

        try {
            $mpdf->WriteHTML($html);
            $mpdf->Output($targetPath, Destination::FILE);
        } finally {
            // CRITICAL: Clean up temporary files created by mPDF
            $this->cleanupTempFiles($tempDir);
        }
    }

    /**
     * Clean up old temporary files created by mPDF.
     * Removes files older than 1 hour.
     */
    private function cleanupTempFiles(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        try {
            $cutoff = time() - 3600; // 1 hour ago
            $iterator = new \DirectoryIterator($tempDir);
            
            foreach ($iterator as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                
                // Delete files older than cutoff
                if ($file->getMTime() < $cutoff) {
                    @unlink($file->getPathname());
                }
            }
        } catch (\Exception $e) {
            // Log error but don't throw - cleanup is best effort
            error_log('[FPDMS] Failed to cleanup mPDF temp files: ' . $e->getMessage());
        }
    }
}
