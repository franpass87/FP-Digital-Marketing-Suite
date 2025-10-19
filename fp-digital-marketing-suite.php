<?php
/**
 * Plugin Name: FP Digital Marketing Suite
 * Plugin URI: https://francescopasseri.com
 * Description: Automates marketing performance reporting, anomaly detection, and multi-channel alerts for private WordPress operations.
 * Version: 0.1.1
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
use FP\DMS\Admin\Support\Ajax\TestConnector;
use FP\DMS\Cli\Commands;
use FP\DMS\ConnectionWizardIntegration;
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

const FP_DMS_VERSION = '0.1.1';
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

// Inizializzazione delle classi principali
Cron::bootstrap();
Mailer::bootstrap();
Routes::register();
add_action('fpdms_cron_tick', [Queue::class, 'tick']);
add_action('fpdms/health/force_tick', [Queue::class, 'tick']);
Commands::register();

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

    Security::registerAdminNotice();
    TestConnector::register();
    Menu::init();
    ConnectionWizardIntegration::init();
}
add_action('init', 'fp_dms_bootstrap');
