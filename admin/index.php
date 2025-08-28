<?php
require_once __DIR__ . '/../vendor/autoload.php';

use FP\DigitalMarketing\ReportGenerator;
use FP\DigitalMarketing\MockDataProvider;

$reportGenerator = new ReportGenerator();
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['generate_pdf'])) {
            $result = $reportGenerator->generatePdfReport();
            $message = "PDF report generated successfully: {$result['filename']} ({$result['size']} bytes)";
        } elseif (isset($_POST['preview_html'])) {
            $htmlReport = $reportGenerator->generateHtmlReport();
            // We'll handle the preview below
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle PDF download
if (isset($_GET['download']) && $_GET['download']) {
    $filename = basename($_GET['download']);
    $filePath = __DIR__ . '/../output/' . $filename;
    
    if (file_exists($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Get list of generated reports
$reports = $reportGenerator->getGeneratedReports();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FP Digital Marketing Suite - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
        }
        .report-preview {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .report-list {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-chart-line me-3"></i>FP Digital Marketing Suite</h1>
                    <p class="lead mb-0">Sistema di Reporting Automatico</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>Azioni Report</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <button type="submit" name="preview_html" class="btn btn-outline-primary btn-lg w-100 mb-3">
                                <i class="fas fa-eye me-2"></i>Anteprima HTML
                            </button>
                            <button type="submit" name="generate_pdf" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-file-pdf me-2"></i>Genera PDF
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="d-grid">
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-clock me-2"></i>Configura Scheduler
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-archive me-2"></i>Report Generati</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reports)): ?>
                            <p class="text-muted">Nessun report generato ancora.</p>
                        <?php else: ?>
                            <div class="report-list">
                                <?php foreach ($reports as $report): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <small class="text-muted"><?= $report['date'] ?></small><br>
                                            <span class="fw-bold"><?= htmlspecialchars($report['filename']) ?></span><br>
                                            <small class="text-muted"><?= number_format($report['size']) ?> bytes</small>
                                        </div>
                                        <a href="?download=<?= urlencode($report['filename']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Anteprima Report</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($htmlReport)): ?>
                            <div class="report-preview">
                                <?= $htmlReport ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <p>Clicca su "Anteprima HTML" per visualizzare il report</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock me-2"></i>Configurazione Scheduler</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Configurazione Cron</h6>
                        <p class="mb-2">Per abilitare la generazione automatica, aggiungi questa riga al tuo crontab:</p>
                        <code>0 9 1 * * <?= PHP_BINARY ?> <?= __DIR__ ?>/../cron/generate_report.php</code>
                        <small class="d-block mt-2 text-muted">Questo genererà un report ogni primo giorno del mese alle 9:00</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Configurazioni Alternative</h6>
                        <ul class="mb-0">
                            <li><code>0 9 * * 1</code> - Ogni lunedì alle 9:00 (settimanale)</li>
                            <li><code>0 9 * * *</code> - Ogni giorno alle 9:00 (giornaliero)</li>
                            <li><code>0 */6 * * *</code> - Ogni 6 ore</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>