<?php
/**
 * Plugin Name: FP Digital Marketing Suite
 * Plugin URI: https://francescopasseri.com
 * Description: Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.
 * Version: 0.9.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-dms
 * Domain Path: /languages
 * License: GPLv2 or later
 */

declare(strict_types=1);

use FP\DMS\Admin\Menu;
use FP\DMS\Admin\Ajax\TemplatePreviewHandler;
use FP\DMS\Admin\Ajax\ReportReviewHandler;
use FP\DMS\Admin\Support\Ajax\TestConnector;
use FP\DMS\Cli\Commands;
use FP\DMS\Http\Routes;
use FP\DMS\Infra\Activator;
use FP\DMS\Infra\Cron;
use FP\DMS\Infra\Deactivator;
use FP\DMS\Infra\Mailer;
use FP\DMS\Infra\Queue;
use FP\DMS\Support\Security;

$composer = __DIR__ . '/vendor/autoload.php';
if (file_exists($composer)) {
    require_once $composer;
}

if (! defined('ABSPATH')) {
    exit;
}

const FP_DMS_VERSION = '0.9.1';
const FP_DMS_PLUGIN_FILE = __FILE__;
const FP_DMS_PLUGIN_DIR = __DIR__;

// Autoloader personalizzato semplice per le classi principali
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'FP\\DMS\\') !== 0) {
        return;
    }

    $relative = substr($class, strlen('FP\\DMS\\'));
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $path = __DIR__ . '/src/' . $relative . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

// Inizializzazione condizionale per ottimizzare le performance su hosting condivisi
// Cron e Mailer vengono caricati solo quando necessario (wp_doing_cron o WP-CLI)
if (wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
    Cron::bootstrap();
    Mailer::bootstrap();
    add_action('fpdms_cron_tick', [Queue::class, 'tick']);
    add_action('fpdms/health/force_tick', [Queue::class, 'tick']);
}

// Routes vengono registrate solo per richieste admin o REST
if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
    Routes::register();
}

// WP-CLI commands sempre disponibili se WP-CLI è attivo
if (defined('WP_CLI') && WP_CLI) {
    Commands::register();
}

function fp_dms_load_textdomain(): void
{
    load_plugin_textdomain('fp-dms', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'fp_dms_load_textdomain');

function fp_dms_activate(): void
{
    Activator::activate();
}
register_activation_hook(__FILE__, 'fp_dms_activate');

function fp_dms_deactivate(): void
{
    Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'fp_dms_deactivate');

function fp_dms_bootstrap(): void
{
    if (! is_admin()) {
        return;
    }

    // Security notice sempre registrato
    Security::registerAdminNotice();
    
    // Ajax handlers solo se DOING_AJAX
    if (defined('DOING_AJAX') && DOING_AJAX) {
        TestConnector::register();
        TemplatePreviewHandler::register();
        ReportReviewHandler::register();
    }
}
add_action('init', 'fp_dms_bootstrap');

// Menu caricato su hook admin_menu (lazy loading)
function fp_dms_admin_menu(): void
{
    if (! is_admin()) {
        return;
    }
    
    Menu::init();
}
add_action('admin_menu', 'fp_dms_admin_menu', 5);
