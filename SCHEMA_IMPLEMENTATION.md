# Task 23: Structured Data (Schema.org) Generator - Implementation Documentation

## Overview

This implementation provides a comprehensive Schema.org structured data generator for the FP Digital Marketing Suite, fulfilling all the acceptance criteria:

✅ **JSON-LD valido e non duplicato**  
✅ **Disattivazione di un tipo rimuove markup senza side effects**  
✅ **Test unitari su serializzazione + escaping**  
✅ **Layer schema modulare documentato**

The schema generator adds JSON-LD markup to improve rich results in search engines with support for WebSite, Organization, BreadcrumbList, Article/BlogPosting, and FAQ schema types.

## Implementation

### 1. Core Schema Generator (`SchemaGenerator`)

The main `SchemaGenerator` class provides:

#### Key Features
- **Modular Architecture**: Each schema type is implemented as a separate class
- **Settings Integration**: UI toggles for enabling/disabling schema types
- **Developer Extensibility**: Hook and filter system for custom schemas
- **Validation**: Schema validation and sanitization
- **Performance**: Conditional generation based on page context

#### Core Methods
```php
// Generate all enabled schemas for current page
SchemaGenerator::generate_schemas(): array

// Generate specific schema type
SchemaGenerator::generate_schema(string $type): ?array

// Check if schema type is enabled
SchemaGenerator::is_schema_type_enabled(string $type): bool

// Sanitize schema data for safe output
SchemaGenerator::sanitize_schema_data(array $data): array

// Validate schema structure
SchemaGenerator::validate_schema(array $schema): bool
```

### 2. Individual Schema Classes

#### Base Schema (`BaseSchema`)
- Abstract base class for all schema types
- Common utility methods for site info, author data, dates
- Standardized schema structure creation

#### WebSite Schema (`WebSiteSchema`)
- Basic website information with SearchAction
- Applied globally but SearchAction only on home page
- Includes site name, URL, description

#### Organization Schema (`OrganizationSchema`)
- Organization/company information
- Applied on home/front page
- Supports logo, contact info, social profiles, address

#### BreadcrumbList Schema (`BreadcrumbListSchema`)
- Navigation breadcrumb markup
- Context-aware breadcrumb generation
- Supports categories, pages, archives, taxonomy pages

#### Article Schema (`ArticleSchema`)
- Article/BlogPosting markup for content
- Supports featured images, author info, keywords
- Includes word count, publication dates, categories

#### FAQ Schema (`FAQSchema`)
- FAQ page markup with question/answer pairs
- Supports Gutenberg blocks, shortcodes, content patterns
- Custom meta field integration

### 3. Gutenberg FAQ Block Integration

#### FAQ Block (`FAQBlock`)
- Custom Gutenberg block for FAQ content
- JavaScript editor interface with add/remove functionality
- Renders as HTML5 `<details>/<summary>` elements
- Automatic Schema.org integration

### 4. Settings Integration

#### Admin Settings
- New "Schema.org Structured Data" settings section
- Toggles for each schema type
- Organization configuration (name, URL, logo, description)
- FAQ post type selection
- Breadcrumb settings

#### Default Settings
```php
[
    'enabled_types' => ['website', 'organization', 'breadcrumb', 'article', 'faq'],
    'organization_name' => get_bloginfo('name'),
    'organization_url' => home_url(),
    'enable_breadcrumbs' => true,
    'faq_post_types' => ['post', 'page']
]
```

## Files Created/Modified

### New Files
- `src/Helpers/SchemaGenerator.php` - Main schema generator class
- `src/Helpers/Schema/BaseSchema.php` - Abstract base class
- `src/Helpers/Schema/WebSiteSchema.php` - WebSite schema implementation
- `src/Helpers/Schema/OrganizationSchema.php` - Organization schema implementation
- `src/Helpers/Schema/BreadcrumbListSchema.php` - BreadcrumbList schema implementation
- `src/Helpers/Schema/ArticleSchema.php` - Article/BlogPosting schema implementation
- `src/Helpers/Schema/FAQSchema.php` - FAQ schema implementation
- `src/Helpers/FAQBlock.php` - Gutenberg FAQ block integration
- `assets/js/faq-block.js` - FAQ block JavaScript editor
- `tests/SchemaGeneratorTest.php` - Core generator tests
- `tests/WebSiteSchemaTest.php` - WebSite schema tests
- `tests/ArticleSchemaTest.php` - Article schema tests

### Modified Files
- `src/DigitalMarketingSuite.php` - Added schema generator and FAQ block initialization
- `src/Admin/Settings.php` - Added schema settings section and fields

## Technical Features

### Hook and Filter System

#### Available Filters
```php
// Filter available schema types
apply_filters('fp_dms_schema_types', $schema_types);

// Filter enabled schema types
apply_filters('fp_dms_enabled_schema_types', $enabled_types);

// Filter generated schemas before output
apply_filters('fp_dms_generated_schemas', $schemas);

// Filter individual schema types
apply_filters('fp_dms_website_schema', $schema);
apply_filters('fp_dms_organization_schema', $schema);
apply_filters('fp_dms_breadcrumb_schema', $schema);
apply_filters('fp_dms_article_schema', $schema, $post);
apply_filters('fp_dms_faq_schema', $schema, $post);

// Custom validation
apply_filters('fp_dms_validate_schema', $is_valid, $schema);
```

#### Developer Extension Example
```php
// Add custom schema type
add_filter('fp_dms_schema_types', function($types) {
    $types['custom_type'] = [
        'class' => 'CustomSchema',
        'name' => 'Custom Schema',
        'description' => 'Custom schema description'
    ];
    return $types;
});

// Modify organization schema
add_filter('fp_dms_organization_schema', function($schema) {
    $schema['foundingDate'] = '2020-01-01';
    return $schema;
});
```

### Sanitization and Security

#### Input Sanitization
- All user inputs sanitized using WordPress functions
- URL validation with `esc_url_raw()`
- Text sanitization with `sanitize_text_field()`
- HTML stripping with `wp_strip_all_tags()`

#### JSON-LD Output Security
- XSS prevention through proper escaping
- Schema validation before output
- WordPress nonce verification for settings

#### Schema Validation
```php
// Required properties validation
$schema['@context'] === 'https://schema.org'
$schema['@type'] exists and not empty

// Custom validation hooks
apply_filters('fp_dms_validate_schema', true, $schema)
```

### Performance Optimization

#### Conditional Generation
- Schemas only generated when applicable to current page
- `is_applicable()` method for each schema type
- Early exit for disabled schema types

#### Efficient Output
- Single JSON-LD script tag with all schemas
- No duplicate schema generation
- Leverages existing WordPress caching

## Testing

### Unit Test Coverage

#### SchemaGeneratorTest (11 tests)
- Schema types configuration
- Enabled types management
- Schema sanitization and validation
- Default settings
- Integration testing
- Duplication prevention

#### WebSiteSchemaTest (5 tests)
- Home page vs non-home page generation
- Search action inclusion
- Special character handling
- Empty description handling

#### ArticleSchemaTest (8 tests)
- Blog post vs page schema types
- Featured image integration
- SEO description priority
- Word count calculation
- Author information
- Keywords and categories

### Running Tests
```bash
# Run schema-specific tests
phpunit tests/SchemaGeneratorTest.php
phpunit tests/WebSiteSchemaTest.php
phpunit tests/ArticleSchemaTest.php

# Run all tests
phpunit
```

## Usage Examples

### 1. Basic Configuration
Navigate to Settings > FP Digital Marketing > Schema.org Structured Data section to:
- Enable/disable schema types
- Configure organization information
- Set FAQ post types
- Enable breadcrumb markup

### 2. Adding FAQ Content

#### Using Gutenberg Block
1. Add "FAQ (FP Digital Marketing)" block
2. Enter questions and answers
3. Publish - FAQ schema automatically generated

#### Using Custom Meta Fields
```php
$faqs = [
    ['question' => 'What is this?', 'answer' => 'This is an example'],
    ['question' => 'How does it work?', 'answer' => 'It works like this']
];
update_post_meta($post_id, '_fp_faqs', $faqs);
```

### 3. Developer Customization

#### Add Custom Schema Type
```php
class CustomProductSchema extends BaseSchema {
    public static function generate(): ?array {
        return self::create_base_schema('Product', [
            'name' => 'Custom Product',
            'description' => 'Product description'
        ]);
    }
    
    public static function is_applicable(): bool {
        return is_singular('product');
    }
}

// Register the custom schema
add_filter('fp_dms_schema_types', function($types) {
    $types['product'] = [
        'class' => 'CustomProductSchema',
        'name' => 'Product Schema',
        'description' => 'E-commerce product markup'
    ];
    return $types;
});
```

### 4. Validation with Google Rich Results Tester

Test your schemas at: https://search.google.com/test/rich-results

Example generated JSON-LD:
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Example Site",
  "url": "https://example.com",
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "https://example.com/?s={search_term_string}"
    },
    "query-input": "required name=search_term_string"
  }
}
```

## Compliance with Acceptance Criteria

✅ **JSON-LD valido e non duplicato**
- Valid Schema.org JSON-LD output with proper context and types
- Duplication prevention through conditional generation
- Schema validation before output

✅ **Disattivazione di un tipo rimuove markup senza side effects**
- Individual schema types can be disabled via settings
- No markup generated for disabled types
- Clean removal without affecting other schemas

✅ **Test unitari su serializzazione + escaping**
- Comprehensive test suite with 24+ tests
- Serialization testing with special characters
- XSS prevention and escaping validation
- JSON encoding compatibility testing

✅ **Layer schema modulare documentato**
- Modular architecture with separate classes per schema type
- Extensible design with hooks and filters
- Comprehensive documentation with examples
- Developer-friendly API

**Output:** Modular documented schema layer ready for production use with rich results support and developer extensibility.

## Future Enhancements

### Planned Schema Types
1. **LocalBusiness**: For local business information
2. **Review/Rating**: Product and service reviews
3. **Event**: Event markup for event pages
4. **Recipe**: Recipe schema for food blogs
5. **VideoObject**: Video content markup

### Advanced Features
1. **Schema Validation Dashboard**: Real-time validation results
2. **Rich Results Preview**: Preview how schemas appear in search
3. **Schema Templates**: Pre-built templates for common use cases
4. **Bulk Schema Management**: Batch operations for large sites
5. **Schema Analytics**: Track rich results performance

### Integration Opportunities
1. **WooCommerce**: Product schema integration
2. **Events Calendar**: Event schema support
3. **Review Plugins**: Review/rating schema
4. **Recipe Plugins**: Recipe schema integration
5. **Local SEO**: LocalBusiness schema enhancement