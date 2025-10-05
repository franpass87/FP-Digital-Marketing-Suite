# Implementation Guide - Connection Simplification

## 📋 Overview

This guide explains how the connection simplification features have been implemented and how to integrate them into your workflow.

## 🏗️ Architecture

### Components Implemented

```
src/
├── Services/Connectors/
│   ├── ErrorTranslator.php          # User-friendly error messages
│   ├── ConnectionTemplate.php       # Pre-configured templates
│   ├── AutoDiscovery.php           # Auto-discovery of resources
│   └── ConnectorException.php      # Already existing
│
├── Admin/
│   ├── ConnectionWizard/
│   │   ├── WizardStep.php          # Interface for wizard steps
│   │   ├── AbstractWizardStep.php  # Base implementation
│   │   ├── ConnectionWizard.php    # Main wizard orchestrator
│   │   └── Steps/
│   │       ├── IntroStep.php       # Introduction
│   │       ├── TemplateSelectionStep.php
│   │       ├── ServiceAccountStep.php
│   │       ├── GA4PropertyStep.php
│   │       ├── GSCSiteStep.php
│   │       ├── TestConnectionStep.php
│   │       └── FinishStep.php
│   │
│   └── Support/Ajax/
│       └── ConnectionAjaxHandler.php # AJAX endpoints
│
└── Plugin.php                       # Integration class

assets/
├── js/
│   ├── connection-validator.js     # Real-time validation
│   └── connection-wizard.js        # Wizard interactions
│
└── css/
    └── connection-validator.css    # Validator styles

tests/Unit/
├── ErrorTranslatorTest.php
├── ConnectionTemplateTest.php
└── AutoDiscoveryTest.php
```

## 🚀 Integration Steps

### Step 1: Bootstrap the Integration

Add to your main plugin file (e.g., `fp-digital-marketing-suite.php`):

```php
<?php

// After existing autoloader setup...

use FP\DMS\ConnectionWizardIntegration;

// Initialize connection wizard integration
add_action('plugins_loaded', function() {
    ConnectionWizardIntegration::init();
}, 20);
```

### Step 2: Update Data Sources Page

Modify `src/Admin/Pages/DataSourcesPage.php` to add "Use Wizard" button:

```php
public function render(): void
{
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Data Sources', 'fp-dms'); ?></h1>
        
        <!-- Add wizard button -->
        <div class="fpdms-add-source-options">
            <div class="button-group">
                <a href="<?php echo admin_url('admin.php?page=fpdms-connection-wizard&provider=ga4'); ?>" 
                   class="button button-primary">
                    🧙 <?php _e('Add with Wizard', 'fp-dms'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=fpdms-data-sources&action=new'); ?>" 
                   class="button">
                    ➕ <?php _e('Add Manually', 'fp-dms'); ?>
                </a>
            </div>
        </div>
        
        <!-- Provider selection for wizard -->
        <div class="fpdms-provider-selector" style="display: none;">
            <h3><?php _e('Choose a provider:', 'fp-dms'); ?></h3>
            <div class="fpdms-provider-grid">
                <?php foreach ($this->getProviders() as $provider): ?>
                    <a href="<?php echo admin_url('admin.php?page=fpdms-connection-wizard&provider=' . $provider['id']); ?>" 
                       class="fpdms-provider-card">
                        <span class="icon"><?php echo $provider['icon']; ?></span>
                        <span class="label"><?php echo $provider['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Existing data sources table... -->
    </div>
    <?php
}

private function getProviders(): array
{
    return [
        ['id' => 'ga4', 'label' => 'Google Analytics 4', 'icon' => '📊'],
        ['id' => 'gsc', 'label' => 'Google Search Console', 'icon' => '🔍'],
        ['id' => 'google_ads', 'label' => 'Google Ads', 'icon' => '🎯'],
        ['id' => 'meta_ads', 'label' => 'Meta Ads', 'icon' => '📱'],
        ['id' => 'clarity', 'label' => 'Microsoft Clarity', 'icon' => '📈'],
        ['id' => 'csv_generic', 'label' => 'CSV Import', 'icon' => '📄'],
    ];
}
```

### Step 3: Register AJAX Actions

The `ConnectionAjaxHandler::register()` is already called in `ConnectionWizardIntegration::init()`, but ensure these actions are available:

- `wp_ajax_fpdms_test_connection_live`
- `wp_ajax_fpdms_discover_resources`
- `wp_ajax_fpdms_validate_field`
- `wp_ajax_fpdms_wizard_load_step`
- `wp_ajax_fpdms_save_connection`

### Step 4: Update Security Class

Ensure the `Security` class has the `verifyNonce` method:

```php
// src/Support/Security.php

public static function verifyNonce(string $nonce, string $action): bool
{
    return wp_verify_nonce($nonce, $action) !== false;
}
```

## 📝 Usage Examples

### Using ErrorTranslator

```php
use FP\DMS\Services\Connectors\ConnectorException;
use FP\DMS\Services\Connectors\ErrorTranslator;

try {
    $provider->fetchMetrics($period);
} catch (ConnectorException $e) {
    $translated = ErrorTranslator::translate($e);
    
    // Display user-friendly error
    echo '<div class="notice notice-error">';
    echo '<h4>' . esc_html($translated['title']) . '</h4>';
    echo '<p>' . esc_html($translated['message']) . '</p>';
    
    if (!empty($translated['actions'])) {
        echo '<p>';
        foreach ($translated['actions'] as $action) {
            if ($action['type'] === 'link') {
                echo '<a href="' . esc_url($action['url']) . '" class="button">';
                echo esc_html($action['label']);
                echo '</a> ';
            }
        }
        echo '</p>';
    }
    echo '</div>';
}
```

### Using ConnectionTemplate

```php
use FP\DMS\Services\Connectors\ConnectionTemplate;

// Get templates for GA4
$templates = ConnectionTemplate::getTemplatesByProvider('ga4');

// Apply a template
$config = ['name' => 'My GA4 Source'];
$config = ConnectionTemplate::applyTemplate('ga4_ecommerce', $config);

// Now $config has:
// - 'metrics' => [...] (pre-configured metrics)
// - 'dimensions' => [...] (if applicable)
// - 'template_used' => 'ga4_ecommerce'
```

### Using AutoDiscovery

```php
use FP\DMS\Services\Connectors\AutoDiscovery;

$serviceAccountJson = '{"type":"service_account",...}';

// Discover GA4 properties
try {
    $properties = AutoDiscovery::discoverGA4Properties($serviceAccountJson);
    
    foreach ($properties as $property) {
        echo $property['display_name'] . ' (' . $property['id'] . ')<br>';
    }
} catch (ConnectorException $e) {
    echo 'Discovery failed: ' . $e->getMessage();
}

// Test connection with enriched data
$enriched = AutoDiscovery::testAndEnrichGA4Connection($serviceAccountJson, '123456789');

if ($enriched['success']) {
    echo 'Property name: ' . $enriched['property_name'];
    echo 'Last data: ' . $enriched['last_data'];
}
```

## 🧪 Testing

### Run Unit Tests

```bash
# Run all new tests
./vendor/bin/phpunit tests/Unit/ErrorTranslatorTest.php
./vendor/bin/phpunit tests/Unit/ConnectionTemplateTest.php
./vendor/bin/phpunit tests/Unit/AutoDiscoveryTest.php

# Or run all tests
./vendor/bin/phpunit
```

### Manual Testing Checklist

- [ ] Install and activate plugin
- [ ] Go to Data Sources page
- [ ] Click "Add with Wizard"
- [ ] Select GA4 provider
- [ ] Follow wizard steps:
  - [ ] Intro step shows provider info
  - [ ] Template selection works
  - [ ] Service account validation works
  - [ ] Auto-discovery finds properties
  - [ ] Manual entry validates format
  - [ ] Test connection works
  - [ ] Finish step shows success
- [ ] Verify data source is created
- [ ] Test with invalid credentials
- [ ] Verify error messages are user-friendly
- [ ] Test real-time validation on input fields
- [ ] Test auto-format suggestions

## 🎨 Customization

### Adding a New Template

```php
// In ConnectionTemplate::getTemplates(), add:

'custom_template' => [
    'name' => __('My Custom Template', 'fp-dms'),
    'description' => __('Custom metrics configuration', 'fp-dms'),
    'provider' => 'ga4',
    'metrics_preset' => ['metric1', 'metric2'],
    'dimensions_preset' => ['date'],
    'recommended_for' => [__('Custom Use Case', 'fp-dms')],
    'icon' => '🎨',
],
```

### Adding a New Provider Wizard

1. Create new step classes in `src/Admin/ConnectionWizard/Steps/`
2. Add provider case in `ConnectionWizard::loadSteps()`
3. Add validation logic in step classes
4. Update JavaScript validators if needed

Example:

```php
// ConnectionWizard.php

private function getCustomProviderSteps(): array
{
    return [
        new Steps\IntroStep('intro', 'custom_provider'),
        new Steps\CustomProviderConfigStep('config', 'custom_provider'),
        new Steps\TestConnectionStep('test', 'custom_provider'),
        new Steps\FinishStep('finish', 'custom_provider'),
    ];
}
```

### Customizing Error Messages

Override translations in your theme or plugin:

```php
add_filter('gettext', function($translation, $text, $domain) {
    if ($domain !== 'fp-dms') return $translation;
    
    if ($text === 'Invalid Credentials') {
        return 'Oops! Those credentials didn\'t work';
    }
    
    return $translation;
}, 10, 3);
```

## 🔧 Troubleshooting

### Wizard Not Showing

**Issue**: Wizard page returns 404

**Solution**:
1. Flush rewrite rules: Go to Settings → Permalinks and click Save
2. Check `ConnectionWizardIntegration::init()` is called
3. Verify user has `manage_options` capability

### AJAX Calls Failing

**Issue**: AJAX requests return 403 or 400

**Solution**:
1. Check nonce is being passed correctly
2. Verify `ConnectionAjaxHandler::register()` is called
3. Check browser console for JavaScript errors
4. Verify `ajaxurl` is defined (default in WordPress admin)

### Validation Not Working

**Issue**: Real-time validation doesn't show

**Solution**:
1. Check JavaScript is enqueued: View page source, search for `connection-validator.js`
2. Check browser console for errors
3. Verify fields have `fpdms-validated-field` class
4. Check `fpdmsI18n` is localized

### Auto-Discovery Returns Empty

**Issue**: No properties/sites found

**Solution**:
1. Verify service account has access to resources
2. Check API is enabled in Google Cloud Console
3. Test credentials manually with API explorer
4. Check error logs for API response details

## 📊 Performance Considerations

### Caching Discovery Results

To avoid repeated API calls:

```php
// Cache discovered properties for 1 hour
$cacheKey = 'fpdms_discovered_' . md5($serviceAccountJson);
$properties = get_transient($cacheKey);

if ($properties === false) {
    $properties = AutoDiscovery::discoverGA4Properties($serviceAccountJson);
    set_transient($cacheKey, $properties, HOUR_IN_SECONDS);
}
```

### Lazy Loading Wizard Steps

Only load step content when needed via AJAX to reduce initial page load.

## 🔐 Security Best Practices

1. **Never log sensitive data**: Service account JSONs should never appear in logs
2. **Use nonces**: All AJAX requests must verify nonces
3. **Validate server-side**: Never trust client-side validation alone
4. **Sanitize input**: All user input must be sanitized
5. **Check capabilities**: Verify user permissions before processing

## 📈 Success Metrics

Track these metrics to measure improvement:

```php
// Track wizard usage
do_action('fpdms_wizard_started', $provider);
do_action('fpdms_wizard_completed', $provider, $time_taken);
do_action('fpdms_wizard_abandoned', $provider, $step);

// Track errors
do_action('fpdms_connection_error', $provider, $error_type);
do_action('fpdms_connection_success', $provider);
```

## 🎓 Training Resources

- Video: "Using the Connection Wizard" (TBD)
- Doc: "Troubleshooting Connection Issues" (TBD)
- FAQ: "Common Setup Problems" (TBD)

## 🤝 Contributing

To add new features:

1. Create feature branch: `git checkout -b feature/new-wizard-step`
2. Add tests for new functionality
3. Update this documentation
4. Submit pull request

---

**Last Updated**: 2025-10-05  
**Version**: 1.0  
**Author**: Cursor AI Background Agent
