<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Shared;

use function esc_attr;
use function esc_html;

/**
 * Shared component for rendering HTML tables
 */
class TableRenderer
{
    /**
     * Render a WordPress admin table
     *
     * @param array<string> $headers Column headers
     * @param array<int, array<string, mixed>> $rows Table rows
     * @param array{
     *   class?: string,
     *   empty_message?: string,
     *   row_renderer?: callable
     * } $options
     */
    public static function render(array $headers, array $rows, array $options = []): void
    {
        $class = $options['class'] ?? 'widefat striped';
        $emptyMessage = $options['empty_message'] ?? '';
        $rowRenderer = $options['row_renderer'] ?? null;

        echo '<table class="' . esc_attr($class) . '">';
        
        // Headers
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr></thead>';
        
        // Body
        echo '<tbody>';
        if (empty($rows)) {
            $colspan = count($headers);
            echo '<tr><td colspan="' . esc_attr((string) $colspan) . '">';
            echo esc_html($emptyMessage ?: 'No data available.');
            echo '</td></tr>';
        } else {
            foreach ($rows as $row) {
                if ($rowRenderer !== null && is_callable($rowRenderer)) {
                    $rowRenderer($row);
                } else {
                    self::renderDefaultRow($row);
                }
            }
        }
        echo '</tbody>';
        
        echo '</table>';
    }

    /**
     * Render a default table row
     *
     * @param array<string, mixed> $row
     */
    private static function renderDefaultRow(array $row): void
    {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . esc_html((string) $cell) . '</td>';
        }
        echo '</tr>';
    }

    /**
     * Start a table row
     */
    public static function startRow(string $class = ''): void
    {
        $classAttr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
        echo '<tr' . $classAttr . '>';
    }

    /**
     * End a table row
     */
    public static function endRow(): void
    {
        echo '</tr>';
    }

    /**
     * Render a table cell
     *
     * @param string|int|float $content
     */
    public static function cell($content, string $class = ''): void
    {
        $classAttr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
        echo '<td' . $classAttr . '>' . esc_html((string) $content) . '</td>';
    }

    /**
     * Render a table cell with raw HTML (use with caution, ensure content is escaped)
     */
    public static function rawCell(string $html, string $class = ''): void
    {
        $classAttr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
        echo '<td' . $classAttr . '>' . $html . '</td>';
    }
}