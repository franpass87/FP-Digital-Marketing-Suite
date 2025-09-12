# White-Label Support - FP Digital Marketing Suite

This document provides comprehensive guidelines for agencies and developers who want to white-label FP Digital Marketing Suite for their clients.

## Overview

FP Digital Marketing Suite includes built-in white-label capabilities that allow agencies to:
- Rebrand the plugin with their own branding
- Customize the user interface and experience
- Add their own support information and documentation
- Hide or modify WordPress admin elements
- Create custom client onboarding experiences

## White-Label Features

### Core Branding Options

**Plugin Identity:**
- Custom plugin name and description
- Agency logo and branding colors
- Custom admin menu labels
- Personalized dashboard widgets
- Branded email templates

**Client-Facing Elements:**
- Custom login pages
- Branded reports and exports
- Agency contact information
- Custom help documentation
- Personalized support links

### Configuration Options

**Administrative Settings:**
- Hide WordPress admin elements
- Custom capability management
- Agency-specific settings panels
- White-label plugin updates
- Custom dashboard layouts

## Implementation Guide

### 1. Basic Branding Configuration

Create a white-label configuration file in your WordPress theme or as a mu-plugin:

```php
<?php
/**
 * White-label configuration for FP Digital Marketing Suite
 * 
 * Place this file in wp-content/mu-plugins/ or your theme's functions.php
 */

// Define white-label constants
define('FP_DMS_WHITE_LABEL', true);
define('FP_DMS_AGENCY_NAME', 'Your Agency Name');
define('FP_DMS_AGENCY_LOGO', 'https://youragency.com/logo.png');
define('FP_DMS_AGENCY_URL', 'https://youragency.com');
define('FP_DMS_SUPPORT_EMAIL', 'support@youragency.com');
define('FP_DMS_SUPPORT_URL', 'https://youragency.com/support');

/**
 * White-label filters and hooks
 */
add_filter('fp_dms_plugin_name', function($name) {
    return 'Your Agency Marketing Suite';
});

add_filter('fp_dms_plugin_description', function($description) {
    return 'Complete digital marketing toolkit powered by Your Agency Name';
});

add_filter('fp_dms_admin_menu_title', function($title) {
    return 'Agency Dashboard';
});

add_filter('fp_dms_dashboard_logo', function($logo_url) {
    return FP_DMS_AGENCY_LOGO;
});

// Custom branding colors
add_filter('fp_dms_brand_colors', function($colors) {
    return [
        'primary' => '#your-primary-color',
        'secondary' => '#your-secondary-color',
        'accent' => '#your-accent-color'
    ];
});
```

### 2. Advanced Branding Options

#### Custom CSS and Styling

```php
/**
 * Add custom CSS for white-label branding
 */
add_action('admin_head', function() {
    if (fp_dms_is_plugin_page()) {
        ?>
        <style>
        :root {
            --fp-dms-primary: <?php echo get_option('fp_dms_brand_primary', '#0073aa'); ?>;
            --fp-dms-secondary: <?php echo get_option('fp_dms_brand_secondary', '#005177'); ?>;
            --fp-dms-accent: <?php echo get_option('fp_dms_brand_accent', '#00a0d2'); ?>;
        }
        
        .fp-dms-header {
            background: var(--fp-dms-primary);
        }
        
        .fp-dms-logo {
            content: url('<?php echo FP_DMS_AGENCY_LOGO; ?>');
            max-height: 40px;
        }
        
        .fp-dms-button-primary {
            background-color: var(--fp-dms-primary);
            border-color: var(--fp-dms-primary);
        }
        
        .fp-dms-nav-tab-active {
            border-top-color: var(--fp-dms-accent);
        }
        </style>
        <?php
    }
});
```

#### Custom JavaScript

```php
/**
 * Add custom JavaScript for enhanced branding
 */
add_action('admin_footer', function() {
    if (fp_dms_is_plugin_page()) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Replace plugin references
            $('.fp-dms-plugin-name').text('<?php echo FP_DMS_AGENCY_NAME; ?>');
            
            // Update support links
            $('.fp-dms-support-link').attr('href', '<?php echo FP_DMS_SUPPORT_URL; ?>');
            
            // Custom dashboard widgets
            if (typeof fpDmsWhiteLabel !== 'undefined') {
                fpDmsWhiteLabel.init({
                    agencyName: '<?php echo FP_DMS_AGENCY_NAME; ?>',
                    supportEmail: '<?php echo FP_DMS_SUPPORT_EMAIL; ?>',
                    branding: <?php echo json_encode(get_option('fp_dms_brand_colors', [])); ?>
                });
            }
        });
        </script>
        <?php
    }
});
```

### 3. Client Portal Customization

#### Custom Login Page

```php
/**
 * Customize WordPress login page for clients
 */
add_action('login_enqueue_scripts', function() {
    ?>
    <style>
    body.login {
        background: linear-gradient(135deg, #your-color-1, #your-color-2);
    }
    
    .login h1 a {
        background-image: url('<?php echo FP_DMS_AGENCY_LOGO; ?>');
        background-size: contain;
        width: 200px;
        height: 80px;
    }
    
    .login form {
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .wp-core-ui .button-primary {
        background: var(--fp-dms-primary);
        border-color: var(--fp-dms-primary);
    }
    </style>
    <?php
});

// Custom login logo URL
add_filter('login_headerurl', function() {
    return FP_DMS_AGENCY_URL;
});

// Custom login logo title
add_filter('login_headertext', function() {
    return FP_DMS_AGENCY_NAME . ' - Client Login';
});
```

#### Custom Dashboard Welcome

```php
/**
 * Add custom welcome message for clients
 */
add_action('wp_dashboard_setup', function() {
    if (current_user_can('fp_dms_view_analytics')) {
        wp_add_dashboard_widget(
            'fp_dms_agency_welcome',
            'Welcome to ' . FP_DMS_AGENCY_NAME,
            'fp_dms_agency_welcome_widget'
        );
    }
});

function fp_dms_agency_welcome_widget() {
    ?>
    <div class="fp-dms-welcome-widget">
        <div class="agency-branding">
            <img src="<?php echo FP_DMS_AGENCY_LOGO; ?>" alt="<?php echo FP_DMS_AGENCY_NAME; ?>" style="max-height: 60px;">
            <h3>Welcome to Your Marketing Dashboard</h3>
        </div>
        
        <p>Your digital marketing performance is powered by <?php echo FP_DMS_AGENCY_NAME; ?>. 
           Access your analytics, SEO insights, and marketing automation tools below.</p>
        
        <div class="agency-contact">
            <p><strong>Need Help?</strong></p>
            <p>Email: <a href="mailto:<?php echo FP_DMS_SUPPORT_EMAIL; ?>"><?php echo FP_DMS_SUPPORT_EMAIL; ?></a></p>
            <p>Support: <a href="<?php echo FP_DMS_SUPPORT_URL; ?>" target="_blank">Visit Support Center</a></p>
        </div>
    </div>
    
    <style>
    .fp-dms-welcome-widget .agency-branding {
        text-align: center;
        margin-bottom: 15px;
    }
    .fp-dms-welcome-widget .agency-contact {
        background: #f9f9f9;
        padding: 10px;
        border-radius: 4px;
        margin-top: 15px;
    }
    </style>
    <?php
}
```

### 4. Report and Export Branding

#### Custom PDF Report Headers

```php
/**
 * Add agency branding to PDF reports
 */
add_filter('fp_dms_pdf_report_header', function($header_html, $client_id) {
    ob_start();
    ?>
    <div class="report-header" style="border-bottom: 2px solid <?php echo get_option('fp_dms_brand_primary'); ?>; padding-bottom: 20px; margin-bottom: 30px;">
        <div style="float: left;">
            <img src="<?php echo FP_DMS_AGENCY_LOGO; ?>" style="max-height: 50px;">
        </div>
        <div style="float: right; text-align: right;">
            <h1 style="color: <?php echo get_option('fp_dms_brand_primary'); ?>; margin: 0;">
                <?php echo FP_DMS_AGENCY_NAME; ?>
            </h1>
            <p style="margin: 5px 0 0 0; color: #666;">Digital Marketing Report</p>
        </div>
        <div style="clear: both;"></div>
    </div>
    <?php
    return ob_get_clean();
}, 10, 2);

/**
 * Add agency contact info to PDF footer
 */
add_filter('fp_dms_pdf_report_footer', function($footer_html) {
    ob_start();
    ?>
    <div class="report-footer" style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 30px; text-align: center; color: #666; font-size: 12px;">
        <p>This report was generated by <?php echo FP_DMS_AGENCY_NAME; ?></p>
        <p>For questions about this report, contact <?php echo FP_DMS_SUPPORT_EMAIL; ?> or visit <?php echo FP_DMS_SUPPORT_URL; ?></p>
    </div>
    <?php
    return ob_get_clean();
});
```

#### Custom Email Templates

```php
/**
 * Customize email templates with agency branding
 */
add_filter('fp_dms_email_template_header', function($header) {
    return '
    <table style="width: 100%; background: ' . get_option('fp_dms_brand_primary') . '; padding: 20px;">
        <tr>
            <td>
                <img src="' . FP_DMS_AGENCY_LOGO . '" style="max-height: 40px;">
            </td>
            <td style="text-align: right; color: white; font-size: 18px;">
                ' . FP_DMS_AGENCY_NAME . '
            </td>
        </tr>
    </table>';
});

add_filter('fp_dms_email_template_footer', function($footer) {
    return '
    <table style="width: 100%; background: #f5f5f5; padding: 20px; margin-top: 30px;">
        <tr>
            <td style="text-align: center; color: #666; font-size: 12px;">
                <p>You are receiving this email because you are a client of ' . FP_DMS_AGENCY_NAME . '</p>
                <p>Questions? Contact us at <a href="mailto:' . FP_DMS_SUPPORT_EMAIL . '">' . FP_DMS_SUPPORT_EMAIL . '</a></p>
                <p><a href="' . FP_DMS_AGENCY_URL . '">' . FP_DMS_AGENCY_URL . '</a></p>
            </td>
        </tr>
    </table>';
});
```

### 5. Admin Interface Customization

#### Hide WordPress Branding

```php
/**
 * Remove WordPress branding elements for clean client experience
 */
add_action('admin_init', function() {
    if (current_user_can('fp_dms_client_access')) {
        // Remove WordPress logo from admin bar
        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('wp-logo');
        });
        
        // Custom admin footer
        add_filter('admin_footer_text', function() {
            return 'Powered by <a href="' . FP_DMS_AGENCY_URL . '">' . FP_DMS_AGENCY_NAME . '</a>';
        });
        
        // Remove WordPress version from footer
        add_filter('update_footer', function() {
            return '';
        });
    }
});
```

#### Custom Admin Menu

```php
/**
 * Customize admin menu for white-label experience
 */
add_action('admin_menu', function() {
    if (defined('FP_DMS_WHITE_LABEL') && FP_DMS_WHITE_LABEL) {
        // Change main menu title
        global $menu;
        foreach ($menu as $key => $menu_item) {
            if ($menu_item[2] === 'fp-digital-marketing') {
                $menu[$key][0] = FP_DMS_AGENCY_NAME . ' Dashboard';
                break;
            }
        }
        
        // Add agency-specific menu items
        add_menu_page(
            'Agency Support',
            'Get Support',
            'read',
            'fp-dms-agency-support',
            'fp_dms_agency_support_page',
            'dashicons-sos',
            100
        );
    }
}, 999);

function fp_dms_agency_support_page() {
    ?>
    <div class="wrap">
        <h1><?php echo FP_DMS_AGENCY_NAME; ?> Support</h1>
        
        <div class="card">
            <h2>Contact Our Support Team</h2>
            <p>Our team is here to help you get the most out of your digital marketing platform.</p>
            
            <table class="form-table">
                <tr>
                    <th>Email Support</th>
                    <td><a href="mailto:<?php echo FP_DMS_SUPPORT_EMAIL; ?>"><?php echo FP_DMS_SUPPORT_EMAIL; ?></a></td>
                </tr>
                <tr>
                    <th>Knowledge Base</th>
                    <td><a href="<?php echo FP_DMS_SUPPORT_URL; ?>" target="_blank">Visit Support Center</a></td>
                </tr>
                <tr>
                    <th>Agency Website</th>
                    <td><a href="<?php echo FP_DMS_AGENCY_URL; ?>" target="_blank"><?php echo FP_DMS_AGENCY_URL; ?></a></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Quick Links</h2>
            <ul>
                <li><a href="admin.php?page=fp-dms-analytics">View Analytics Dashboard</a></li>
                <li><a href="admin.php?page=fp-dms-seo">SEO Tools</a></li>
                <li><a href="admin.php?page=fp-dms-settings">Settings</a></li>
            </ul>
        </div>
    </div>
    <?php
}
```

### 6. Client Onboarding Customization

#### Custom Setup Wizard

```php
/**
 * Customize the setup wizard for agency branding
 */
add_filter('fp_dms_setup_wizard_steps', function($steps) {
    // Add agency welcome step
    $agency_step = [
        'welcome' => [
            'title' => 'Welcome to ' . FP_DMS_AGENCY_NAME,
            'description' => 'Let\'s set up your digital marketing dashboard',
            'callback' => 'fp_dms_agency_welcome_step'
        ]
    ];
    
    return array_merge($agency_step, $steps);
});

function fp_dms_agency_welcome_step() {
    ?>
    <div class="fp-dms-setup-step">
        <div class="step-header">
            <img src="<?php echo FP_DMS_AGENCY_LOGO; ?>" alt="<?php echo FP_DMS_AGENCY_NAME; ?>" style="max-height: 60px; margin-bottom: 20px;">
            <h2>Welcome to Your Digital Marketing Command Center</h2>
        </div>
        
        <div class="step-content">
            <p>Thank you for choosing <?php echo FP_DMS_AGENCY_NAME; ?> for your digital marketing needs. 
               This powerful platform will help you track, analyze, and optimize your online presence.</p>
            
            <div class="feature-highlights">
                <div class="feature">
                    <h4>📊 Advanced Analytics</h4>
                    <p>Track your website performance with Google Analytics 4, Google Ads, and Search Console integration.</p>
                </div>
                <div class="feature">
                    <h4>🚀 SEO Optimization</h4>
                    <p>Improve your search rankings with our comprehensive SEO tools and analysis.</p>
                </div>
                <div class="feature">
                    <h4>📈 Marketing Automation</h4>
                    <p>Automate your marketing campaigns and track conversions across all channels.</p>
                </div>
            </div>
            
            <div class="agency-contact">
                <h4>Need Help Getting Started?</h4>
                <p>Our team is here to help you succeed. Contact us anytime:</p>
                <p>📧 <a href="mailto:<?php echo FP_DMS_SUPPORT_EMAIL; ?>"><?php echo FP_DMS_SUPPORT_EMAIL; ?></a></p>
                <p>🌐 <a href="<?php echo FP_DMS_SUPPORT_URL; ?>" target="_blank">Visit our support center</a></p>
            </div>
        </div>
    </div>
    
    <style>
    .fp-dms-setup-step .step-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .feature-highlights {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .feature {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid <?php echo get_option('fp_dms_brand_primary', '#0073aa'); ?>;
    }
    
    .agency-contact {
        background: #e7f3ff;
        padding: 20px;
        border-radius: 8px;
        margin-top: 30px;
    }
    </style>
    <?php
}
```

### 7. White-Label Plugin Updates

#### Custom Update Server

```php
/**
 * Handle white-label plugin updates
 */
class FP_DMS_White_Label_Updates {
    
    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }
    
    public function check_for_updates($transient) {
        if (!defined('FP_DMS_WHITE_LABEL') || !FP_DMS_WHITE_LABEL) {
            return $transient;
        }
        
        // Check your agency's update server
        $plugin_slug = plugin_basename(FP_DMS_PLUGIN_FILE);
        $version_info = $this->get_version_info();
        
        if (version_compare(FP_DMS_VERSION, $version_info['new_version'], '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'new_version' => $version_info['new_version'],
                'url' => FP_DMS_AGENCY_URL,
                'package' => $version_info['download_url']
            ];
        }
        
        return $transient;
    }
    
    private function get_version_info() {
        // Call your agency's update server
        $response = wp_remote_get(FP_DMS_AGENCY_URL . '/wp-json/agency/v1/plugin-updates/fp-dms');
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        }
        
        return false;
    }
}

new FP_DMS_White_Label_Updates();
```

## Configuration Examples

### Complete Agency Configuration

```php
<?php
/**
 * Complete white-label configuration example
 * Save as: wp-content/mu-plugins/fp-dms-agency-branding.php
 */

// Agency Information
define('FP_DMS_WHITE_LABEL', true);
define('FP_DMS_AGENCY_NAME', 'Digital Growth Partners');
define('FP_DMS_AGENCY_LOGO', 'https://digitalgrowthpartners.com/assets/logo-white.svg');
define('FP_DMS_AGENCY_URL', 'https://digitalgrowthpartners.com');
define('FP_DMS_SUPPORT_EMAIL', 'support@digitalgrowthpartners.com');
define('FP_DMS_SUPPORT_URL', 'https://digitalgrowthpartners.com/support');

// Brand Colors
add_filter('fp_dms_brand_colors', function() {
    return [
        'primary' => '#2563eb',
        'secondary' => '#1e40af',
        'accent' => '#3b82f6',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'error' => '#ef4444'
    ];
});

// Plugin Branding
add_filter('fp_dms_plugin_name', function() {
    return 'DGP Marketing Suite';
});

add_filter('fp_dms_plugin_description', function() {
    return 'Professional digital marketing platform by Digital Growth Partners';
});

// Menu Customization
add_filter('fp_dms_admin_menu_title', function() {
    return 'Marketing Suite';
});

// Dashboard Customization
add_filter('fp_dms_dashboard_title', function() {
    return 'Digital Growth Dashboard';
});

// Email Customization
add_filter('fp_dms_email_from_name', function() {
    return 'Digital Growth Partners';
});

add_filter('fp_dms_email_from_email', function() {
    return 'noreply@digitalgrowthpartners.com';
});

// Custom CSS
add_action('admin_head', function() {
    if (strpos(get_current_screen()->id, 'fp-dms') !== false) {
        echo '<link rel="stylesheet" href="https://digitalgrowthpartners.com/assets/fp-dms-custom.css">';
    }
});

// Hide specific menu items for clients
add_action('admin_init', function() {
    if (current_user_can('fp_dms_client_access') && !current_user_can('manage_options')) {
        remove_menu_page('tools.php');
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('users.php');
    }
});
```

## Best Practices

### Branding Guidelines

1. **Consistent Visual Identity**
   - Use your agency's color palette throughout
   - Maintain consistent typography and spacing
   - Apply your logo appropriately in all contexts

2. **Professional Communication**
   - Update all user-facing text with your agency's tone
   - Provide clear contact information
   - Include helpful links to your resources

3. **Client Experience**
   - Simplify the interface for clients
   - Hide unnecessary WordPress complexity
   - Provide clear guidance and support

### Technical Considerations

1. **Update Management**
   - Implement proper update mechanisms
   - Test all customizations after updates
   - Maintain version compatibility

2. **Performance**
   - Optimize custom CSS and JavaScript
   - Use efficient loading strategies
   - Monitor impact on page performance

3. **Security**
   - Validate all custom inputs
   - Use proper WordPress APIs
   - Implement security best practices

## Support and Maintenance

### Documentation Requirements

Create comprehensive documentation for your white-labeled solution:

1. **Client Documentation**
   - How to use the dashboard
   - Understanding reports and analytics
   - Troubleshooting common issues

2. **Technical Documentation**
   - Installation and setup procedures
   - Customization guidelines
   - Update and maintenance processes

### Ongoing Support

Establish clear support procedures:

1. **Support Channels**
   - Email support system
   - Knowledge base
   - Video tutorials
   - Live chat (optional)

2. **Maintenance Schedule**
   - Regular plugin updates
   - Security monitoring
   - Performance optimization
   - Client check-ins

## Conclusion

White-labeling FP Digital Marketing Suite allows agencies to provide a professional, branded experience for their clients while maintaining the powerful functionality of the platform. By following these guidelines and examples, you can create a seamless, professional solution that reinforces your agency's brand and provides exceptional value to your clients.

For additional white-label support and customization services, contact the FP Digital Marketing Suite development team.