<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;

/**
 * CSV import configuration step.
 */
class CSVConfigStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('CSV Configuration', 'fp-dms'),
            __('Configure how to import your CSV data', 'fp-dms')
        );
    }

    public function render(array $data): string
    {
        $csvPath = $data['config']['csv_path'] ?? '';
        $delimiter = $data['config']['delimiter'] ?? ',';
        $hasHeaders = $data['config']['has_headers'] ?? true;
        $dateColumn = $data['config']['date_column'] ?? 'date';

        ob_start();
        ?>
        <div class="fpdms-csv-config-step">
            <?php echo $this->renderHelpPanel(
                __('📄 About CSV Import', 'fp-dms'),
                $this->getCSVHelp(),
                []
            ); ?>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 1: CSV File Location', 'fp-dms'); ?></h3>
                
                <?php echo $this->renderTextField(
                    'csv_path',
                    __('CSV File Path or URL', 'fp-dms'),
                    $csvPath,
                    [
                        'required' => true,
                        'placeholder' => '/path/to/data.csv or https://example.com/data.csv',
                        'description' => __('Local file path or remote URL to your CSV file', 'fp-dms'),
                    ]
                ); ?>

                <div class="fpdms-csv-upload">
                    <p><strong><?php _e('Or upload a file:', 'fp-dms'); ?></strong></p>
                    <input type="file" 
                           id="fpdms_csv_file" 
                           accept=".csv,text/csv" 
                           class="fpdms-csv-file-input" />
                    <p class="description">
                        <?php _e('Upload a CSV file to use as your data source', 'fp-dms'); ?>
                    </p>
                </div>
            </div>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 2: CSV Format', 'fp-dms'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fpdms_delimiter"><?php _e('Delimiter', 'fp-dms'); ?></label>
                        </th>
                        <td>
                            <select name="delimiter" id="fpdms_delimiter" class="regular-text">
                                <option value="," <?php selected($delimiter, ','); ?>><?php _e('Comma (,)', 'fp-dms'); ?></option>
                                <option value=";" <?php selected($delimiter, ';'); ?>><?php _e('Semicolon (;)', 'fp-dms'); ?></option>
                                <option value="\t" <?php selected($delimiter, "\t"); ?>><?php _e('Tab', 'fp-dms'); ?></option>
                                <option value="|" <?php selected($delimiter, '|'); ?>><?php _e('Pipe (|)', 'fp-dms'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Character used to separate columns in your CSV', 'fp-dms'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fpdms_has_headers"><?php _e('First Row', 'fp-dms'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="has_headers" 
                                       id="fpdms_has_headers" 
                                       value="1" 
                                       <?php checked($hasHeaders); ?> />
                                <?php _e('First row contains column headers', 'fp-dms'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Check this if your CSV has column names in the first row', 'fp-dms'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="fpdms-field-group">
                <h3><?php _e('Step 3: Column Mapping', 'fp-dms'); ?></h3>

                <p><?php _e('Map your CSV columns to standard fields:', 'fp-dms'); ?></p>

                <table class="widefat fpdms-column-mapping">
                    <thead>
                        <tr>
                            <th><?php _e('Standard Field', 'fp-dms'); ?></th>
                            <th><?php _e('Your CSV Column', 'fp-dms'); ?></th>
                            <th><?php _e('Required', 'fp-dms'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Date', 'fp-dms'); ?></strong></td>
                            <td>
                                <input type="text" 
                                       name="date_column" 
                                       value="<?php echo esc_attr($dateColumn); ?>" 
                                       placeholder="date"
                                       class="regular-text" />
                            </td>
                            <td><span class="required">*</span></td>
                        </tr>
                        <?php foreach ($this->getStandardFields() as $field => $label) : ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <td>
                                <input type="text" 
                                       name="column_<?php echo esc_attr($field); ?>" 
                                       value="<?php echo esc_attr($data['config']['column_' . $field] ?? ''); ?>" 
                                       placeholder="<?php echo esc_attr($field); ?>"
                                       class="regular-text" />
                            </td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description">
                    <?php _e('Leave blank if the column doesn\'t exist in your CSV', 'fp-dms'); ?>
                </p>
            </div>

            <div class="fpdms-csv-preview">
                <h3><?php _e('Step 4: Preview', 'fp-dms'); ?></h3>
                <button type="button" class="button button-secondary fpdms-btn-preview-csv">
                    👁️ <?php _e('Preview CSV Data', 'fp-dms'); ?>
                </button>
                <div class="fpdms-csv-preview-result" style="display: none;">
                    <!-- Preview will be loaded here -->
                </div>
            </div>

            <div class="fpdms-csv-example">
                <h4><?php _e('💡 Example CSV Format:', 'fp-dms'); ?></h4>
                <pre><code>date,users,sessions,clicks,impressions,cost,revenue
2024-01-01,1234,5678,890,12345,123.45,678.90
2024-01-02,1456,6789,901,13456,134.56,789.01</code></pre>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        $errors = [];

        // Validate CSV path
        $csvPath = $data['config']['csv_path'] ?? '';
        if (empty($csvPath)) {
            $errors['csv_path'] = __('CSV file path or URL is required', 'fp-dms');
        } elseif (!filter_var($csvPath, FILTER_VALIDATE_URL) && !file_exists($csvPath)) {
            $errors['csv_path'] = __('CSV file not found or invalid URL', 'fp-dms');
        }

        // Validate date column
        $dateColumn = $data['config']['date_column'] ?? '';
        if (empty($dateColumn)) {
            $errors['date_column'] = __('Date column mapping is required', 'fp-dms');
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    public function getHelp(): array
    {
        return [
            'title' => __('About CSV Import', 'fp-dms'),
            'content' => __(
                'CSV import allows you to import data from any source. Your CSV file should have a date column and at least one metric column. The data will be synced based on your schedule.',
                'fp-dms'
            ),
        ];
    }

    private function getCSVHelp(): string
    {
        return '
            <p>' . __('CSV import is perfect for:', 'fp-dms') . '</p>
            <ul>
                <li>✓ ' . __('Importing data from custom sources', 'fp-dms') . '</li>
                <li>✓ ' . __('Consolidating metrics from multiple tools', 'fp-dms') . '</li>
                <li>✓ ' . __('Manual data entry', 'fp-dms') . '</li>
                <li>✓ ' . __('Legacy data migration', 'fp-dms') . '</li>
            </ul>
            
            <h4>' . __('Requirements:', 'fp-dms') . '</h4>
            <ul>
                <li>✓ ' . __('Must have a date column', 'fp-dms') . '</li>
                <li>✓ ' . __('Date format: YYYY-MM-DD (e.g., 2024-01-15)', 'fp-dms') . '</li>
                <li>✓ ' . __('Numeric values without currency symbols', 'fp-dms') . '</li>
                <li>✓ ' . __('Consistent column names', 'fp-dms') . '</li>
            </ul>
        ';
    }

    private function getStandardFields(): array
    {
        return [
            'users' => __('Users', 'fp-dms'),
            'sessions' => __('Sessions', 'fp-dms'),
            'clicks' => __('Clicks', 'fp-dms'),
            'impressions' => __('Impressions', 'fp-dms'),
            'cost' => __('Cost', 'fp-dms'),
            'revenue' => __('Revenue', 'fp-dms'),
            'conversions' => __('Conversions', 'fp-dms'),
            'bounce_rate' => __('Bounce Rate', 'fp-dms'),
            'avg_session_duration' => __('Avg Session Duration', 'fp-dms'),
        ];
    }
}
