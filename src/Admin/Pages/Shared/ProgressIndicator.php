<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;

/**
 * Progress Indicator Component
 * 
 * Mostra progress bar, spinner e stato per operazioni lunghe
 */
class ProgressIndicator
{
    /**
     * Render progress bar
     *
     * @param array{
     *     percent?: int,
     *     label?: string,
     *     status?: string,
     *     showPercent?: bool
     * } $config
     */
    public static function renderBar(array $config): void
    {
        $percent = max(0, min(100, $config['percent'] ?? 0));
        $label = $config['label'] ?? __('Elaborazione in corso...', 'fp-dms');
        $status = $config['status'] ?? 'progress'; // progress, success, error
        $showPercent = $config['showPercent'] ?? true;
        
        echo '<div class="fpdms-progress-container">';
        
        if ($label) {
            echo '<div class="fpdms-progress-label">';
            echo esc_html($label);
            if ($showPercent) {
                echo ' <span class="fpdms-progress-percent">' . esc_html($percent) . '%</span>';
            }
            echo '</div>';
        }
        
        echo '<div class="fpdms-progress-bar-container">';
        echo '<div class="fpdms-progress-bar fpdms-progress-' . esc_attr($status) . '" style="width:' . esc_attr((string)$percent) . '%">';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        self::renderStyles();
    }
    
    /**
     * Render spinner (for indeterminate progress)
     *
     * @param array{
     *     label?: string,
     *     size?: string,
     *     inline?: bool
     * } $config
     */
    public static function renderSpinner(array $config = []): void
    {
        $label = $config['label'] ?? '';
        $size = $config['size'] ?? 'medium'; // small, medium, large
        $inline = $config['inline'] ?? false;
        
        $containerClass = 'fpdms-spinner-container';
        if ($inline) {
            $containerClass .= ' fpdms-spinner-inline';
        }
        
        echo '<div class="' . esc_attr($containerClass) . '">';
        echo '<div class="fpdms-spinner fpdms-spinner-' . esc_attr($size) . '"></div>';
        
        if ($label) {
            echo '<span class="fpdms-spinner-label">' . esc_html($label) . '</span>';
        }
        
        echo '</div>';
        
        self::renderStyles();
    }
    
    /**
     * Render step indicator (e.g., "Step 2 of 5")
     *
     * @param array{
     *     current: int,
     *     total: int,
     *     labels?: array<int, string>
     * } $config
     */
    public static function renderSteps(array $config): void
    {
        $current = $config['current'];
        $total = $config['total'];
        $labels = $config['labels'] ?? [];
        
        echo '<div class="fpdms-steps-container">';
        
        for ($i = 1; $i <= $total; $i++) {
            $isActive = $i === $current;
            $isComplete = $i < $current;
            $stepClass = 'fpdms-step';
            
            if ($isActive) {
                $stepClass .= ' is-active';
            } elseif ($isComplete) {
                $stepClass .= ' is-complete';
            }
            
            echo '<div class="' . esc_attr($stepClass) . '">';
            echo '<div class="fpdms-step-number">';
            
            if ($isComplete) {
                echo '<span class="dashicons dashicons-yes"></span>';
            } else {
                echo esc_html((string)$i);
            }
            
            echo '</div>';
            
            if (isset($labels[$i - 1])) {
                echo '<div class="fpdms-step-label">' . esc_html($labels[$i - 1]) . '</div>';
            }
            
            echo '</div>';
            
            if ($i < $total) {
                echo '<div class="fpdms-step-connector' . ($isComplete ? ' is-complete' : '') . '"></div>';
            }
        }
        
        echo '</div>';
        
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
        /* Progress Bar */
        .fpdms-progress-container {
            margin: 16px 0;
        }
        
        .fpdms-progress-label {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fpdms-progress-percent {
            color: #667eea;
            font-weight: 700;
        }
        
        .fpdms-progress-bar-container {
            width: 100%;
            height: 12px;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .fpdms-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
            transition: width 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .fpdms-progress-bar::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: fpdms-shimmer 2s infinite;
        }
        
        @keyframes fpdms-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .fpdms-progress-success {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .fpdms-progress-error {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
        }
        
        /* Spinner */
        .fpdms-spinner-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .fpdms-spinner-container.fpdms-spinner-inline {
            flex-direction: row;
            padding: 0;
            gap: 10px;
        }
        
        .fpdms-spinner {
            border: 3px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: fpdms-spin 0.8s linear infinite;
        }
        
        .fpdms-spinner-small {
            width: 20px;
            height: 20px;
            border-width: 2px;
        }
        
        .fpdms-spinner-medium {
            width: 40px;
            height: 40px;
        }
        
        .fpdms-spinner-large {
            width: 60px;
            height: 60px;
            border-width: 4px;
        }
        
        @keyframes fpdms-spin {
            to { transform: rotate(360deg); }
        }
        
        .fpdms-spinner-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 12px;
        }
        
        .fpdms-spinner-inline .fpdms-spinner-label {
            margin-top: 0;
        }
        
        /* Steps */
        .fpdms-steps-container {
            display: flex;
            align-items: center;
            padding: 24px 0;
        }
        
        .fpdms-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 80px;
        }
        
        .fpdms-step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
            margin-bottom: 8px;
        }
        
        .fpdms-step.is-active .fpdms-step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transform: scale(1.1);
        }
        
        .fpdms-step.is-complete .fpdms-step-number {
            background: #10b981;
            color: white;
        }
        
        .fpdms-step.is-complete .fpdms-step-number .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        
        .fpdms-step-label {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            font-weight: 500;
        }
        
        .fpdms-step.is-active .fpdms-step-label {
            color: #667eea;
            font-weight: 700;
        }
        
        .fpdms-step-connector {
            flex: 1;
            height: 2px;
            background: #e5e7eb;
            margin: 0 8px 24px 8px;
            transition: background 0.3s;
        }
        
        .fpdms-step-connector.is-complete {
            background: #10b981;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-steps-container {
                overflow-x: auto;
                padding: 16px 0;
            }
            
            .fpdms-step {
                min-width: 60px;
            }
            
            .fpdms-step-number {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .fpdms-step-label {
                font-size: 10px;
            }
        }
        </style>';
    }
}

