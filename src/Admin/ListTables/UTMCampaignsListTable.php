<?php
/**
 * List table for UTM campaigns with bulk actions and filters.
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin\ListTables;

use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Models\UTMCampaign;
use WP_List_Table;

use function absint;
use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function number_format_i18n;
use function remove_query_arg;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function selected;
use function submit_button;
use function wp_date;
use function wp_nonce_url;
use function wp_verify_nonce;
use function _n;

if (! class_exists(WP_List_Table::class)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Provides a standardized wp-admin list table for UTM campaigns.
 */
class UTMCampaignsListTable extends WP_List_Table
{
    /**
     * Map of status slugs to their translated labels.
     *
     * @var array<string, string>
     */
    private array $status_labels = [
        'active' => '',
        'paused' => '',
        'completed' => '',
    ];

    /**
     * Notices collected during bulk/row actions.
     *
     * @var list<array{message:string,type:string}>
     */
    private array $notices = [];

    /**
     * Currently active status filter.
     */
    private string $status_filter = '';

    /**
     * Search term applied to the table.
     */
    private string $search_term = '';

    /**
     * Constructor.
     *
     * @param array<string,mixed> $args Optional arguments.
     */
    public function __construct(array $args = [])
    {
        parent::__construct(
            [
                'plural' => 'utm_campaigns',
                'singular' => 'utm_campaign',
                'screen' => $args['screen'] ?? null,
                'ajax' => false,
            ]
        );

        $this->status_labels = [
            'active' => esc_html__('Attiva', 'fp-digital-marketing'),
            'paused' => esc_html__('In pausa', 'fp-digital-marketing'),
            'completed' => esc_html__('Completata', 'fp-digital-marketing'),
        ];

        $this->status_filter = isset($args['status']) ? sanitize_key((string) $args['status']) : '';
        if (! isset($this->status_labels[$this->status_filter])) {
            $this->status_filter = '';
        }

        $this->search_term = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';
    }

    /**
     * Prepare table items including bulk action handling.
     */
    public function prepare_items(): void
    {
        $this->process_bulk_action();

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable, 'campaign_name'];

        $per_page     = $this->get_items_per_page('fp_dms_campaigns_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $filters = [];
        if ($this->status_filter !== '') {
            $filters['status'] = $this->status_filter;
        }
        if ($this->search_term !== '') {
            $filters['search'] = $this->search_term;
        }

        $this->items = array_map(
            static function (UTMCampaign $campaign): array {
                return $campaign->to_array();
            },
            UTMCampaign::get_campaigns($filters, $per_page, $offset)
        );

        $total_items = UTMCampaign::get_campaigns_count($filters);

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page'    => $per_page,
            ]
        );
    }

    /**
     * Retrieve column definitions.
     *
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'campaign_name' => esc_html__('Nome Campagna', 'fp-digital-marketing'),
            'source_medium' => esc_html__('Source / Medium', 'fp-digital-marketing'),
            'utm_campaign'  => esc_html__('Campaign', 'fp-digital-marketing'),
            'clicks'        => esc_html__('Click', 'fp-digital-marketing'),
            'conversions'   => esc_html__('Conversioni', 'fp-digital-marketing'),
            'status'        => esc_html__('Stato', 'fp-digital-marketing'),
            'created_at'    => esc_html__('Creato il', 'fp-digital-marketing'),
        ];
    }

    /**
     * Provide sortable columns configuration.
     *
     * @return array<string,array{0:string,1:bool}>
     */
    protected function get_sortable_columns(): array
    {
        return [
            'campaign_name' => ['campaign_name', true],
            'clicks'        => ['clicks', false],
            'conversions'   => ['conversions', false],
            'created_at'    => ['created_at', true],
        ];
    }

    /**
     * Text to display when no items are present.
     */
    public function no_items(): void
    {
        echo esc_html__('Nessuna campagna trovata. Crea una nuova campagna per iniziare.', 'fp-digital-marketing');
    }

    /**
     * Render checkbox column.
     *
     * @param array<string,mixed> $item Current row.
     */
    public function column_cb($item): string
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        return '<label class="screen-reader-text" for="campaign_' . esc_attr((string) $id) . '">' . esc_html__('Seleziona campagna', 'fp-digital-marketing') . '</label>'
            . '<input type="checkbox" name="campaign_ids[]" id="campaign_' . esc_attr((string) $id) . '" value="' . esc_attr((string) $id) . '" />';
    }

    /**
     * Render the campaign name with row actions.
     *
     * @param array<string,mixed> $item Current row.
     */
    public function column_campaign_name(array $item): string
    {
        $campaign_id = isset($item['id']) ? (int) $item['id'] : 0;
        $campaign_name = $item['campaign_name'] ?? '';

        $actions = [];
        $base_args = [
            'page'        => sanitize_key($_REQUEST['page'] ?? ''),
            'campaign_id' => $campaign_id,
        ];

        $view_url = add_query_arg(
            array_merge($base_args, ['action' => 'view']),
            admin_url('admin.php')
        );
        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($view_url),
            esc_html__('Dettagli', 'fp-digital-marketing')
        );

        $edit_url = add_query_arg(
            array_merge($base_args, ['action' => 'edit']),
            admin_url('admin.php')
        );
        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_url),
            esc_html__('Modifica', 'fp-digital-marketing')
        );

        if (Capabilities::current_user_can(Capabilities::MANAGE_CAMPAIGNS)) {
            $delete_url = wp_nonce_url(
                add_query_arg(
                    array_merge($base_args, ['action' => 'delete']),
                    admin_url('admin.php')
                ),
                'utm-campaign-row-action-' . $campaign_id
            );

            $actions['delete'] = sprintf(
                '<a href="%s" class="submitdelete" data-confirm="%s">%s</a>',
                esc_url($delete_url),
                esc_attr__('Sei sicuro di voler eliminare questa campagna?', 'fp-digital-marketing'),
                esc_html__('Elimina', 'fp-digital-marketing')
            );
        }

        $output  = '<strong><a class="row-title" href="' . esc_url($view_url) . '">';
        $output .= esc_html($campaign_name !== '' ? $campaign_name : esc_html__('(Senza nome)', 'fp-digital-marketing'));
        $output .= '</a></strong>';
        $output .= $this->row_actions($actions);

        return $output;
    }

    /**
     * Render the source/medium column.
     *
     * @param array<string,mixed> $item Current row.
     */
    public function column_source_medium(array $item): string
    {
        $source = $item['utm_source'] ?? '';
        $medium = $item['utm_medium'] ?? '';

        if ($source === '' && $medium === '') {
            return '&#8212;';
        }

        return esc_html(trim($source . ' / ' . $medium, ' /'));
    }

    /**
     * Format numeric columns.
     *
     * @param array<string,mixed> $item Current row.
     * @param string              $column_name Column name.
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'utm_campaign':
                return esc_html((string) ($item['utm_campaign'] ?? ''));
            case 'clicks':
            case 'conversions':
                return esc_html(number_format_i18n((int) ($item[$column_name] ?? 0)));
            case 'created_at':
                $created_at = $item['created_at'] ?? '';
                if ($created_at === '') {
                    return '&#8212;';
                }

                $timestamp = strtotime((string) $created_at);
                if ($timestamp === false) {
                    return esc_html((string) $created_at);
                }

                return esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp));
            case 'status':
                $status = isset($item['status']) ? sanitize_key((string) $item['status']) : '';
                $label  = $this->status_labels[$status] ?? $status;
                $classes = ['fp-dms-status-pill'];
                if ($status !== '') {
                    $classes[] = 'fp-dms-status-pill--' . sanitize_html_class($status);
                }

                return '<span class="' . esc_attr(implode(' ', $classes)) . '">' . esc_html($label) . '</span>';
            default:
                return esc_html((string) ($item[$column_name] ?? ''));
        }
    }

    /**
     * Provide view filters for statuses.
     *
     * @return array<string,string>
     */
    protected function get_views(): array
    {
        $base_url = remove_query_arg(['status', 'paged'], esc_url_raw(add_query_arg([])));
        $views = [];

        $total_all = UTMCampaign::get_campaigns_count([]);
        $all_label = sprintf(
            '%s <span class="count">(%d)</span>',
            esc_html__('Tutte', 'fp-digital-marketing'),
            $total_all
        );
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($base_url),
            $this->status_filter === '' ? 'current' : '',
            $all_label
        );

        foreach ($this->status_labels as $status => $label) {
            $url = add_query_arg('status', $status, $base_url);
            $count = UTMCampaign::get_campaigns_count(['status' => $status]);
            $views[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $this->status_filter === $status ? 'current' : '',
                esc_html($label),
                $count
            );
        }

        return $views;
    }

    /**
     * Render filter controls above the table.
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';
        echo '<label class="screen-reader-text" for="filter-by-status">' . esc_html__('Filtra per stato', 'fp-digital-marketing') . '</label>';
        echo '<select name="status" id="filter-by-status">';
        echo '<option value="">' . esc_html__('Tutti gli stati', 'fp-digital-marketing') . '</option>';
        foreach ($this->status_labels as $status => $label) {
            $selected = selected($this->status_filter, $status, false);
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        submit_button(esc_html__('Filtra', 'fp-digital-marketing'), 'secondary', 'filter_action', false);
        echo '</div>';
    }

    /**
     * Configure available bulk actions.
     *
     * @return array<string,string>
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete'   => esc_html__('Elimina', 'fp-digital-marketing'),
            'activate' => esc_html__('Imposta come attiva', 'fp-digital-marketing'),
            'pause'    => esc_html__('Metti in pausa', 'fp-digital-marketing'),
            'complete' => esc_html__('Segna come completata', 'fp-digital-marketing'),
        ];
    }

    /**
     * Handle row and bulk actions.
     */
    protected function process_bulk_action(): void
    {
        $action = $this->current_action();
        if (! $action || ! in_array($action, ['delete', 'activate', 'pause', 'complete'], true)) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::MANAGE_CAMPAIGNS)) {
            return;
        }

        $ids = [];
        $nonce_action = '';

        if (! empty($_REQUEST['campaign_id'])) {
            $id = absint($_REQUEST['campaign_id']);
            if ($id > 0) {
                $ids = [$id];
                $nonce_action = 'utm-campaign-row-action-' . $id;
            }
        } elseif (! empty($_REQUEST['campaign_ids']) && is_array($_REQUEST['campaign_ids'])) {
            $ids = array_filter(array_map('absint', (array) $_REQUEST['campaign_ids']));
            $nonce_action = 'bulk-' . $this->_args['plural'];
        }

        if (empty($ids)) {
            return;
        }

        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if ($nonce_action === '') {
            $nonce_action = 'bulk-' . $this->_args['plural'];
        }

        if (! wp_verify_nonce((string) $nonce, $nonce_action)) {
            $this->add_notice(esc_html__('Nonce non valido per l\'azione richiesta.', 'fp-digital-marketing'), 'error');
            return;
        }

        $updated = 0;

        foreach ($ids as $id) {
            $campaign = UTMCampaign::find((int) $id);
            if (! $campaign) {
                continue;
            }

            if ($action === 'delete') {
                if ($campaign->delete()) {
                    $updated++;
                }
                continue;
            }

            $data = $campaign->to_array();
            $data['status'] = $this->normalize_status_for_action($action, (string) ($data['status'] ?? ''));
            $campaign->populate($data);

            if ($campaign->save()) {
                $updated++;
            }
        }

        if ($updated === 0) {
            $this->add_notice(esc_html__('Nessuna campagna è stata aggiornata.', 'fp-digital-marketing'), 'warning');
            return;
        }

        switch ($action) {
            case 'delete':
                $message = _n('%d campagna eliminata.', '%d campagne eliminate.', $updated, 'fp-digital-marketing');
                break;
            case 'activate':
                $message = _n('%d campagna impostata come attiva.', '%d campagne impostate come attive.', $updated, 'fp-digital-marketing');
                break;
            case 'pause':
                $message = _n('%d campagna messa in pausa.', '%d campagne messe in pausa.', $updated, 'fp-digital-marketing');
                break;
            default:
                $message = _n('%d campagna segnata come completata.', '%d campagne segnate come completate.', $updated, 'fp-digital-marketing');
                break;
        }

        $this->add_notice(sprintf($message, $updated), 'success');
    }

    /**
     * Collect admin notice to display after actions.
     */
    private function add_notice(string $message, string $type = 'success'): void
    {
        $this->notices[] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    /**
     * Return accumulated notices.
     *
     * @return list<array{message:string,type:string}>
     */
    public function get_admin_notices(): array
    {
        return $this->notices;
    }

    /**
     * Map actions to normalized status values.
     */
    private function normalize_status_for_action(string $action, string $current): string
    {
        switch ($action) {
            case 'activate':
                return 'active';
            case 'pause':
                return 'paused';
            case 'complete':
                return 'completed';
        }

        return $current;
    }
}
