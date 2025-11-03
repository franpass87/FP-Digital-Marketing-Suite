<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Breadcrumbs Component
 * 
 * Mostra la navigazione gerarchica per orientare l'utente
 */
class Breadcrumbs
{
    /**
     * Render breadcrumbs
     *
     * @param array<int, array{label: string, url?: string, icon?: string}> $items
     */
    public static function render(array $items): void
    {
        if (empty($items)) {
            return;
        }

        echo '<nav class="fpdms-breadcrumbs" aria-label="Breadcrumb">';
        echo '<ol class="fpdms-breadcrumbs-list">';
        
        $totalItems = count($items);
        foreach ($items as $index => $item) {
            $isLast = ($index === $totalItems - 1);
            $icon = $item['icon'] ?? '';
            $label = $item['label'];
            $url = $item['url'] ?? '';
            
            echo '<li class="fpdms-breadcrumb-item' . ($isLast ? ' is-active' : '') . '">';
            
            if ($icon) {
                echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
            }
            
            if ($url && !$isLast) {
                echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
            } else {
                echo '<span>' . esc_html($label) . '</span>';
            }
            
            if (!$isLast) {
                echo '<span class="fpdms-breadcrumb-separator">/</span>';
            }
            
            echo '</li>';
        }
        
        echo '</ol>';
        echo '</nav>';
        
        // Inline CSS (only first time)
        self::renderStyles();
    }
    
    /**
     * Render inline styles
     */
    private static function renderStyles(): void
    {
        static $stylesRendered = false;
        
        if ($stylesRendered) {
            return;
        }
        
        $stylesRendered = true;
        
        echo '<style>
        .fpdms-breadcrumbs {
            margin-bottom: 20px;
            padding: 12px 0;
        }
        
        .fpdms-breadcrumbs-list {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .fpdms-breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .fpdms-breadcrumb-item .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #9ca3af;
        }
        
        .fpdms-breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .fpdms-breadcrumb-item a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .fpdms-breadcrumb-item.is-active {
            color: #1f2937;
            font-weight: 600;
        }
        
        .fpdms-breadcrumb-separator {
            color: #d1d5db;
            font-weight: 300;
            margin: 0 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-breadcrumbs-list {
                font-size: 13px;
            }
            
            .fpdms-breadcrumb-item .dashicons {
                display: none;
            }
        }
        </style>';
    }
    
    /**
     * Helper: Get standard page items for common pages
     *
     * @param string $currentPage
     * @param array<string, mixed> $extra
     * @return array<int, array{label: string, url?: string, icon?: string}>
     */
    public static function getStandardItems(string $currentPage, array $extra = []): array
    {
        $base = [
            [
                'label' => __('FP Suite', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-dashboard'),
                'icon' => 'dashicons-chart-area'
            ]
        ];
        
        $pages = [
            'dashboard' => [
                'label' => __('Dashboard', 'fp-dms'),
            ],
            'overview' => [
                'label' => __('Overview', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-overview')
            ],
            'clients' => [
                'label' => __('Clienti', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-clients')
            ],
            'datasources' => [
                'label' => __('Connessioni', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-datasources')
            ],
            'schedules' => [
                'label' => __('Automazione', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-schedules')
            ],
            'reports' => [
                'label' => __('Report', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-reports')
            ],
            'templates' => [
                'label' => __('Template', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-templates')
            ],
            'settings' => [
                'label' => __('Impostazioni', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-settings')
            ],
            'anomalies' => [
                'label' => __('Anomalie', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-anomalies')
            ],
            'health' => [
                'label' => __('System Health', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-health')
            ],
            'logs' => [
                'label' => __('Logs', 'fp-dms'),
                'url' => admin_url('admin.php?page=fp-dms-logs')
            ],
        ];
        
        if (isset($pages[$currentPage])) {
            $base[] = $pages[$currentPage];
        }
        
        // Add extra items (for sub-pages like "Edit Client")
        foreach ($extra as $item) {
            $base[] = $item;
        }
        
        return $base;
    }
}

