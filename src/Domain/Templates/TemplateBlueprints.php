<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Templates;

use function esc_html__;

final class TemplateBlueprints
{
    /** @var array<string,TemplateBlueprint>|null */
    private static ?array $cache = null;

    /**
     * @return array<string,TemplateBlueprint>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $balanced = TemplateBuilder::make()
            ->addSection(
                esc_html__('Executive summary', 'fp-dms'),
                '<p>' . esc_html__('Use this space to summarise wins, challenges, and context for the reporting period.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.kpi|raw}}')
            ->addSection(
                esc_html__('Key takeaways', 'fp-dms'),
                '<ul><li>' . esc_html__('Call out the marketing activities that moved the numbers.', 'fp-dms') . '</li><li>' . esc_html__('Highlight optimisation opportunities for the next cycle.', 'fp-dms') . '</li></ul>'
            )
            ->addRawSection('{{sections.trends|raw}}')
            ->addRawSection('{{sections.gsc|raw}}')
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Next steps', 'fp-dms'),
                '<p>' . esc_html__('Outline the actions you will take to sustain growth or fix bottlenecks.', 'fp-dms') . '</p>'
            )
            ->build();

        $kpiFocused = TemplateBuilder::make()
            ->addSection(
                esc_html__('Performance snapshot', 'fp-dms'),
                '<p>' . esc_html__('Summarise how the account performed during this period in one paragraph.', 'fp-dms') . '</p>'
            )
            ->addKpiSection(esc_html__('KPI overview', 'fp-dms'), [
                ['label' => esc_html__('Users', 'fp-dms'), 'value' => '{{kpi.ga4.users|number}}'],
                ['label' => esc_html__('Sessions', 'fp-dms'), 'value' => '{{kpi.ga4.sessions|number}}'],
                ['label' => esc_html__('Clicks', 'fp-dms'), 'value' => '{{kpi.google_ads.clicks|number}}'],
                ['label' => esc_html__('Conversions', 'fp-dms'), 'value' => '{{kpi.google_ads.conversions|number}}'],
                ['label' => esc_html__('Cost', 'fp-dms'), 'value' => '{{kpi.google_ads.cost|number}}'],
            ])
            ->addSection(
                esc_html__('Insights and commentary', 'fp-dms'),
                '<p>' . esc_html__('Explain what caused major swings and how they align with your objectives.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->addSection(
                esc_html__('Action plan', 'fp-dms'),
                '<ul><li>' . esc_html__('List the optimisations you will tackle next.', 'fp-dms') . '</li></ul>'
            )
            ->build();

        $searchBlueprint = TemplateBuilder::make()
            ->addSection(
                esc_html__('Organic performance overview', 'fp-dms'),
                '<p>' . esc_html__('Frame the organic search visibility achieved in the selected window.', 'fp-dms') . '</p>'
            )
            ->addRawSection('{{sections.gsc|raw}}')
            ->addSection(
                esc_html__('Opportunities', 'fp-dms'),
                '<ul><li>' . esc_html__('Identify keyword clusters or pages that deserve extra attention.', 'fp-dms') . '</li><li>' . esc_html__('Describe experiments or content you will launch.', 'fp-dms') . '</li></ul>'
            )
            ->addRawSection('{{sections.anomalies|raw}}')
            ->build();

        self::$cache = [
            'balanced' => new TemplateBlueprint(
                'balanced',
                esc_html__('Balanced performance report', 'fp-dms'),
                esc_html__('Combines KPI, trend, and search insights with space for commentary.', 'fp-dms'),
                $balanced
            ),
            'kpi' => new TemplateBlueprint(
                'kpi',
                esc_html__('KPI-focused summary', 'fp-dms'),
                esc_html__('Puts the spotlight on headline metrics and qualitative insights.', 'fp-dms'),
                $kpiFocused
            ),
            'search' => new TemplateBlueprint(
                'search',
                esc_html__('Search visibility recap', 'fp-dms'),
                esc_html__('Centres the report on Google Search Console findings and next steps.', 'fp-dms'),
                $searchBlueprint
            ),
        ];

        return self::$cache;
    }

    public static function find(string $key): ?TemplateBlueprint
    {
        $all = self::all();

        return $all[$key] ?? null;
    }

    public static function defaultBlueprint(): TemplateBlueprint
    {
        return self::all()['balanced'];
    }

    public static function defaultDraft(): TemplateDraft
    {
        $blueprint = self::defaultBlueprint();

        return TemplateDraft::fromValues(
            esc_html__('Balanced performance report', 'fp-dms'),
            esc_html__('Automatically generated default layout.', 'fp-dms'),
            $blueprint->content,
            true
        );
    }
}
