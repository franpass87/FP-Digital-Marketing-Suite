<?php
/**
 * Anomaly Radar (KPI Watchdog) for Clients
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\DetectedAnomaly;
use FP\DigitalMarketing\Models\AnomalyRule;
use FP\DigitalMarketing\Helpers\MetricsSchema;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\AnomalySuggestionEngine;
use FP\DigitalMarketing\PostTypes\ClientePostType;

/**
 * AnomalyRadar class for client-specific anomaly monitoring
 */
class AnomalyRadar {

	/**
	 * Page slug for the anomaly radar
	 */
	public const PAGE_SLUG = 'fp-anomaly-radar';

	/**
	 * Initialize the anomaly radar interface
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_client_submenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add submenu to the Cliente post type
	 *
	 * @return void
	 */
	public function add_client_submenu(): void {
		add_submenu_page(
			'edit.php?post_type=' . ClientePostType::POST_TYPE,
			__( 'Anomaly Radar (KPI Watchdog)', 'fp-digital-marketing' ),
			__( '📡 Anomaly Radar', 'fp-digital-marketing' ),
			Capabilities::MANAGE_ALERTS,
			self::PAGE_SLUG,
			[ $this, 'display_radar_page' ]
		);
	}

	/**
	 * Display the anomaly radar page
	 *
	 * @return void
	 */
	public function display_radar_page(): void {
				$client_id = (int) ( $_GET['client_id'] ?? 0 );
				$action    = sanitize_key( $_GET['action'] ?? 'overview' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Anomaly Radar (KPI Watchdog)', 'fp-digital-marketing' ) . '</h1>';

		// Client selector
		$this->render_client_selector( $client_id );

		if ( $client_id > 0 ) {
			switch ( $action ) {
				case 'history':
					$this->display_anomaly_history( $client_id );
					break;
				case 'rules':
					$this->display_client_rules( $client_id );
					break;
				default:
					$this->display_client_overview( $client_id );
					break;
			}
		} else {
			$this->display_no_client_selected();
		}

		echo '</div>';
	}

	/**
	 * Render client selector dropdown
	 *
	 * @param int $selected_client_id Currently selected client ID
	 * @return void
	 */
	private function render_client_selector( int $selected_client_id ): void {
		$clients = get_posts(
			[
				'post_type'      => ClientePostType::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="' . esc_attr( ClientePostType::POST_TYPE ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';

		echo '<select name="client_id" onchange="this.form.submit();">';
		echo '<option value="">' . esc_html__( 'Seleziona Cliente...', 'fp-digital-marketing' ) . '</option>';

		foreach ( $clients as $client ) {
			$selected = $client->ID === $selected_client_id ? 'selected' : '';
			echo '<option value="' . esc_attr( $client->ID ) . '" ' . $selected . '>';
			echo esc_html( $client->post_title );
			echo '</option>';
		}

		echo '</select>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Display client overview with recent anomalies and stats
	 *
	 * @param int $client_id Client ID
	 * @return void
	 */
	private function display_client_overview( int $client_id ): void {
		$client_title = get_the_title( $client_id );

		echo '<h2>' . sprintf(
			esc_html__( 'KPI Watchdog per %s', 'fp-digital-marketing' ),
			esc_html( $client_title )
		) . '</h2>';

		// Navigation tabs
		$this->render_navigation_tabs( $client_id, 'overview' );

		// Get recent anomalies for this client
		$recent_anomalies = DetectedAnomaly::get_anomalies(
			[
				'client_id' => $client_id,
				'days_back' => 7,
				'limit'     => 10,
			]
		);

		// Get client statistics
		$stats = DetectedAnomaly::get_statistics(
			[
				'client_id' => $client_id,
				'days_back' => 30,
			]
		);

		// Display overview cards
		echo '<div class="fp-anomaly-radar-overview">';

		// Stats cards
		echo '<div class="fp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">';

		$this->render_stat_card(
			__( 'Anomalie Totali (30g)', 'fp-digital-marketing' ),
			$stats['total_count'] ?? 0,
			'dashicons-chart-line'
		);

		$this->render_stat_card(
			__( 'Non Riconosciute', 'fp-digital-marketing' ),
			$stats['unacknowledged_count'] ?? 0,
			'dashicons-warning',
			$stats['unacknowledged_count'] > 0 ? '#d63638' : '#007cba'
		);

		$this->render_stat_card(
			__( 'Metriche Monitorate', 'fp-digital-marketing' ),
			$stats['affected_metrics'] ?? 0,
			'dashicons-visibility'
		);

		echo '</div>';

		// Recent anomalies table
		if ( ! empty( $recent_anomalies ) ) {
			echo '<h3>' . esc_html__( 'Anomalie Recenti (7 giorni)', 'fp-digital-marketing' ) . '</h3>';
			$this->render_anomalies_table( $recent_anomalies, true );
		} else {
			echo '<div class="notice notice-success">';
			echo '<p>' . esc_html__( 'Nessuna anomalia rilevata negli ultimi 7 giorni. Tutti i KPI sembrano normali! 🎉', 'fp-digital-marketing' ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Display anomaly history for the client
	 *
	 * @param int $client_id Client ID
	 * @return void
	 */
	private function display_anomaly_history( int $client_id ): void {
		$client_title = get_the_title( $client_id );

		echo '<h2>' . sprintf(
			esc_html__( 'Storico Anomalie - %s', 'fp-digital-marketing' ),
			esc_html( $client_title )
		) . '</h2>';

		$this->render_navigation_tabs( $client_id, 'history' );

		// Get filters
		$days_back = (int) ( $_GET['days_back'] ?? 30 );
		$metric    = sanitize_text_field( $_GET['metric'] ?? '' );

		// Filter form
		echo '<div class="tablenav top">';
		echo '<form method="get" style="display: inline-flex; gap: 10px; align-items: center;">';
		echo '<input type="hidden" name="post_type" value="' . esc_attr( ClientePostType::POST_TYPE ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
		echo '<input type="hidden" name="client_id" value="' . esc_attr( $client_id ) . '">';
		echo '<input type="hidden" name="action" value="history">';

		echo '<select name="days_back">';
		$periods = [
			7  => '7 giorni',
			30 => '30 giorni',
			90 => '90 giorni',
		];
		foreach ( $periods as $period => $label ) {
			$selected = $period === $days_back ? 'selected' : '';
			echo '<option value="' . esc_attr( $period ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<select name="metric">';
		echo '<option value="">' . esc_html__( 'Tutte le metriche', 'fp-digital-marketing' ) . '</option>';
		$kpi_definitions = MetricsSchema::get_kpi_definitions();
		foreach ( $kpi_definitions as $kpi_key => $kpi_def ) {
			$selected = $kpi_key === $metric ? 'selected' : '';
			echo '<option value="' . esc_attr( $kpi_key ) . '" ' . $selected . '>' . esc_html( $kpi_def['name'] ) . '</option>';
		}
		echo '</select>';

		echo '<input type="submit" class="button" value="' . esc_attr__( 'Filtra', 'fp-digital-marketing' ) . '">';
		echo '</form>';
		echo '</div>';

		// Get filtered anomalies
		$filters = [
			'client_id' => $client_id,
			'days_back' => $days_back,
			'limit'     => 100,
		];

		if ( ! empty( $metric ) ) {
			$filters['metric'] = $metric;
		}

		$anomalies = DetectedAnomaly::get_anomalies( $filters );

		if ( ! empty( $anomalies ) ) {
			$this->render_anomalies_table( $anomalies, false );
		} else {
			echo '<div class="notice notice-info">';
			echo '<p>' . esc_html__( 'Nessuna anomalia trovata per i filtri selezionati.', 'fp-digital-marketing' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Display anomaly rules for the client
	 *
	 * @param int $client_id Client ID
	 * @return void
	 */
	private function display_client_rules( int $client_id ): void {
		$client_title = get_the_title( $client_id );

		echo '<h2>' . sprintf(
			esc_html__( 'Regole di Monitoraggio - %s', 'fp-digital-marketing' ),
			esc_html( $client_title )
		) . '</h2>';

		$this->render_navigation_tabs( $client_id, 'rules' );

		// Get rules for this client
		$rules = AnomalyRule::get_rules( [ 'client_id' => $client_id ] );

		if ( ! empty( $rules ) ) {
			echo '<div class="fp-client-rules">';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Nome Regola', 'fp-digital-marketing' ) . '</th>';
			echo '<th>' . esc_html__( 'Metrica', 'fp-digital-marketing' ) . '</th>';
			echo '<th>' . esc_html__( 'Metodo', 'fp-digital-marketing' ) . '</th>';
			echo '<th>' . esc_html__( 'Stato', 'fp-digital-marketing' ) . '</th>';
			echo '<th>' . esc_html__( 'Azioni', 'fp-digital-marketing' ) . '</th>';
			echo '</tr></thead><tbody>';

			$kpi_definitions = MetricsSchema::get_kpi_definitions();

			foreach ( $rules as $rule ) {
				$metric_name  = $kpi_definitions[ $rule->metric ]['name'] ?? $rule->metric;
				$status_class = $rule->is_active ? 'active' : 'inactive';
				$status_text  = $rule->is_active ? __( 'Attiva', 'fp-digital-marketing' ) : __( 'Inattiva', 'fp-digital-marketing' );

				echo '<tr>';
				echo '<td><strong>' . esc_html( $rule->name ) . '</strong></td>';
				echo '<td>' . esc_html( $metric_name ) . '</td>';
				echo '<td>' . esc_html( $rule->detection_method ) . '</td>';
				echo '<td><span class="fp-status-' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
				echo '<td>';
				echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . ClientePostType::POST_TYPE . '&page=fp-digital-marketing-anomalies&action=edit&rule_id=' . $rule->id ) ) . '" class="button button-small">';
				echo esc_html__( 'Modifica', 'fp-digital-marketing' );
				echo '</a>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-warning">';
			echo '<p>' . esc_html__( 'Nessuna regola di monitoraggio configurata per questo cliente.', 'fp-digital-marketing' ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . ClientePostType::POST_TYPE . '&page=fp-digital-marketing-anomalies&action=add' ) ) . '" class="button button-primary">';
			echo esc_html__( 'Crea Prima Regola', 'fp-digital-marketing' );
			echo '</a></p>';
			echo '</div>';
		}
	}

	/**
	 * Display message when no client is selected
	 *
	 * @return void
	 */
	private function display_no_client_selected(): void {
		echo '<div class="notice notice-info">';
		echo '<p>' . esc_html__( 'Seleziona un cliente per visualizzare il suo Anomaly Radar (KPI Watchdog).', 'fp-digital-marketing' ) . '</p>';
		echo '</div>';

		echo '<div class="fp-anomaly-radar-intro">';
		echo '<h3>' . esc_html__( 'Cos\'è l\'Anomaly Radar?', 'fp-digital-marketing' ) . '</h3>';
		echo '<p>' . esc_html__( 'L\'Anomaly Radar è un sistema di monitoraggio avanzato che ti avvisa quando i KPI di un cliente presentano comportamenti anomali rispetto ai pattern storici.', 'fp-digital-marketing' ) . '</p>';

		echo '<h4>' . esc_html__( 'Funzionalità principali:', 'fp-digital-marketing' ) . '</h4>';
		echo '<ul>';
		echo '<li>📊 ' . esc_html__( 'Monitoraggio in tempo reale dei KPI principali', 'fp-digital-marketing' ) . '</li>';
		echo '<li>🔍 ' . esc_html__( 'Rilevamento automatico di anomalie', 'fp-digital-marketing' ) . '</li>';
		echo '<li>📈 ' . esc_html__( 'Analisi delle tendenze e pattern storici', 'fp-digital-marketing' ) . '</li>';
		echo '<li>🚨 ' . esc_html__( 'Notifiche immediate per anomalie critiche', 'fp-digital-marketing' ) . '</li>';
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render navigation tabs for client-specific views
	 *
	 * @param int    $client_id Client ID
	 * @param string $current_tab Current active tab
	 * @return void
	 */
	private function render_navigation_tabs( int $client_id, string $current_tab ): void {
		$base_url = admin_url( 'edit.php?post_type=' . ClientePostType::POST_TYPE . '&page=' . self::PAGE_SLUG . '&client_id=' . $client_id );

		$tabs = [
			'overview' => __( 'Panoramica', 'fp-digital-marketing' ),
			'history'  => __( 'Storico', 'fp-digital-marketing' ),
			'rules'    => __( 'Regole', 'fp-digital-marketing' ),
		];

		echo '<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
		foreach ( $tabs as $tab_key => $tab_label ) {
			$active_class = $current_tab === $tab_key ? ' nav-tab-active' : '';
			$tab_url      = $tab_key === 'overview' ? $base_url : $base_url . '&action=' . $tab_key;

			echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . $active_class . '">';
			echo esc_html( $tab_label );
			echo '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Render a statistics card
	 *
	 * @param string $title Card title
	 * @param mixed  $value Card value
	 * @param string $icon Dashicon class
	 * @param string $color Optional color
	 * @return void
	 */
	private function render_stat_card( string $title, $value, string $icon, string $color = '#007cba' ): void {
		echo '<div class="fp-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">';
		echo '<div class="dashicons ' . esc_attr( $icon ) . '" style="font-size: 32px; color: ' . esc_attr( $color ) . '; margin-bottom: 10px;"></div>';
		echo '<div style="font-size: 24px; font-weight: bold; color: ' . esc_attr( $color ) . ';">' . esc_html( $value ) . '</div>';
		echo '<div style="color: #666; margin-top: 5px;">' . esc_html( $title ) . '</div>';
		echo '</div>';
	}

	/**
	 * Render anomalies table
	 *
	 * @param array $anomalies Array of anomaly objects
	 * @param bool  $compact Whether to show compact view
	 * @return void
	 */
	private function render_anomalies_table( array $anomalies, bool $compact = false ): void {
		$kpi_definitions = MetricsSchema::get_kpi_definitions();

		echo '<table class="wp-list-table widefat fixed striped fp-anomalies-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Metrica', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Valore', 'fp-digital-marketing' ) . '</th>';
		if ( ! $compact ) {
			echo '<th>' . esc_html__( 'Valore Atteso', 'fp-digital-marketing' ) . '</th>';
			echo '<th>' . esc_html__( 'Metodo', 'fp-digital-marketing' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Gravità', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Rilevata', 'fp-digital-marketing' ) . '</th>';
		echo '<th>' . esc_html__( 'Azioni', 'fp-digital-marketing' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $anomalies as $anomaly ) {
			$metric_name = $kpi_definitions[ $anomaly->metric ]['name'] ?? $anomaly->metric;
			$row_class   = $anomaly->acknowledged ? 'acknowledged' : 'unacknowledged';
			$anomaly_id  = 'anomaly-' . $anomaly->id;

			echo '<tr class="' . esc_attr( $row_class ) . '" data-anomaly-id="' . esc_attr( $anomaly->id ) . '">';
			echo '<td><strong>' . esc_html( $metric_name ) . '</strong></td>';
			echo '<td>';
			echo '<span class="current-value">' . esc_html( number_format( $anomaly->current_value, 2 ) ) . '</span>';
			if ( $anomaly->deviation_type === 'positive' ) {
				echo ' <span class="dashicons dashicons-arrow-up-alt" style="color: #d63638;"></span>';
			} elseif ( $anomaly->deviation_type === 'negative' ) {
				echo ' <span class="dashicons dashicons-arrow-down-alt" style="color: #d63638;"></span>';
			}
			echo '</td>';

			if ( ! $compact ) {
				echo '<td>' . esc_html( number_format( $anomaly->expected_value ?? 0, 2 ) ) . '</td>';
				echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', $anomaly->detection_method ) ) ) . '</td>';
			}

			$severity_colors = [
				'critical' => '#d63638',
				'high'     => '#dba617',
				'medium'   => '#00a0d2',
				'low'      => '#007cba',
			];
			$severity_color  = $severity_colors[ $anomaly->severity ] ?? '#666';

			echo '<td><span style="color: ' . esc_attr( $severity_color ) . '; font-weight: bold;">' . esc_html( ucfirst( $anomaly->severity ) ) . '</span></td>';
			echo '<td>' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $anomaly->detected_at ) ) ) . '</td>';
			echo '<td>';
			echo '<button type="button" class="button button-small toggle-suggestions" data-target="' . esc_attr( $anomaly_id ) . '">';
			echo '<span class="dashicons dashicons-lightbulb"></span> ' . esc_html__( 'Suggerimenti', 'fp-digital-marketing' );
			echo '</button>';
			echo '</td>';
			echo '</tr>';

			// Suggestions row (initially hidden)
			echo '<tr class="suggestions-row" id="' . esc_attr( $anomaly_id ) . '" style="display: none;">';
			echo '<td colspan="' . ( $compact ? '5' : '7' ) . '">';
			$this->render_anomaly_suggestions( $anomaly );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Add JavaScript for expandable suggestions
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.toggle-suggestions').on('click', function() {
				const target = $(this).data('target');
				const $suggestionRow = $('#' + target);
				const $button = $(this);
				
				if ($suggestionRow.is(':visible')) {
					$suggestionRow.hide();
					$button.find('.dashicons').removeClass('dashicons-lightbulb').addClass('dashicons-lightbulb');
				} else {
					$suggestionRow.show();
					$button.find('.dashicons').removeClass('dashicons-lightbulb').addClass('dashicons-dismiss');
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render suggestions for a specific anomaly
	 *
	 * @param DetectedAnomaly $anomaly Anomaly object
	 * @return void
	 */
	private function render_anomaly_suggestions( DetectedAnomaly $anomaly ): void {
		$suggestions = AnomalySuggestionEngine::generate_suggestions( $anomaly );

		if ( empty( $suggestions ) ) {
			echo '<div class="notice notice-info inline">';
			echo '<p>' . esc_html__( 'Nessun suggerimento automatico disponibile per questa anomalia.', 'fp-digital-marketing' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="anomaly-suggestions">';
		echo '<h4>' . esc_html__( 'Suggerimenti per risolvere l\'anomalia:', 'fp-digital-marketing' ) . '</h4>';

		foreach ( $suggestions as $suggestion ) {
			$priority_class = 'priority-' . $suggestion['priority'];
			$category_icon  = $this->get_category_icon( $suggestion['category'] );

			echo '<div class="suggestion-card ' . esc_attr( $priority_class ) . '">';
			echo '<div class="suggestion-header">';
			echo '<span class="category-icon">' . $category_icon . '</span>';
			echo '<h5>' . esc_html( $suggestion['title'] ) . '</h5>';
			echo '<span class="priority-badge priority-' . esc_attr( $suggestion['priority'] ) . '">';
			echo esc_html( $this->get_priority_label( $suggestion['priority'] ) );
			echo '</span>';
			echo '</div>';

			echo '<div class="suggestion-content">';
			echo '<p>' . esc_html( $suggestion['description'] ) . '</p>';

			if ( ! empty( $suggestion['actions'] ) ) {
				echo '<div class="suggested-actions">';
				echo '<strong>' . esc_html__( 'Azioni consigliate:', 'fp-digital-marketing' ) . '</strong>';
				echo '<ul>';
				foreach ( $suggestion['actions'] as $action ) {
					echo '<li>' . esc_html( $action ) . '</li>';
				}
				echo '</ul>';
				echo '</div>';
			}
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';

		// Add CSS for suggestions
		?>
		<style>
		.anomaly-suggestions {
			background: #f9f9f9;
			padding: 15px;
			border-radius: 4px;
			margin: 10px 0;
		}
		
		.suggestion-card {
			background: white;
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 15px;
			margin: 10px 0;
			border-left-width: 4px;
		}
		
		.suggestion-card.priority-critical {
			border-left-color: #d63638;
		}
		
		.suggestion-card.priority-high {
			border-left-color: #dba617;
		}
		
		.suggestion-card.priority-medium {
			border-left-color: #00a0d2;
		}
		
		.suggestion-card.priority-low {
			border-left-color: #007cba;
		}
		
		.suggestion-header {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-bottom: 10px;
		}
		
		.suggestion-header h5 {
			margin: 0;
			flex-grow: 1;
		}
		
		.category-icon {
			font-size: 18px;
		}
		
		.priority-badge {
			padding: 2px 8px;
			border-radius: 12px;
			font-size: 11px;
			font-weight: bold;
			text-transform: uppercase;
		}
		
		.priority-badge.priority-critical {
			background: #d63638;
			color: white;
		}
		
		.priority-badge.priority-high {
			background: #dba617;
			color: white;
		}
		
		.priority-badge.priority-medium {
			background: #00a0d2;
			color: white;
		}
		
		.priority-badge.priority-low {
			background: #007cba;
			color: white;
		}
		
		.suggested-actions {
			margin-top: 10px;
		}
		
		.suggested-actions ul {
			margin: 5px 0 0 20px;
		}
		
		.suggested-actions li {
			margin: 5px 0;
		}
		</style>
		<?php
	}

	/**
	 * Get icon for suggestion category
	 *
	 * @param string $category Category name
	 * @return string Icon HTML
	 */
	private function get_category_icon( string $category ): string {
		$icons = [
			AnomalySuggestionEngine::CATEGORY_TECHNICAL   => '🔧',
			AnomalySuggestionEngine::CATEGORY_CONTENT     => '📝',
			AnomalySuggestionEngine::CATEGORY_MARKETING   => '📈',
			AnomalySuggestionEngine::CATEGORY_PERFORMANCE => '⚡',
			AnomalySuggestionEngine::CATEGORY_PLATFORM    => '🔗',
		];

		return $icons[ $category ] ?? '💡';
	}

	/**
	 * Get human-readable priority label
	 *
	 * @param string $priority Priority level
	 * @return string Priority label
	 */
	private function get_priority_label( string $priority ): string {
		$labels = [
			AnomalySuggestionEngine::PRIORITY_CRITICAL => __( 'Critico', 'fp-digital-marketing' ),
			AnomalySuggestionEngine::PRIORITY_HIGH     => __( 'Alto', 'fp-digital-marketing' ),
			AnomalySuggestionEngine::PRIORITY_MEDIUM   => __( 'Medio', 'fp-digital-marketing' ),
			AnomalySuggestionEngine::PRIORITY_LOW      => __( 'Basso', 'fp-digital-marketing' ),
		];

		return $labels[ $priority ] ?? $priority;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current page hook
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on our page
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		// Add some custom CSS for the radar interface
		wp_add_inline_style(
			'wp-admin',
			'
			.fp-anomaly-radar-overview {
				margin-top: 20px;
			}
			.fp-stats-grid {
				margin-bottom: 30px;
			}
			.fp-stat-card {
				transition: transform 0.2s ease, box-shadow 0.2s ease;
			}
			.fp-stat-card:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			}
			.fp-status-active {
				color: #007cba;
				font-weight: bold;
			}
			.fp-status-inactive {
				color: #d63638;
				font-weight: bold;
			}
			.acknowledged {
				opacity: 0.7;
			}
			.unacknowledged {
				border-left: 4px solid #d63638;
			}
			.fp-anomaly-radar-intro {
				max-width: 800px;
				margin: 20px 0;
			}
			.fp-anomaly-radar-intro ul {
				list-style: none;
				padding-left: 0;
			}
			.fp-anomaly-radar-intro li {
				margin: 8px 0;
				padding-left: 0;
			}
		'
		);
	}
}