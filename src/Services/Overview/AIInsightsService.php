<?php

declare(strict_types=1);

namespace FP\DMS\Services\Overview;

use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Services\AIReportAnalyzer;

/**
 * AI Insights Service for Overview page
 * 
 * Generates intelligent insights from overview data using AI
 */
class AIInsightsService
{
    private Assembler $assembler;
    private AIReportAnalyzer $aiAnalyzer;
    private AnomaliesRepo $anomaliesRepo;
    private ClientsRepo $clientsRepo;

    public function __construct(
        ?Assembler $assembler = null,
        ?AIReportAnalyzer $aiAnalyzer = null,
        ?AnomaliesRepo $anomaliesRepo = null,
        ?ClientsRepo $clientsRepo = null
    ) {
        $this->assembler = $assembler ?? new Assembler();
        $this->aiAnalyzer = $aiAnalyzer ?? new AIReportAnalyzer();
        $this->anomaliesRepo = $anomaliesRepo ?? new AnomaliesRepo();
        $this->clientsRepo = $clientsRepo ?? new ClientsRepo();
    }

    /**
     * Cache duration for AI insights (24 hours)
     */
    private const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Generate AI insights for overview data
     *
     * @param int $clientId
     * @param array<string, string> $period
     * @return array{
     *     performance: string,
     *     trends: string,
     *     recommendations: string,
     *     has_api_key: bool
     * }
     */
    public function generateInsights(int $clientId, array $period): array
    {
        // Check if AI is available
        $hasApiKey = $this->hasOpenAIKey();
        
        if (!$hasApiKey) {
            return [
                'performance' => '',
                'trends' => '',
                'recommendations' => '',
                'has_api_key' => false,
            ];
        }

        // Try to get from cache first (24h cache)
        $cacheKey = $this->getCacheKey($clientId, $period);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        // Get client info
        $client = $this->clientsRepo->find($clientId);
        $clientName = $client ? $client->name : 'Cliente';
        $industry = $client && !empty($client->metadata['industry']) 
            ? (string) $client->metadata['industry'] 
            : 'general';

        // Get overview data
        $summary = $this->assembler->summary($clientId, $period);
        
        // Build context for AI
        $context = $this->buildContext($clientId, $clientName, $summary, $period);
        
        // Get anomalies for context
        $anomalies = $this->getRecentAnomalies($clientId);
        
        // Generate AI insights
        $performance = $this->aiAnalyzer->generateExecutiveSummary($context, $industry);
        $trends = $this->aiAnalyzer->analyzeTrends($context, $industry);
        $recommendations = $this->aiAnalyzer->generateRecommendations($context, $industry);
        
        // If we have anomalies, add explanation
        if (!empty($anomalies)) {
            $anomalyExplanation = $this->aiAnalyzer->explainAnomalies($anomalies, $context, $industry);
            if (!empty($anomalyExplanation)) {
                $trends .= "\n\n**Anomalie rilevate:**\n" . $anomalyExplanation;
            }
        }

        $result = [
            'performance' => $this->formatForHtml($performance),
            'trends' => $this->formatForHtml($trends),
            'recommendations' => $this->formatForHtml($recommendations),
            'has_api_key' => true,
        ];

        // Cache the result for 24 hours
        set_transient($cacheKey, $result, self::CACHE_DURATION);

        return $result;
    }

    /**
     * Generate cache key for AI insights
     *
     * @param int $clientId
     * @param array<string, string> $period
     * @return string
     */
    private function getCacheKey(int $clientId, array $period): string
    {
        $periodKey = md5(json_encode($period));
        return "fpdms_ai_insights_{$clientId}_{$periodKey}";
    }

    /**
     * Clear AI insights cache for a specific client
     *
     * @param int $clientId
     * @return void
     */
    public function clearCache(int $clientId): void
    {
        global $wpdb;
        
        // Delete all transients for this client
        $pattern = $wpdb->esc_like("_transient_fpdms_ai_insights_{$clientId}_") . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
    }

    /**
     * Build context array for AI analysis
     *
     * @param int $clientId
     * @param string $clientName
     * @param array<string, mixed> $summary
     * @param array<string, string> $period
     * @return array<string, mixed>
     */
    private function buildContext(int $clientId, string $clientName, array $summary, array $period): array
    {
        // Extract KPI totals
        $totals = [];
        foreach ($summary['kpis'] as $kpi) {
            $totals[$kpi['metric']] = $kpi['value'];
        }

        // Build trends data
        $trends = [
            'wow' => $this->buildTrendsFromKpis($summary['kpis']),
        ];

        return [
            'client' => [
                'id' => $clientId,
                'name' => $clientName,
            ],
            'period' => [
                'start' => $summary['period']['from'],
                'end' => $summary['period']['to'],
            ],
            'kpi' => [
                'totals' => $totals,
            ],
            'trends' => $trends,
        ];
    }

    /**
     * Build trends data from KPIs
     *
     * @param array<int, array<string, mixed>> $kpis
     * @return array<string, array<string, mixed>>
     */
    private function buildTrendsFromKpis(array $kpis): array
    {
        $trends = [];
        
        foreach ($kpis as $kpi) {
            $metric = $kpi['metric'];
            $delta = $kpi['delta'];
            
            $trends[$metric] = [
                'current' => $kpi['value'],
                'previous' => $kpi['previous_value'],
                'delta_pct' => $delta['percent'],
                'direction' => $delta['direction'],
            ];
        }
        
        return $trends;
    }

    /**
     * Get recent anomalies for the client
     *
     * @param int $clientId
     * @return array<int, array<string, mixed>>
     */
    private function getRecentAnomalies(int $clientId): array
    {
        $allAnomalies = $this->anomaliesRepo->findByClient($clientId);
        
        // Filter only unresolved anomalies from last 30 days
        $recentAnomalies = [];
        $cutoffDate = new \DateTimeImmutable('-30 days');
        
        foreach ($allAnomalies as $anomaly) {
            if ($anomaly->resolved) {
                continue;
            }
            
            $detectedAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anomaly->detected_at);
            if (!$detectedAt || $detectedAt < $cutoffDate) {
                continue;
            }
            
            $recentAnomalies[] = [
                'metric' => $anomaly->metric,
                'delta_percent' => round($anomaly->delta_pct, 1),
                'severity' => $anomaly->severity,
                'score' => $anomaly->score,
            ];
        }
        
        // Limit to top 5 by score
        usort($recentAnomalies, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($recentAnomalies, 0, 5);
    }

    /**
     * Format text for HTML display
     * Converts markdown-like syntax to HTML
     *
     * @param string $text
     * @return string
     */
    private function formatForHtml(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Convert **bold** to <strong>
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        
        // Convert bullet points to <ul>
        $lines = explode("\n", $text);
        $inList = false;
        $inNumberedList = false;
        $result = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                if ($inList) {
                    $result[] = '</ul>';
                    $inList = false;
                }
                if ($inNumberedList) {
                    $result[] = '</ol>';
                    $inNumberedList = false;
                }
                $result[] = '';
                continue;
            }
            
            // Bullet list
            if (preg_match('/^[•\-\*]\s+(.+)$/', $line, $matches)) {
                if (!$inList) {
                    $result[] = '<ul>';
                    $inList = true;
                }
                $result[] = '<li>' . $matches[1] . '</li>';
            }
            // Numbered list
            elseif (preg_match('/^(\d+)\.\s+(.+)$/', $line, $matches)) {
                if (!$inNumberedList) {
                    $result[] = '<ol>';
                    $inNumberedList = true;
                }
                $result[] = '<li>' . $matches[2] . '</li>';
            }
            // Regular paragraph
            else {
                if ($inList) {
                    $result[] = '</ul>';
                    $inList = false;
                }
                if ($inNumberedList) {
                    $result[] = '</ol>';
                    $inNumberedList = false;
                }
                $result[] = '<p>' . $line . '</p>';
            }
        }
        
        // Close any open lists
        if ($inList) {
            $result[] = '</ul>';
        }
        if ($inNumberedList) {
            $result[] = '</ol>';
        }
        
        return implode("\n", $result);
    }

    /**
     * Check if OpenAI API key is configured
     *
     * @return bool
     */
    private function hasOpenAIKey(): bool
    {
        $apiKey = \FP\DMS\Infra\Options::get('fpdms_openai_api_key', '');
        return !empty($apiKey);
    }
}

