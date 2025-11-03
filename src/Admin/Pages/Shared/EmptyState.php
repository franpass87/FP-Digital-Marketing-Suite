<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;
use function esc_url;

/**
 * Empty State Component
 * 
 * Componente riutilizzabile per stati vuoti nelle pagine admin
 */
class EmptyState
{
    /**
     * Render an empty state component
     *
     * @param array{
     *     icon?: string,
     *     title: string,
     *     description: string,
     *     primaryAction?: array{label: string, url: string, class?: string},
     *     secondaryAction?: array{label: string, url: string},
     *     helpText?: string
     * } $config
     */
    public static function render(array $config): void
    {
        $icon = $config['icon'] ?? 'dashicons-info';
        $title = $config['title'];
        $description = $config['description'];
        $primaryAction = $config['primaryAction'] ?? null;
        $secondaryAction = $config['secondaryAction'] ?? null;
        $helpText = $config['helpText'] ?? '';

        echo '<div class="fpdms-empty-state">';
        
        // Icon
        echo '<div class="fpdms-empty-state-icon">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '</div>';
        
        // Title
        echo '<h3 class="fpdms-empty-state-title">' . esc_html($title) . '</h3>';
        
        // Description
        echo '<p class="fpdms-empty-state-description">' . esc_html($description) . '</p>';
        
        // Actions
        if ($primaryAction || $secondaryAction) {
            echo '<div class="fpdms-empty-state-actions">';
            
            if ($primaryAction) {
                $buttonClass = $primaryAction['class'] ?? 'button-primary';
                echo '<a href="' . esc_url($primaryAction['url']) . '" class="button ' . esc_attr($buttonClass) . '">';
                echo esc_html($primaryAction['label']);
                echo '</a>';
            }
            
            if ($secondaryAction) {
                echo '<a href="' . esc_url($secondaryAction['url']) . '" class="button button-secondary">';
                echo esc_html($secondaryAction['label']);
                echo '</a>';
            }
            
            echo '</div>';
        }
        
        // Help text
        if ($helpText) {
            echo '<p class="fpdms-empty-state-help">';
            echo '<span class="dashicons dashicons-editor-help"></span>';
            echo esc_html($helpText);
            echo '</p>';
        }
        
        echo '</div>';
        
        // Inline CSS
        self::renderStyles();
    }
    
    /**
     * Render inline styles (solo la prima volta)
     */
    private static function renderStyles(): void
    {
        static $stylesRendered = false;
        
        if ($stylesRendered) {
            return;
        }
        
        $stylesRendered = true;
        
        echo '<style>
        .fpdms-empty-state {
            text-align: center;
            padding: 80px 40px;
            max-width: 600px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border-radius: 16px;
            border: 2px dashed #e5e7eb;
        }
        
        .fpdms-empty-state-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            animation: fpdms-float 3s ease-in-out infinite;
        }
        
        @keyframes fpdms-float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .fpdms-empty-state-icon .dashicons {
            font-size: 64px;
            width: 64px;
            height: 64px;
            color: white;
        }
        
        .fpdms-empty-state-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 12px 0;
        }
        
        .fpdms-empty-state-description {
            font-size: 16px;
            line-height: 1.6;
            color: #6b7280;
            margin: 0 0 32px 0;
        }
        
        .fpdms-empty-state-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .fpdms-empty-state-actions .button {
            font-size: 16px;
            padding: 12px 32px;
            height: auto;
            line-height: 1.5;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .fpdms-empty-state-actions .button-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .fpdms-empty-state-actions .button-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a4190 100%);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            transform: translateY(-2px);
        }
        
        .fpdms-empty-state-actions .button-secondary {
            background: white;
            border: 2px solid #e5e7eb;
            color: #6b7280;
        }
        
        .fpdms-empty-state-actions .button-secondary:hover {
            border-color: #667eea;
            color: #667eea;
            background: #f9fafb;
        }
        
        .fpdms-empty-state-help {
            margin-top: 24px;
            font-size: 14px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .fpdms-empty-state-help .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-empty-state {
                padding: 60px 24px;
            }
            
            .fpdms-empty-state-icon {
                width: 100px;
                height: 100px;
            }
            
            .fpdms-empty-state-icon .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
            }
            
            .fpdms-empty-state-title {
                font-size: 20px;
            }
            
            .fpdms-empty-state-description {
                font-size: 14px;
            }
            
            .fpdms-empty-state-actions {
                flex-direction: column;
            }
            
            .fpdms-empty-state-actions .button {
                width: 100%;
            }
        }
        </style>';
    }
}

