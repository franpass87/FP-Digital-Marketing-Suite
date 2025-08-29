# Internationalization (i18n) Documentation
## FP Digital Marketing Suite

This document describes the internationalization implementation and translation process for the FP Digital Marketing Suite plugin.

## Overview

The plugin supports full internationalization with the following features:

- **Text Domain**: `fp-digital-marketing`
- **Domain Path**: `/languages`
- **Default Language**: Italian (it_IT)
- **Supported Languages**: Italian (it_IT), English (en_US)
- **Translation Functions**: All user-facing strings use WordPress i18n functions

## File Structure

```
languages/
├── .gitkeep                           # Git repository file
├── fp-digital-marketing.pot           # Template file for translators
├── fp-digital-marketing-it_IT.po      # Italian translation source
├── fp-digital-marketing-it_IT.mo      # Italian compiled translation
├── fp-digital-marketing-en_US.po      # English translation source
└── fp-digital-marketing-en_US.mo      # English compiled translation
```

## Translation Functions Used

The plugin uses standard WordPress internationalization functions:

- `__( $text, 'fp-digital-marketing' )` - Returns translated string
- `_e( $text, 'fp-digital-marketing' )` - Echoes translated string
- `_x( $text, $context, 'fp-digital-marketing' )` - Translated string with context
- `esc_html__( $text, 'fp-digital-marketing' )` - Escaped HTML translated string
- `esc_attr__( $text, 'fp-digital-marketing' )` - Escaped attribute translated string
- `esc_html_e( $text, 'fp-digital-marketing' )` - Escaped HTML echoed translated string
- `esc_attr_e( $text, 'fp-digital-marketing' )` - Escaped attribute echoed translated string

## Adding New Translations

### For Developers

1. **Wrap all user-facing strings** with appropriate i18n functions:
   ```php
   // Before
   echo 'Save Settings';
   
   // After
   echo esc_html__( 'Save Settings', 'fp-digital-marketing' );
   ```

2. **Use context when needed** for strings that might be ambiguous:
   ```php
   _x( 'Post', 'noun: a blog post', 'fp-digital-marketing' );
   _x( 'Post', 'verb: to publish', 'fp-digital-marketing' );
   ```

3. **For JavaScript strings**, use `wp_localize_script()`:
   ```php
   wp_localize_script( 'script-handle', 'fpDmsI18n', [
       'confirmDelete' => __( 'Are you sure?', 'fp-digital-marketing' ),
   ]);
   ```

### Regenerating Translation Files

After adding new translatable strings:

1. **Update the POT file**:
   ```bash
   # Using wp-cli (recommended)
   wp i18n make-pot . languages/fp-digital-marketing.pot --domain=fp-digital-marketing
   
   # Or use the included generator script
   php /tmp/generate-pot.php
   ```

2. **Update existing translation files**:
   ```bash
   # Using msgmerge (if gettext tools are available)
   msgmerge --update languages/fp-digital-marketing-it_IT.po languages/fp-digital-marketing.pot
   msgmerge --update languages/fp-digital-marketing-en_US.po languages/fp-digital-marketing.pot
   ```

3. **Compile PO files to MO files**:
   ```bash
   # Using msgfmt (if gettext tools are available)
   msgfmt languages/fp-digital-marketing-it_IT.po -o languages/fp-digital-marketing-it_IT.mo
   msgfmt languages/fp-digital-marketing-en_US.po -o languages/fp-digital-marketing-en_US.mo
   
   # Or use the included converter script
   php /tmp/po-to-mo.php
   ```

## Adding a New Language

To add support for a new language (e.g., French - fr_FR):

1. **Copy the POT file**:
   ```bash
   cp languages/fp-digital-marketing.pot languages/fp-digital-marketing-fr_FR.po
   ```

2. **Update the PO file header**:
   ```po
   "Language: fr_FR\n"
   "Language-Team: French <fr@li.org>\n"
   "Last-Translator: Your Name <your.email@example.com>\n"
   ```

3. **Translate all msgstr entries**:
   ```po
   msgid "Save Settings"
   msgstr "Enregistrer les paramètres"
   ```

4. **Compile to MO file**:
   ```bash
   msgfmt languages/fp-digital-marketing-fr_FR.po -o languages/fp-digital-marketing-fr_FR.mo
   ```

## Translation Guidelines

### String Guidelines

1. **Keep strings concise but descriptive**
2. **Avoid concatenating translated strings**
3. **Use placeholders for dynamic content**:
   ```php
   sprintf( __( 'Welcome, %s!', 'fp-digital-marketing' ), $username );
   ```

4. **Provide context for ambiguous terms**:
   ```php
   _x( 'Comment', 'noun: user feedback', 'fp-digital-marketing' );
   ```

### Translation Best Practices

1. **Maintain consistent terminology** across all strings
2. **Respect cultural differences** in formatting (dates, numbers, currencies)
3. **Test translations in context** to ensure proper fit
4. **Consider text expansion** - some languages require more space

## Text Domain Loading

The plugin automatically loads the text domain in the main class:

```php
// In src/DigitalMarketingSuite.php
private function load_textdomain(): void {
    load_plugin_textdomain(
        'fp-digital-marketing',
        false,
        dirname( plugin_basename( FP_DIGITAL_MARKETING_PLUGIN_FILE ) ) . '/languages'
    );
}
```

This is called during plugin initialization to ensure translations are available before any strings are displayed.

## Testing Translations

### WordPress Language Settings

1. Go to **Settings > General** in WordPress admin
2. Set **Site Language** to your target language
3. Save changes and verify translations appear correctly

### Force Language for Testing

For development, you can force a specific language:

```php
// Add to wp-config.php for testing
define( 'WPLANG', 'it_IT' );
```

### Debugging Translation Issues

1. **Check file permissions** - MO files must be readable
2. **Verify file naming** - Must match WordPress naming conventions
3. **Clear caches** - Some caching plugins cache translations
4. **Check PHP errors** - Malformed MO files can cause issues

## Tools and Resources

### Recommended Tools

- **Poedit** - User-friendly PO file editor
- **WordPress.org GlotPress** - Online translation platform
- **WP-CLI i18n commands** - Command-line tools for developers

### WP-CLI Commands

```bash
# Generate POT file
wp i18n make-pot . languages/fp-digital-marketing.pot --domain=fp-digital-marketing

# Generate JSON files for JavaScript
wp i18n make-json languages/ --no-purge

# Update PO files
wp i18n update-po languages/fp-digital-marketing.pot languages/
```

## Maintenance

### Regular Tasks

1. **Update POT file** when adding new features
2. **Review translations** for accuracy and completeness
3. **Test in multiple languages** before releases
4. **Update documentation** when changing translation processes

### Version Control

- **Track PO files** in version control
- **Track MO files** in version control (for deployment)
- **Track POT file** as the master template

## Support

For translation questions or issues:

1. Check this documentation first
2. Review WordPress i18n best practices
3. Open an issue on the project repository
4. Contact the development team

## Statistics

Current translation status:

- **Total translatable strings**: 292
- **Italian (it_IT)**: 100% complete (292/292)
- **English (en_US)**: 100% complete (292/292)

Last updated: 2025-08-29