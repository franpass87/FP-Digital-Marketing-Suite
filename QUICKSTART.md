# 🚀 Quick Start - Connection Wizard

Get the wizard running in **5 minutes**!

## Step 1: Integrate (1 minute)

Add to your main plugin file (`fp-digital-marketing-suite.php`):

```php
<?php
/**
 * Plugin Name: FP Digital Marketing Suite
 * ...existing headers...
 */

// ...existing code...

// 👇 ADD THIS AFTER YOUR AUTOLOADER
use FP\DMS\ConnectionWizardIntegration;

add_action('plugins_loaded', function() {
    ConnectionWizardIntegration::init();
}, 20);
```

## Step 2: Test (2 minutes)

### Quick Test - GA4 Wizard

1. Go to WordPress Admin → FP Marketing Suite → Data Sources

2. Open this URL in your browser:
   ```
   /wp-admin/admin.php?page=fpdms-connection-wizard&provider=ga4
   ```

3. You should see the wizard! 🎉

### Test Validation

In browser console:
```javascript
// Test validator
const validator = new ConnectionValidator('ga4');
console.log(validator.validateGA4PropertyId('123456789')); // Should return {valid: true}
console.log(validator.validateGA4PropertyId('abc')); // Should return {valid: false}
```

## Step 3: Add UI Button (2 minutes)

Update `src/Admin/Pages/DataSourcesPage.php`:

```php
public function render(): void
{
    ?>
    <div class="wrap">
        <h1><?php _e('Data Sources', 'fp-dms'); ?></h1>
        
        <!-- 👇 ADD THIS -->
        <p>
            <a href="<?php echo admin_url('admin.php?page=fpdms-connection-wizard&provider=ga4'); ?>" 
               class="button button-primary button-hero">
                🧙 <?php _e('Add Data Source (Wizard)', 'fp-dms'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=fp-dms-datasources&action=new'); ?>" 
               class="button">
                ➕ <?php _e('Add Manually', 'fp-dms'); ?>
            </a>
        </p>
        
        <!-- ...rest of your page... -->
    </div>
    <?php
}
```

## Done! ✅

The wizard is now active. Test each provider:

- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=ga4`
- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=gsc`
- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=google_ads`
- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=meta_ads`
- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=clarity`
- `/wp-admin/admin.php?page=fpdms-connection-wizard&provider=csv_generic`

---

## Troubleshooting (if needed)

### Wizard not showing?

**Check 1**: Assets loaded?
```bash
# View page source, search for:
connection-validator.js
connection-wizard.js
```

**Check 2**: Flush permalinks
```
Settings → Permalinks → Save Changes
```

**Check 3**: JavaScript errors?
```
F12 → Console tab
```

### Validation not working?

**Check**: `fpdmsI18n` object defined?
```javascript
// In browser console:
console.log(window.fpdmsI18n);
// Should show object with translations
```

### AJAX failing?

**Check**: Nonce valid?
```javascript
// In browser console:
console.log(fpdmsWizard.nonce);
// Should show a hash
```

---

## Next Steps

1. **Read Full Guide**: `docs/IMPLEMENTATION_GUIDE.md`
2. **Test All Providers**: Use URLs above
3. **Customize**: See guide for customization options
4. **Deploy**: Follow deployment checklist

---

## Support

- 📖 **Docs**: `docs/IMPLEMENTATION_GUIDE.md`
- ❓ **FAQ**: Check troubleshooting section
- 🐛 **Issues**: GitHub Issues
- 💬 **Help**: Team Slack

---

**Time to first wizard**: 5 minutes ⏱️  
**Difficulty**: Easy 😊  
**Prerequisites**: Working FP DMS plugin

🎉 **Enjoy your new wizard!**
