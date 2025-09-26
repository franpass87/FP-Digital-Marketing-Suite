<?php
/**
 * Admin Menu Manager - Centralized menu registration and organization.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

/**
 * Coordinates menu registration while delegating structure management to MenuRegistry.
 */
class MenuManager
{
    /**
     * Indicates whether the menu manager has been initialized.
     */
    private static bool $initialized = false;

    /**
     * Singleton instance reference used by static helpers.
     */
    private static ?MenuManager $instance = null;

    /**
     * Mirror the slugs from MenuRegistry for backwards compatibility.
     */
    private const MAIN_MENU_SLUG = MenuRegistry::MAIN_MENU_SLUG;
    private const WIZARD_MENU_SLUG = MenuRegistry::WIZARD_MENU_SLUG;

    /**
     * Pre-instantiated admin modules keyed by short class name.
     *
     * @var array<string, object>
     */
    private array $admin_instances;

    private MenuRegistry $registry;

    public function __construct(array $admin_instances = [], ?MenuRegistry $registry = null)
    {
        $this->admin_instances = $admin_instances;
        $this->registry = $registry ?? new MenuRegistry();
        self::$instance = $this;
    }

    public function init(): void
    {
        if (self::$initialized) {
            return;
        }

        add_action('admin_menu', [$this, 'register_menus'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'show_rationalization_notice']);
        add_action('wp_ajax_fp_dms_dismiss_menu_notice', [$this, 'handle_dismiss_notice']);

        self::$initialized = true;
    }

    public static function is_initialized(): bool
    {
        return self::$initialized;
    }

    public function register_menus(): void
    {
        $main_menu = $this->registry->get_main_menu();

        add_menu_page(
            $main_menu['page_title'],
            $main_menu['menu_title'],
            $main_menu['capability'],
            $main_menu['menu_slug'],
            $this->resolve_callback($main_menu['callback']),
            $main_menu['icon'],
            $main_menu['position']
        );

        foreach ($this->registry->group_submenus() as $menus) {
            foreach ($menus as $menu) {
                add_submenu_page(
                    $menu['parent_slug'],
                    $menu['page_title'],
                    $menu['menu_title'],
                    $menu['capability'],
                    $menu['menu_slug'],
                    $this->resolve_callback($menu['callback'])
                );
            }
        }
    }

    /**
     * Attempts to resolve a callback defined as "Class::method" while honouring provided instances.
     *
     * @param string $callback
     * @return callable
     */
    private function resolve_callback(string $callback): callable
    {
        if (strpos($callback, '::') !== false) {
            [$class, $method] = explode('::', $callback, 2);

            if (isset($this->admin_instances[$class]) && method_exists($this->admin_instances[$class], $method)) {
                return [$this->admin_instances[$class], $method];
            }

            $full_class = "\\\FP\\DigitalMarketing\\Admin\\{$class}";

            if (class_exists($full_class)) {
                try {
                    $instance = new $full_class();
                    if (method_exists($instance, $method)) {
                        $this->admin_instances[$class] = $instance;
                        return [$instance, $method];
                    }
                } catch (\Throwable $exception) {
                    if (function_exists('error_log')) {
                        error_log(
                            sprintf(
                                'FP Digital Marketing MenuManager: Failed to instantiate %s - %s',
                                $class,
                                $exception->getMessage()
                            )
                        );
                    }
                }
            }

            return [$this, 'render_admin_unavailable_page'];
        }

        return [$this, 'render_placeholder_page'];
    }

    public function render_admin_unavailable_page(): void
    {
        $slug = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $page_name = $this->get_page_name_from_slug($slug);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($page_name) . '</h1>';

        echo '<div class="notice notice-info"><p>';
        echo '<strong>' . esc_html__('Funzionalità in caricamento', 'fp-digital-marketing') . '</strong><br>';
        echo esc_html__(
            'Questa funzionalità è attualmente in fase di inizializzazione. Si prega di aggiornare la pagina o tornare più tardi.',
            'fp-digital-marketing'
        );
        echo '</p></div>';

        $this->render_navigation_cards();

        if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>' . esc_html__('Info Debug (solo amministratori):', 'fp-digital-marketing') . '</strong><br>';
            /* translators: %s is the current admin page slug. */
            echo esc_html(sprintf(__('Modulo admin per la pagina "%s" non disponibile. Verifica log degli errori per dettagli.', 'fp-digital-marketing'), $slug));
            echo '</p></div>';
        }

        echo '</div>';
    }

    public function render_placeholder_page(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('FP Digital Marketing Suite', 'fp-digital-marketing') . '</h1>';
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . esc_html__('Pagina in configurazione', 'fp-digital-marketing') . '</strong><br>';
        echo esc_html__(
            'Questa pagina admin non è ancora completamente configurata. Se vedi questo messaggio, potrebbe esserci un problema con il caricamento del modulo amministrativo.',
            'fp-digital-marketing'
        );
        echo '</p></div>';

        if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>' . esc_html__('Informazioni di debug (solo per amministratori):', 'fp-digital-marketing') . '</strong><br>';
            echo esc_html__(
                'Questa pagina placeholder viene mostrata quando il callback del menu non può essere risolto. Verifica che tutte le classi admin siano caricate correttamente.',
                'fp-digital-marketing'
            );
            echo '</p></div>';
        }

        echo '<div style="margin-top: 20px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::MAIN_MENU_SLUG)) . '" class="button button-primary">';
        echo esc_html__('Vai alla Dashboard', 'fp-digital-marketing');
        echo '</a> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp-digital-marketing-settings')) . '" class="button">';
        echo esc_html__('Impostazioni Plugin', 'fp-digital-marketing');
        echo '</a>';
        echo '</div>';

        echo '</div>';
    }

    private function render_navigation_cards(): void
    {
        echo '<div class="fp-admin-basic-content">';
        echo '<h2>' . esc_html__('Azioni disponibili', 'fp-digital-marketing') . '</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">';

        $cards = [
            [
                'title' => __('📊 Dashboard Principale', 'fp-digital-marketing'),
                'description' => __('Visualizza panoramica completa delle metriche e KPI.', 'fp-digital-marketing'),
                'url' => admin_url('admin.php?page=' . self::MAIN_MENU_SLUG),
                'label' => __('Vai alla Dashboard', 'fp-digital-marketing'),
                'primary' => true,
            ],
            [
                'title' => __('⚙️ Configurazione', 'fp-digital-marketing'),
                'description' => __('Configura le impostazioni del plugin e le connessioni.', 'fp-digital-marketing'),
                'url' => admin_url('admin.php?page=fp-digital-marketing-settings'),
                'label' => __('Impostazioni', 'fp-digital-marketing'),
                'primary' => false,
            ],
            [
                'title' => __('🛠️ Setup Guidato', 'fp-digital-marketing'),
                'description' => __('Configura il plugin passo-passo con la procedura guidata.', 'fp-digital-marketing'),
                'url' => admin_url('admin.php?page=' . self::WIZARD_MENU_SLUG),
                'label' => __('Avvia Setup', 'fp-digital-marketing'),
                'primary' => false,
            ],
        ];

        foreach ($cards as $card) {
            $button_class = $card['primary'] ? 'button button-primary' : 'button';
            echo '<div style="border: 1px solid #ccd0d4; padding: 20px; background: #fff;">';
            echo '<h3>' . esc_html($card['title']) . '</h3>';
            echo '<p>' . esc_html($card['description']) . '</p>';
            echo '<a href="' . esc_url($card['url']) . '" class="' . esc_attr($button_class) . '">';
            echo esc_html($card['label']);
            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function get_page_name_from_slug(string $slug): string
    {
        $page_names = [
            self::MAIN_MENU_SLUG => __('Dashboard', 'fp-digital-marketing'),
            'fp-digital-marketing-reports' => __('Reports & Analytics', 'fp-digital-marketing'),
            'fp-conversion-events' => __('Eventi Conversione', 'fp-digital-marketing'),
            'fp-utm-campaign-manager' => __('Gestione Campagne UTM', 'fp-digital-marketing'),
            'fp-digital-marketing-funnel-analysis' => __('Funnel Analysis', 'fp-digital-marketing'),
            'fp-audience-segments' => __('Segmentazione Audience', 'fp-digital-marketing'),
            'fp-digital-marketing-alerts' => __('Alert e Notifiche', 'fp-digital-marketing'),
            'fp-digital-marketing-anomalies' => __('Rilevazione Anomalie', 'fp-digital-marketing'),
            'fp-digital-marketing-cache-performance' => __('Cache Performance', 'fp-digital-marketing'),
            'fp-digital-marketing-security' => __('Security Settings', 'fp-digital-marketing'),
            'fp-platform-connections' => __('Connessioni Piattaforme', 'fp-digital-marketing'),
            'fp-digital-marketing-settings' => __('FP Digital Marketing Settings', 'fp-digital-marketing'),
            self::WIZARD_MENU_SLUG => __('Setup Wizard', 'fp-digital-marketing'),
        ];

        return $page_names[$slug] ?? __('FP Digital Marketing', 'fp-digital-marketing');
    }

    public function enqueue_admin_assets(): void
    {
        wp_enqueue_style(
            'fp-dms-admin-menu-rationalized',
            FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/css/admin-menu-rationalized.css',
            [],
            FP_DIGITAL_MARKETING_VERSION
        );
    }

    public function get_menu_structure(): array
    {
        return [
            'main' => $this->registry->get_main_menu(),
            'submenus' => $this->registry->get_submenus(),
        ];
    }

    public function show_rationalization_notice(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'fp-digital-marketing') === false) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $dismissed = get_user_meta($user_id, 'fp_dms_menu_rationalization_notice_dismissed', true);

        if ($dismissed) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible" data-notice="fp-dms-menu-rationalization">';
        echo '<p><strong>' . esc_html__('FP Digital Marketing Suite', 'fp-digital-marketing') . '</strong> - ';
        echo esc_html__(
            'The admin menu has been rationalized and reorganized for better user experience. All functionality remains accessible through the new logical grouping.',
            'fp-digital-marketing'
        );
        echo '</p>';
        echo '</div>';

        $payload = [
            'action' => 'fp_dms_dismiss_menu_notice',
            'nonce' => wp_create_nonce('fp_dms_dismiss_notice'),
        ];

        $encoded_payload = wp_json_encode($payload);

        if ($encoded_payload !== false) {
            $selector = esc_js('fp-dms-menu-rationalization');
            $script = sprintf(
                'jQuery(function($){$(document).on("click","[data-notice=\'%s\'] .notice-dismiss",function(){$.post(ajaxurl,%s);});});',
                $selector,
                $encoded_payload
            );

            echo '<script>' . $script . '</script>';
        }
    }

    public function handle_dismiss_notice(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'fp_dms_dismiss_notice')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $user_id = get_current_user_id();
        update_user_meta($user_id, 'fp_dms_menu_rationalization_notice_dismissed', true);

        wp_send_json_success();
    }

    public static function disable_wizard_menu_entry(string $status = 'completed'): void
    {
        $instance = self::$instance;

        if (!$instance instanceof self) {
            $instance = new self();
        }

        $instance->registry->disable_wizard_menu($status);
        self::remove_wizard_from_global_submenu();
    }

    public static function enable_wizard_menu_entry(): void
    {
        $instance = self::$instance;

        if (!$instance instanceof self) {
            $instance = new self();
        }

        $instance->registry->enable_wizard_menu();
    }

    private static function remove_wizard_from_global_submenu(): void
    {
        global $submenu;

        if (!isset($submenu[self::MAIN_MENU_SLUG]) || !is_array($submenu[self::MAIN_MENU_SLUG])) {
            return;
        }

        foreach ($submenu[self::MAIN_MENU_SLUG] as $index => $menu) {
            if (isset($menu[2]) && $menu[2] === self::WIZARD_MENU_SLUG) {
                unset($submenu[self::MAIN_MENU_SLUG][$index]);
            }
        }

        $submenu[self::MAIN_MENU_SLUG] = array_values($submenu[self::MAIN_MENU_SLUG]);
    }

    public function remove_legacy_menus(): void
    {
        $legacy_slugs = [
            'fp-digital-marketing-reports',
            'fp-digital-marketing-alerts',
            'fp-digital-marketing-anomalies',
            'fp-digital-marketing-utm-campaigns',
            'fp-digital-marketing-conversion-events',
            'fp-conversion-events',
            'fp-digital-marketing-segments-old',
            'fp-digital-marketing-cache',
            'fp-digital-marketing-security-old',
        ];

        foreach ($legacy_slugs as $slug) {
            remove_submenu_page(self::MAIN_MENU_SLUG, $slug);
        }
    }
}
