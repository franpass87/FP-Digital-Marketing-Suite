<?php

namespace FP\DigitalMarketing;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Main report generator class
 */
class ReportGenerator
{
    private $templatePath;
    private $outputPath;

    public function __construct($templatePath = null, $outputPath = null)
    {
        $this->templatePath = $templatePath ?: __DIR__ . '/templates/report_template.html';
        $this->outputPath = $outputPath ?: __DIR__ . '/../output';
    }

    /**
     * Generate HTML report
     */
    public function generateHtmlReport($data = null)
    {
        if ($data === null) {
            $data = MockDataProvider::getAnalyticsData();
        }

        $template = file_get_contents($this->templatePath);
        
        // Replace template variables
        $html = $this->renderTemplate($template, $data);
        
        return $html;
    }

    /**
     * Generate PDF report
     */
    public function generatePdfReport($data = null, $filename = null)
    {
        $html = $this->generateHtmlReport($data);
        
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        if ($filename === null) {
            $filename = 'marketing_report_' . date('Y-m-d_H-i-s') . '.pdf';
        }
        
        $filePath = $this->outputPath . '/' . $filename;
        file_put_contents($filePath, $dompdf->output());
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => filesize($filePath)
        ];
    }

    /**
     * Render template with data
     */
    private function renderTemplate($template, $data)
    {
        // Replace basic variables
        $replacements = [
            '{{report_date}}' => date('d/m/Y H:i'),
            '{{period_display}}' => $data['period']['display'],
            '{{period_start}}' => date('d/m/Y', strtotime($data['period']['start'])),
            '{{period_end}}' => date('d/m/Y', strtotime($data['period']['end'])),
        ];

        // Replace KPI values
        foreach ($data['kpis'] as $key => $kpi) {
            $replacements['{{' . $key . '_value}}'] = $kpi['value'];
            $replacements['{{' . $key . '_change}}'] = $this->formatChange($kpi['change']);
            $replacements['{{' . $key . '_change_class}}'] = $kpi['change'] >= 0 ? 'positive' : 'negative';
        }

        // Replace chart data
        $replacements['{{traffic_chart_data}}'] = $this->generateTrafficChartHtml($data['charts']['traffic_trend']);
        $replacements['{{funnel_chart_data}}'] = $this->generateFunnelChartHtml($data['charts']['conversion_funnel']);
        $replacements['{{revenue_source_data}}'] = $this->generateRevenueSourceHtml($data['charts']['revenue_by_source']);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function formatChange($change)
    {
        $sign = $change >= 0 ? '+' : '';
        return $sign . number_format($change, 1) . '%';
    }

    private function generateTrafficChartHtml($data)
    {
        $html = '<div class="chart-container">';
        $maxValue = max(array_column($data, 'sessions'));
        
        foreach (array_slice($data, -7) as $day) {
            $height = round(($day['sessions'] / $maxValue) * 100);
            $html .= sprintf(
                '<div class="chart-bar" style="height: %s%%;" title="%s: %s sessions"></div>',
                $height,
                date('d/m', strtotime($day['date'])),
                number_format($day['sessions'])
            );
        }
        
        $html .= '</div>';
        return $html;
    }

    private function generateFunnelChartHtml($data)
    {
        $html = '<div class="funnel-chart">';
        
        foreach ($data as $step) {
            $html .= sprintf(
                '<div class="funnel-step" style="width: %s%%;">
                    <span class="step-name">%s</span>
                    <span class="step-value">%s (%s%%)</span>
                </div>',
                $step['percentage'],
                $step['step'],
                number_format($step['count']),
                number_format($step['percentage'], 1)
            );
        }
        
        $html .= '</div>';
        return $html;
    }

    private function generateRevenueSourceHtml($data)
    {
        $html = '<div class="revenue-sources">';
        
        foreach ($data as $source) {
            $html .= sprintf(
                '<div class="revenue-source">
                    <div class="source-info">
                        <span class="source-name">%s</span>
                        <span class="source-revenue">€%s</span>
                    </div>
                    <div class="source-bar" style="width: %s%%;"></div>
                </div>',
                $source['source'],
                number_format($source['revenue']),
                $source['percentage']
            );
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Get list of generated reports
     */
    public function getGeneratedReports()
    {
        $files = glob($this->outputPath . '/*.pdf');
        $reports = [];
        
        foreach ($files as $file) {
            $reports[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'date' => date('d/m/Y H:i', filemtime($file))
            ];
        }
        
        // Sort by modification time, newest first
        usort($reports, function($a, $b) {
            return filemtime($b['path']) - filemtime($a['path']);
        });
        
        return $reports;
    }
}