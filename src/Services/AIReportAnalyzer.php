<?php

declare(strict_types=1);

namespace FP\DMS\Services;

use FP\DMS\Infra\Options;

/**
 * AI-powered report analyzer using OpenAI GPT.
 * Generates executive summaries, insights, and recommendations from data.
 */
class AIReportAnalyzer
{
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-5-nano'; // GPT-5 Nano - Economico e veloce
    private const MAX_TOKENS = 1500;
    
    /**
     * Generate executive summary from report data.
     *
     * @param array $context Report context with KPIs and metrics
     * @param string $industry Industry type
     * @return string Generated summary in Italian
     */
    public function generateExecutiveSummary(array $context, string $industry = 'general'): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return $this->getFallbackExecutiveSummary($context);
        }
        
        $prompt = $this->buildExecutiveSummaryPrompt($context, $industry);
        $response = $this->callOpenAI($prompt, $apiKey, 500);
        
        return $response ?: $this->getFallbackExecutiveSummary($context);
    }
    
    /**
     * Analyze trends and provide insights.
     *
     * @param array $context Report context with trends data
     * @param string $industry Industry type
     * @return string Generated trend analysis in Italian
     */
    public function analyzeTrends(array $context, string $industry = 'general'): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return $this->getFallbackTrendAnalysis($context);
        }
        
        $prompt = $this->buildTrendAnalysisPrompt($context, $industry);
        $response = $this->callOpenAI($prompt, $apiKey, 600);
        
        return $response ?: $this->getFallbackTrendAnalysis($context);
    }
    
    /**
     * Generate strategic recommendations.
     *
     * @param array $context Report context with KPIs
     * @param string $industry Industry type
     * @return string Generated recommendations in Italian
     */
    public function generateRecommendations(array $context, string $industry = 'general'): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return $this->getFallbackRecommendations($context, $industry);
        }
        
        $prompt = $this->buildRecommendationsPrompt($context, $industry);
        $response = $this->callOpenAI($prompt, $apiKey, 800);
        
        return $response ?: $this->getFallbackRecommendations($context, $industry);
    }
    
    /**
     * Analyze anomalies and provide explanations.
     *
     * @param array $anomalies List of detected anomalies
     * @param array $context Full report context
     * @param string $industry Industry type
     * @return string Generated anomaly analysis in Italian
     */
    public function explainAnomalies(array $anomalies, array $context, string $industry = 'general'): string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey) || empty($anomalies)) {
            return '';
        }
        
        $prompt = $this->buildAnomalyExplanationPrompt($anomalies, $context, $industry);
        $response = $this->callOpenAI($prompt, $apiKey, 600);
        
        return $response ?: '';
    }
    
    /**
     * Build prompt for executive summary.
     */
    private function buildExecutiveSummaryPrompt(array $context, string $industry): string
    {
        $clientName = $context['client']['name'] ?? 'il cliente';
        $period = ($context['period']['start'] ?? '') . ' - ' . ($context['period']['end'] ?? '');
        
        $kpis = $this->formatKpisForPrompt($context);
        $industryContext = $this->getIndustryContext($industry);
        
        return <<<PROMPT
Sei un esperto di marketing digitale specializzato in {$industryContext}.

Genera un executive summary professionale in italiano per il report di {$clientName} relativo al periodo {$period}.

**Dati chiave:**
{$kpis}

**Obiettivo:**
Scrivi un sommario esecutivo di 2-3 paragrafi che:
1. Riassuma le performance principali del periodo
2. Evidenzi i punti di forza e le opportunità
3. Utilizzi un tono professionale e orientato ai risultati

Rispondi SOLO con il testo del sommario, senza titoli o formattazioni markdown.
PROMPT;
    }
    
    /**
     * Build prompt for trend analysis.
     */
    private function buildTrendAnalysisPrompt(array $context, string $industry): string
    {
        $trends = $context['trends'] ?? [];
        $kpis = $this->formatKpisForPrompt($context);
        $industryContext = $this->getIndustryContext($industry);
        
        $trendData = '';
        if (isset($trends['wow'])) {
            $trendData .= "\n**Confronto settimanale:**\n" . $this->formatTrendsData($trends['wow']);
        }
        if (isset($trends['mom'])) {
            $trendData .= "\n**Confronto mensile:**\n" . $this->formatTrendsData($trends['mom']);
        }
        
        return <<<PROMPT
Sei un data analyst esperto in {$industryContext}.

Analizza i trend delle performance e fornisci insights actionable.

**Metriche attuali:**
{$kpis}

**Trend:**
{$trendData}

**Obiettivo:**
Analizza i trend e fornisci 3-4 insights chiave in italiano:
- Identifica pattern significativi
- Spiega le variazioni più rilevanti
- Fornisci un'interpretazione strategica

Usa un formato a elenco puntato. Sii specifico e orientato all'azione.
PROMPT;
    }
    
    /**
     * Build prompt for recommendations.
     */
    private function buildRecommendationsPrompt(array $context, string $industry): string
    {
        $kpis = $this->formatKpisForPrompt($context);
        $trends = $context['trends'] ?? [];
        $industryContext = $this->getIndustryContext($industry);
        
        $performanceData = $this->identifyPerformanceAreas($context);
        
        return <<<PROMPT
Sei un consulente di marketing strategico specializzato in {$industryContext}.

Genera raccomandazioni strategiche basate sui dati del cliente.

**Performance attuali:**
{$kpis}

**Analisi performance:**
{$performanceData}

**Obiettivo:**
Genera 4-6 raccomandazioni strategiche in italiano:
- Prioritizza azioni ad alto impatto
- Fornisci raccomandazioni specifiche e attuabili
- Considera ROI e risorse necessarie
- Organizza per priorità

Usa un formato a elenco numerato. Sii concreto e orientato ai risultati.
PROMPT;
    }
    
    /**
     * Build prompt for anomaly explanation.
     */
    private function buildAnomalyExplanationPrompt(array $anomalies, array $context, string $industry): string
    {
        $anomalyList = '';
        foreach ($anomalies as $anomaly) {
            $metric = $anomaly['metric'] ?? '';
            $delta = $anomaly['delta_percent'] ?? 0;
            $anomalyList .= "- {$metric}: variazione del {$delta}%\n";
        }
        
        $industryContext = $this->getIndustryContext($industry);
        
        return <<<PROMPT
Sei un data analyst esperto in {$industryContext}.

Spiega le seguenti anomalie rilevate nei dati:

{$anomalyList}

**Obiettivo:**
Per ogni anomalia, fornisci in italiano:
1. Una possibile spiegazione tecnica
2. Fattori contestuali che potrebbero averla causata
3. Se rappresenta un'opportunità o un rischio

Usa 2-3 frasi per anomalia. Sii specifico e professionale.
PROMPT;
    }
    
    /**
     * Format KPIs for prompt.
     */
    private function formatKpisForPrompt(array $context): string
    {
        $kpis = $context['kpi']['totals'] ?? [];
        $formatted = [];
        
        $labels = [
            'users' => 'Utenti',
            'sessions' => 'Sessioni',
            'pageviews' => 'Pagine viste',
            'clicks' => 'Clic',
            'impressions' => 'Impressioni',
            'cost' => 'Costo',
            'conversions' => 'Conversioni',
            'revenue' => 'Fatturato',
            'gsc_clicks' => 'Clic GSC',
            'gsc_impressions' => 'Impressioni GSC',
            'ctr' => 'CTR',
            'position' => 'Posizione media',
        ];
        
        foreach ($kpis as $key => $value) {
            if (isset($labels[$key])) {
                $formattedValue = in_array($key, ['cost', 'revenue']) 
                    ? '€' . number_format($value, 2) 
                    : number_format($value);
                $formatted[] = "- {$labels[$key]}: {$formattedValue}";
            }
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Format trends data for prompt.
     */
    private function formatTrendsData(array $trends): string
    {
        $formatted = [];
        
        foreach ($trends as $metric => $data) {
            $deltaPct = $data['delta_pct'] ?? 0;
            if ($deltaPct !== null) {
                $formatted[] = "- {$metric}: " . ($deltaPct > 0 ? '+' : '') . number_format($deltaPct, 1) . '%';
            }
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Identify performance areas (strong/weak).
     */
    private function identifyPerformanceAreas(array $context): string
    {
        $trends = $context['trends']['wow'] ?? $context['trends']['mom'] ?? [];
        
        $strong = [];
        $weak = [];
        
        foreach ($trends as $metric => $data) {
            $deltaPct = $data['delta_pct'] ?? null;
            if ($deltaPct === null) {
                continue;
            }
            
            if ($deltaPct > 10) {
                $strong[] = "{$metric} (+{$deltaPct}%)";
            } elseif ($deltaPct < -10) {
                $weak[] = "{$metric} ({$deltaPct}%)";
            }
        }
        
        $result = '';
        if (!empty($strong)) {
            $result .= "Aree forti: " . implode(', ', $strong) . "\n";
        }
        if (!empty($weak)) {
            $result .= "Aree da migliorare: " . implode(', ', $weak);
        }
        
        return $result ?: 'Performance stabile nel periodo';
    }
    
    /**
     * Get industry context for prompts.
     */
    private function getIndustryContext(string $industry): string
    {
        $contexts = [
            'hospitality' => 'hospitality e turismo',
            'hotel' => 'settore alberghiero',
            'resort' => 'resort e strutture di lusso',
            'wine' => 'settore vinicolo ed enoturismo',
            'bnb' => 'B&B e turismo rurale',
            'ecommerce' => 'e-commerce',
            'saas' => 'software as a service',
            'b2b' => 'business-to-business e lead generation',
            'local' => 'business locali',
            'healthcare' => 'settore sanitario',
        ];
        
        return $contexts[$industry] ?? 'marketing digitale';
    }
    
    /**
     * Call OpenAI API.
     */
    private function callOpenAI(string $prompt, string $apiKey, int $maxTokens = 500): ?string
    {
        $model = Options::get('fpdms_ai_model', self::DEFAULT_MODEL);
        
        $requestBody = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sei un esperto consulente di marketing digitale che scrive report professionali in italiano.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => min($maxTokens, self::MAX_TOKENS),
            'temperature' => 0.7,
        ];
        
        $response = wp_remote_post(self::API_ENDPOINT, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($requestBody),
        ]);
        
        if (is_wp_error($response)) {
            error_log('[AIReportAnalyzer] OpenAI API error: ' . $response->get_error_message());
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[AIReportAnalyzer] Invalid JSON response from OpenAI: ' . json_last_error_msg());
            error_log('[AIReportAnalyzer] Response body: ' . substr($body, 0, 500));
            return null;
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('[AIReportAnalyzer] Unexpected OpenAI response structure');
            error_log('[AIReportAnalyzer] Response: ' . substr($body, 0, 500));
            return null;
        }
        
        $content = trim($data['choices'][0]['message']['content']);
        
        // Log usage for monitoring
        if (isset($data['usage'])) {
            error_log(sprintf(
                '[AIReportAnalyzer] OpenAI usage: %d tokens (prompt: %d, completion: %d)',
                $data['usage']['total_tokens'] ?? 0,
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0
            ));
        }
        
        return $content;
    }
    
    /**
     * Get OpenAI API key from settings.
     */
    private function getApiKey(): string
    {
        return Options::get('fpdms_openai_api_key', '');
    }
    
    /**
     * Fallback executive summary (quando AI non è disponibile).
     */
    private function getFallbackExecutiveSummary(array $context): string
    {
        $clientName = $context['client']['name'] ?? 'il cliente';
        $period = ($context['period']['start'] ?? '') . ' - ' . ($context['period']['end'] ?? '');
        
        return "Report delle performance digitali di <strong>{$clientName}</strong> per il periodo {$period}. "
            . "Questo documento analizza le metriche chiave e fornisce insights strategici per ottimizzare le attività di marketing digitale.";
    }
    
    /**
     * Fallback trend analysis.
     */
    private function getFallbackTrendAnalysis(array $context): string
    {
        $trends = $context['trends']['wow'] ?? $context['trends']['mom'] ?? [];
        
        if (empty($trends)) {
            return '';
        }
        
        $insights = [];
        foreach ($trends as $metric => $data) {
            $deltaPct = $data['delta_pct'] ?? null;
            if ($deltaPct === null) {
                continue;
            }
            
            if (abs($deltaPct) > 10) {
                $direction = $deltaPct > 0 ? 'aumento' : 'diminuzione';
                $insights[] = "• {$metric}: {$direction} del " . number_format(abs($deltaPct), 1) . '%';
            }
        }
        
        return !empty($insights) 
            ? "**Variazioni significative:**\n" . implode("\n", $insights)
            : "Performance stabile rispetto al periodo precedente.";
    }
    
    /**
     * Fallback recommendations.
     */
    private function getFallbackRecommendations(array $context, string $industry): string
    {
        $recommendations = [
            'Continuare a monitorare le metriche chiave per identificare trend emergenti',
            'Ottimizzare le campagne con performance inferiori alle aspettative',
            'Scalare le iniziative che hanno dimostrato ROI positivo',
            'Implementare test A/B per migliorare i tassi di conversione',
        ];
        
        return implode("\n", array_map(function($rec, $index) {
            return ($index + 1) . '. ' . $rec;
        }, $recommendations, array_keys($recommendations)));
    }
}

