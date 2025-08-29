<?php
/**
 * UTM Campaign Manager Admin Page
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Models\UTMCampaign;
use FP\DigitalMarketing\Helpers\UTMGenerator;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * UTM Campaign Manager class for admin interface
 */
class UTMCampaignManager {

	/**
	 * Page slug for UTM campaign manager
	 */
	private const PAGE_SLUG = 'fp-utm-campaign-manager';

	/**
	 * Option group name
	 */
	private const OPTION_GROUP = 'fp_utm_campaign_settings';

	/**
	 * Nonce action for UTM forms
	 */
	private const NONCE_ACTION = 'fp_utm_campaign_nonce';

	/**
	 * Initialize the UTM campaign manager
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_fp_utm_generate_url', [ $this, 'handle_ajax_generate_url' ] );
		add_action( 'wp_ajax_fp_utm_load_preset', [ $this, 'handle_ajax_load_preset' ] );
		add_action( 'wp_ajax_fp_utm_delete_campaign', [ $this, 'handle_ajax_delete_campaign' ] );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'fp-digital-marketing-dashboard',
			__( 'Gestione Campagne UTM', 'fp-digital-marketing' ),
			__( 'Campagne UTM', 'fp-digital-marketing' ),
			Capabilities::MANAGE_CAMPAIGNS,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wp-util' );
		
		// UTM Campaign Manager specific styles.
		wp_add_inline_style( 'wp-admin', $this->get_inline_css() );

		// UTM Campaign Manager JavaScript.
		wp_add_inline_script( 'jquery', $this->get_inline_js() );

		// Localize script for AJAX.
		wp_localize_script( 'jquery', 'fpUtmAjax', [
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'strings'   => [
				'confirm_delete' => __( 'Sei sicuro di voler eliminare questa campagna?', 'fp-digital-marketing' ),
				'url_copied'     => __( 'URL copiato negli appunti!', 'fp-digital-marketing' ),
				'copy_failed'    => __( 'Impossibile copiare l\'URL. Seleziona e copia manualmente.', 'fp-digital-marketing' ),
				'generating'     => __( 'Generazione in corso...', 'fp-digital-marketing' ),
				'error'          => __( 'Si è verificato un errore. Riprova.', 'fp-digital-marketing' ),
			],
		] );
	}

	/**
	 * Handle form submissions
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['fp_utm_action'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_die( __( 'Nonce di sicurezza non valido.', 'fp-digital-marketing' ) );
		}

		// Check capabilities.
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_CAMPAIGNS ) ) {
			wp_die( __( 'Non hai i permessi per eseguire questa azione.', 'fp-digital-marketing' ) );
		}

		$action = sanitize_text_field( $_POST['fp_utm_action'] );

		switch ( $action ) {
			case 'save_campaign':
				$this->handle_save_campaign();
				break;
			case 'update_campaign':
				$this->handle_update_campaign();
				break;
		}
	}

	/**
	 * Handle save new campaign
	 *
	 * @return void
	 */
	private function handle_save_campaign(): void {
		$campaign_data = $this->sanitize_campaign_data( $_POST );
		
		// Validate required fields.
		if ( empty( $campaign_data['campaign_name'] ) || 
			 empty( $campaign_data['utm_source'] ) || 
			 empty( $campaign_data['utm_medium'] ) || 
			 empty( $campaign_data['utm_campaign'] ) ||
			 empty( $campaign_data['base_url'] ) ) {
			$this->add_admin_notice( __( 'Tutti i campi obbligatori devono essere compilati.', 'fp-digital-marketing' ), 'error' );
			return;
		}

		$campaign = new UTMCampaign( $campaign_data );
		
		if ( $campaign->save() ) {
			$this->add_admin_notice( __( 'Campagna UTM salvata con successo.', 'fp-digital-marketing' ), 'success' );
			// Redirect to avoid resubmission.
			wp_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) ) );
			exit;
		} else {
			$this->add_admin_notice( __( 'Errore nel salvare la campagna. Verifica che non esistano duplicati.', 'fp-digital-marketing' ), 'error' );
		}
	}

	/**
	 * Handle update existing campaign
	 *
	 * @return void
	 */
	private function handle_update_campaign(): void {
		$campaign_id = (int) ( $_POST['campaign_id'] ?? 0 );
		
		if ( empty( $campaign_id ) ) {
			$this->add_admin_notice( __( 'ID campagna non valido.', 'fp-digital-marketing' ), 'error' );
			return;
		}

		$campaign = UTMCampaign::find( $campaign_id );
		if ( ! $campaign ) {
			$this->add_admin_notice( __( 'Campagna non trovata.', 'fp-digital-marketing' ), 'error' );
			return;
		}

		$campaign_data = $this->sanitize_campaign_data( $_POST );
		$campaign->populate( $campaign_data );

		if ( $campaign->save() ) {
			$this->add_admin_notice( __( 'Campagna UTM aggiornata con successo.', 'fp-digital-marketing' ), 'success' );
			wp_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) ) );
			exit;
		} else {
			$this->add_admin_notice( __( 'Errore nell\'aggiornare la campagna.', 'fp-digital-marketing' ), 'error' );
		}
	}

	/**
	 * Handle AJAX URL generation
	 *
	 * @return void
	 */
	public function handle_ajax_generate_url(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-digital-marketing' ) ] );
		}

		// Check capabilities.
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_CAMPAIGNS ) ) {
			wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'fp-digital-marketing' ) ] );
		}

		$base_url = esc_url_raw( $_POST['base_url'] ?? '' );
		$utm_params = [
			'source'   => sanitize_text_field( $_POST['utm_source'] ?? '' ),
			'medium'   => sanitize_text_field( $_POST['utm_medium'] ?? '' ),
			'campaign' => sanitize_text_field( $_POST['utm_campaign'] ?? '' ),
			'term'     => sanitize_text_field( $_POST['utm_term'] ?? '' ),
			'content'  => sanitize_text_field( $_POST['utm_content'] ?? '' ),
		];

		// Generate final URL.
		$final_url = UTMGenerator::generate_utm_url( $base_url, $utm_params );
		
		if ( empty( $final_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Impossibile generare l\'URL. Verifica i parametri.', 'fp-digital-marketing' ) ] );
		}

		// Generate suggested campaign name.
		$suggested_name = UTMGenerator::suggest_campaign_name( $utm_params );

		wp_send_json_success( [
			'final_url'       => $final_url,
			'suggested_name'  => $suggested_name,
		] );
	}

	/**
	 * Handle AJAX preset loading
	 *
	 * @return void
	 */
	public function handle_ajax_load_preset(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-digital-marketing' ) ] );
		}

		$preset_id = sanitize_text_field( $_POST['preset_id'] ?? '' );
		$preset = UTMGenerator::get_preset( $preset_id );

		if ( ! $preset ) {
			wp_send_json_error( [ 'message' => __( 'Preset non trovato.', 'fp-digital-marketing' ) ] );
		}

		wp_send_json_success( $preset );
	}

	/**
	 * Handle AJAX campaign deletion
	 *
	 * @return void
	 */
	public function handle_ajax_delete_campaign(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-digital-marketing' ) ] );
		}

		// Check capabilities.
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_CAMPAIGNS ) ) {
			wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'fp-digital-marketing' ) ] );
		}

		$campaign_id = (int) ( $_POST['campaign_id'] ?? 0 );
		$campaign = UTMCampaign::find( $campaign_id );

		if ( ! $campaign ) {
			wp_send_json_error( [ 'message' => __( 'Campagna non trovata.', 'fp-digital-marketing' ) ] );
		}

		if ( $campaign->delete() ) {
			wp_send_json_success( [ 'message' => __( 'Campagna eliminata con successo.', 'fp-digital-marketing' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Errore nell\'eliminare la campagna.', 'fp-digital-marketing' ) ] );
		}
	}

	/**
	 * Render the main page
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check user capabilities.
		if ( ! Capabilities::current_user_can( Capabilities::MANAGE_CAMPAIGNS ) ) {
			wp_die( __( 'Non hai i permessi per accedere a questa pagina.', 'fp-digital-marketing' ) );
		}

		$action = $_GET['action'] ?? 'list';
		$campaign_id = (int) ( $_GET['campaign_id'] ?? 0 );

		switch ( $action ) {
			case 'new':
				$this->render_campaign_form();
				break;
			case 'edit':
				$this->render_campaign_form( $campaign_id );
				break;
			case 'view':
				$this->render_campaign_view( $campaign_id );
				break;
			default:
				$this->render_campaigns_list();
				break;
		}
	}

	/**
	 * Render campaigns list
	 *
	 * @return void
	 */
	private function render_campaigns_list(): void {
		// Get filters.
		$filters = [
			'status'     => sanitize_text_field( $_GET['status'] ?? '' ),
			'utm_source' => sanitize_text_field( $_GET['utm_source'] ?? '' ),
			'utm_medium' => sanitize_text_field( $_GET['utm_medium'] ?? '' ),
			'search'     => sanitize_text_field( $_GET['search'] ?? '' ),
		];

		// Remove empty filters.
		$filters = array_filter( $filters );

		// Pagination.
		$per_page = 20;
		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$offset = ( $current_page - 1 ) * $per_page;

		// Get campaigns.
		$campaigns = UTMCampaign::get_campaigns( $filters, $per_page, $offset );
		$total_campaigns = UTMCampaign::get_campaigns_count( $filters );
		$total_pages = ceil( $total_campaigns / $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Gestione Campagne UTM', 'fp-digital-marketing' ); ?>
			</h1>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'new' ], admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Aggiungi Nuova Campagna', 'fp-digital-marketing' ); ?>
			</a>

			<hr class="wp-header-end">

			<!-- Filters -->
			<div class="utm-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					
					<p class="search-box">
						<label class="screen-reader-text" for="campaign-search-input"><?php esc_html_e( 'Cerca campagne:', 'fp-digital-marketing' ); ?></label>
						<input type="search" id="campaign-search-input" name="search" value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Cerca campagne...', 'fp-digital-marketing' ); ?>">
						
						<select name="status">
							<option value=""><?php esc_html_e( 'Tutti gli stati', 'fp-digital-marketing' ); ?></option>
							<option value="active" <?php selected( $_GET['status'] ?? '', 'active' ); ?>><?php esc_html_e( 'Attive', 'fp-digital-marketing' ); ?></option>
							<option value="paused" <?php selected( $_GET['status'] ?? '', 'paused' ); ?>><?php esc_html_e( 'In pausa', 'fp-digital-marketing' ); ?></option>
							<option value="completed" <?php selected( $_GET['status'] ?? '', 'completed' ); ?>><?php esc_html_e( 'Completate', 'fp-digital-marketing' ); ?></option>
						</select>
						
						<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Filtra', 'fp-digital-marketing' ); ?>">
					</p>
				</form>
			</div>

			<!-- Campaigns Table -->
			<table class="wp-list-table widefat fixed striped utm-campaigns-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Nome Campagna', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source/Medium', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Campaign', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Click', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Conversioni', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Data Creazione', 'fp-digital-marketing' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Azioni', 'fp-digital-marketing' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
						<tr>
							<td colspan="8" class="text-center">
								<?php esc_html_e( 'Nessuna campagna trovata.', 'fp-digital-marketing' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $campaigns as $campaign ) : ?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'view', 'campaign_id' => $campaign->get_id() ], admin_url( 'admin.php' ) ) ); ?>">
											<?php echo esc_html( $campaign->get_campaign_name() ); ?>
										</a>
									</strong>
								</td>
								<td><?php echo esc_html( $campaign->to_array()['utm_source'] . ' / ' . $campaign->to_array()['utm_medium'] ); ?></td>
								<td><?php echo esc_html( $campaign->to_array()['utm_campaign'] ); ?></td>
								<td><?php echo esc_html( number_format( $campaign->get_clicks() ) ); ?></td>
								<td><?php echo esc_html( number_format( $campaign->get_conversions() ) ); ?></td>
								<td>
									<span class="utm-status utm-status-<?php echo esc_attr( $campaign->get_status() ); ?>">
										<?php 
										$statuses = [
											'active'    => __( 'Attiva', 'fp-digital-marketing' ),
											'paused'    => __( 'In pausa', 'fp-digital-marketing' ),
											'completed' => __( 'Completata', 'fp-digital-marketing' ),
										];
										echo esc_html( $statuses[ $campaign->get_status() ] ?? $campaign->get_status() );
										?>
									</span>
								</td>
								<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $campaign->to_array()['created_at'] ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'edit', 'campaign_id' => $campaign->get_id() ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Modifica', 'fp-digital-marketing' ); ?>
									</a>
									<button type="button" class="button button-small button-delete-campaign" data-campaign-id="<?php echo esc_attr( $campaign->get_id() ); ?>">
										<?php esc_html_e( 'Elimina', 'fp-digital-marketing' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_args = [
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo;', 'fp-digital-marketing' ),
							'next_text' => __( '&raquo;', 'fp-digital-marketing' ),
							'total'     => $total_pages,
							'current'   => $current_page,
						];
						echo wp_kses_post( paginate_links( $pagination_args ) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render campaign form (new/edit)
	 *
	 * @param int|null $campaign_id Campaign ID for editing.
	 * @return void
	 */
	private function render_campaign_form( ?int $campaign_id = null ): void {
		$campaign = null;
		$is_edit = false;

		if ( $campaign_id ) {
			$campaign = UTMCampaign::find( $campaign_id );
			if ( ! $campaign ) {
				wp_die( __( 'Campagna non trovata.', 'fp-digital-marketing' ) );
			}
			$is_edit = true;
		}

		$presets = UTMGenerator::get_presets();
		?>
		<div class="wrap">
			<h1>
				<?php echo $is_edit ? esc_html__( 'Modifica Campagna UTM', 'fp-digital-marketing' ) : esc_html__( 'Nuova Campagna UTM', 'fp-digital-marketing' ); ?>
			</h1>

			<form method="post" action="" class="utm-campaign-form">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="fp_utm_action" value="<?php echo $is_edit ? 'update_campaign' : 'save_campaign'; ?>">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->get_id() ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tbody>
						<!-- Presets -->
						<tr>
							<th scope="row">
								<label for="preset"><?php esc_html_e( 'Preset Campagna', 'fp-digital-marketing' ); ?></label>
							</th>
							<td>
								<select id="preset" name="preset" class="regular-text">
									<option value=""><?php esc_html_e( 'Seleziona un preset...', 'fp-digital-marketing' ); ?></option>
									<?php foreach ( $presets as $preset_id => $preset_data ) : ?>
										<option value="<?php echo esc_attr( $preset_id ); ?>" <?php selected( $campaign ? $campaign->to_array()['preset_used'] : '', $preset_id ); ?>>
											<?php echo esc_html( $preset_data['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Seleziona un preset per compilare automaticamente i campi UTM.', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- Campaign Name -->
						<tr>
							<th scope="row">
								<label for="campaign_name"><?php esc_html_e( 'Nome Campagna', 'fp-digital-marketing' ); ?> *</label>
							</th>
							<td>
								<input type="text" id="campaign_name" name="campaign_name" value="<?php echo esc_attr( $campaign ? $campaign->get_campaign_name() : '' ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Nome descrittivo per identificare la campagna.', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- Base URL -->
						<tr>
							<th scope="row">
								<label for="base_url"><?php esc_html_e( 'URL Base', 'fp-digital-marketing' ); ?> *</label>
							</th>
							<td>
								<input type="url" id="base_url" name="base_url" value="<?php echo esc_attr( $campaign ? $campaign->to_array()['base_url'] : '' ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'URL di destinazione senza parametri UTM.', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- UTM Source -->
						<tr>
							<th scope="row">
								<label for="utm_source"><?php esc_html_e( 'UTM Source', 'fp-digital-marketing' ); ?> *</label>
							</th>
							<td>
								<input type="text" id="utm_source" name="utm_source" value="<?php echo esc_attr( $campaign ? $campaign->to_array()['utm_source'] : '' ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Es. google, facebook, newsletter', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- UTM Medium -->
						<tr>
							<th scope="row">
								<label for="utm_medium"><?php esc_html_e( 'UTM Medium', 'fp-digital-marketing' ); ?> *</label>
							</th>
							<td>
								<input type="text" id="utm_medium" name="utm_medium" value="<?php echo esc_attr( $campaign ? $campaign->to_array()['utm_medium'] : '' ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Es. cpc, social, email, referral', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- UTM Campaign -->
						<tr>
							<th scope="row">
								<label for="utm_campaign"><?php esc_html_e( 'UTM Campaign', 'fp-digital-marketing' ); ?> *</label>
							</th>
							<td>
								<input type="text" id="utm_campaign" name="utm_campaign" value="<?php echo esc_attr( $campaign ? $campaign->to_array()['utm_campaign'] : '' ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Nome specifico della campagna.', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- UTM Term -->
						<tr>
							<th scope="row">
								<label for="utm_term"><?php esc_html_e( 'UTM Term', 'fp-digital-marketing' ); ?></label>
							</th>
							<td>
								<input type="text" id="utm_term" name="utm_term" value="<?php echo esc_attr( $campaign ? ( $campaign->to_array()['utm_term'] ?? '' ) : '' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Parole chiave (opzionale).', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- UTM Content -->
						<tr>
							<th scope="row">
								<label for="utm_content"><?php esc_html_e( 'UTM Content', 'fp-digital-marketing' ); ?></label>
							</th>
							<td>
								<input type="text" id="utm_content" name="utm_content" value="<?php echo esc_attr( $campaign ? ( $campaign->to_array()['utm_content'] ?? '' ) : '' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Contenuto specifico (opzionale).', 'fp-digital-marketing' ); ?></p>
							</td>
						</tr>

						<!-- Generated URL Preview -->
						<tr>
							<th scope="row">
								<label for="final_url_preview"><?php esc_html_e( 'URL Finale', 'fp-digital-marketing' ); ?></label>
							</th>
							<td>
								<div class="utm-url-preview">
									<textarea id="final_url_preview" class="large-text" rows="3" readonly><?php echo esc_textarea( $campaign ? $campaign->get_final_url() : '' ); ?></textarea>
									<div class="utm-url-actions">
										<button type="button" id="generate_url_btn" class="button button-secondary">
											<?php esc_html_e( 'Genera URL', 'fp-digital-marketing' ); ?>
										</button>
										<button type="button" id="copy_url_btn" class="button button-secondary">
											<?php esc_html_e( 'Copia URL', 'fp-digital-marketing' ); ?>
										</button>
									</div>
								</div>
							</td>
						</tr>

						<!-- Status (for editing) -->
						<?php if ( $is_edit ) : ?>
							<tr>
								<th scope="row">
									<label for="status"><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></label>
								</th>
								<td>
									<select id="status" name="status" class="regular-text">
										<option value="active" <?php selected( $campaign->get_status(), 'active' ); ?>><?php esc_html_e( 'Attiva', 'fp-digital-marketing' ); ?></option>
										<option value="paused" <?php selected( $campaign->get_status(), 'paused' ); ?>><?php esc_html_e( 'In pausa', 'fp-digital-marketing' ); ?></option>
										<option value="completed" <?php selected( $campaign->get_status(), 'completed' ); ?>><?php esc_html_e( 'Completata', 'fp-digital-marketing' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__( 'Aggiorna Campagna', 'fp-digital-marketing' ) : esc_attr__( 'Salva Campagna', 'fp-digital-marketing' ); ?>">
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Annulla', 'fp-digital-marketing' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render campaign view page
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	private function render_campaign_view( int $campaign_id ): void {
		$campaign = UTMCampaign::find( $campaign_id );
		if ( ! $campaign ) {
			wp_die( __( 'Campagna non trovata.', 'fp-digital-marketing' ) );
		}

		$campaign_data = $campaign->to_array();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $campaign->get_campaign_name() ); ?></h1>

			<div class="utm-campaign-details">
				<div class="utm-campaign-info">
					<h2><?php esc_html_e( 'Dettagli Campagna', 'fp-digital-marketing' ); ?></h2>
					
					<table class="widefat">
						<tbody>
							<tr>
								<th><?php esc_html_e( 'Nome Campagna', 'fp-digital-marketing' ); ?></th>
								<td><?php echo esc_html( $campaign->get_campaign_name() ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'UTM Source', 'fp-digital-marketing' ); ?></th>
								<td><?php echo esc_html( $campaign_data['utm_source'] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'UTM Medium', 'fp-digital-marketing' ); ?></th>
								<td><?php echo esc_html( $campaign_data['utm_medium'] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'UTM Campaign', 'fp-digital-marketing' ); ?></th>
								<td><?php echo esc_html( $campaign_data['utm_campaign'] ); ?></td>
							</tr>
							<?php if ( $campaign_data['utm_term'] ) : ?>
								<tr>
									<th><?php esc_html_e( 'UTM Term', 'fp-digital-marketing' ); ?></th>
									<td><?php echo esc_html( $campaign_data['utm_term'] ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( $campaign_data['utm_content'] ) : ?>
								<tr>
									<th><?php esc_html_e( 'UTM Content', 'fp-digital-marketing' ); ?></th>
									<td><?php echo esc_html( $campaign_data['utm_content'] ); ?></td>
								</tr>
							<?php endif; ?>
							<tr>
								<th><?php esc_html_e( 'URL Base', 'fp-digital-marketing' ); ?></th>
								<td><a href="<?php echo esc_url( $campaign_data['base_url'] ); ?>" target="_blank"><?php echo esc_html( $campaign_data['base_url'] ); ?></a></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'URL Finale', 'fp-digital-marketing' ); ?></th>
								<td>
									<div class="utm-url-display">
										<input type="text" value="<?php echo esc_attr( $campaign->get_final_url() ); ?>" class="large-text" readonly>
										<button type="button" class="button button-secondary copy-url-btn" data-url="<?php echo esc_attr( $campaign->get_final_url() ); ?>">
											<?php esc_html_e( 'Copia', 'fp-digital-marketing' ); ?>
										</button>
									</div>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Stato', 'fp-digital-marketing' ); ?></th>
								<td>
									<span class="utm-status utm-status-<?php echo esc_attr( $campaign->get_status() ); ?>">
										<?php 
										$statuses = [
											'active'    => __( 'Attiva', 'fp-digital-marketing' ),
											'paused'    => __( 'In pausa', 'fp-digital-marketing' ),
											'completed' => __( 'Completata', 'fp-digital-marketing' ),
										];
										echo esc_html( $statuses[ $campaign->get_status() ] ?? $campaign->get_status() );
										?>
									</span>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Creata il', 'fp-digital-marketing' ); ?></th>
								<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $campaign_data['created_at'] ) ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="utm-campaign-stats">
					<h2><?php esc_html_e( 'Statistiche Campagna', 'fp-digital-marketing' ); ?></h2>
					
					<div class="utm-stats-grid">
						<div class="utm-stat-card">
							<div class="utm-stat-number"><?php echo esc_html( number_format( $campaign->get_clicks() ) ); ?></div>
							<div class="utm-stat-label"><?php esc_html_e( 'Click', 'fp-digital-marketing' ); ?></div>
						</div>
						<div class="utm-stat-card">
							<div class="utm-stat-number"><?php echo esc_html( number_format( $campaign->get_conversions() ) ); ?></div>
							<div class="utm-stat-label"><?php esc_html_e( 'Conversioni', 'fp-digital-marketing' ); ?></div>
						</div>
						<div class="utm-stat-card">
							<div class="utm-stat-number"><?php echo esc_html( number_format( $campaign->get_revenue(), 2 ) ); ?>€</div>
							<div class="utm-stat-label"><?php esc_html_e( 'Fatturato', 'fp-digital-marketing' ); ?></div>
						</div>
						<div class="utm-stat-card">
							<div class="utm-stat-number">
								<?php 
								$ctr = $campaign->get_clicks() > 0 ? ( $campaign->get_conversions() / $campaign->get_clicks() ) * 100 : 0;
								echo esc_html( number_format( $ctr, 2 ) );
								?>%
							</div>
							<div class="utm-stat-label"><?php esc_html_e( 'Tasso di Conversione', 'fp-digital-marketing' ); ?></div>
						</div>
					</div>
				</div>
			</div>

			<p class="utm-campaign-actions">
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'action' => 'edit', 'campaign_id' => $campaign->get_id() ], admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Modifica Campagna', 'fp-digital-marketing' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Torna alla Lista', 'fp-digital-marketing' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize campaign data from form
	 *
	 * @param array $data Raw form data.
	 * @return array Sanitized campaign data.
	 */
	private function sanitize_campaign_data( array $data ): array {
		return [
			'campaign_name' => sanitize_text_field( $data['campaign_name'] ?? '' ),
			'utm_source'    => sanitize_text_field( $data['utm_source'] ?? '' ),
			'utm_medium'    => sanitize_text_field( $data['utm_medium'] ?? '' ),
			'utm_campaign'  => sanitize_text_field( $data['utm_campaign'] ?? '' ),
			'utm_term'      => ! empty( $data['utm_term'] ) ? sanitize_text_field( $data['utm_term'] ) : null,
			'utm_content'   => ! empty( $data['utm_content'] ) ? sanitize_text_field( $data['utm_content'] ) : null,
			'base_url'      => esc_url_raw( $data['base_url'] ?? '' ),
			'preset_used'   => ! empty( $data['preset'] ) ? sanitize_text_field( $data['preset'] ) : null,
			'status'        => sanitize_text_field( $data['status'] ?? 'active' ),
		];
	}

	/**
	 * Add admin notice
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (success, error, warning, info).
	 * @return void
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		add_action( 'admin_notices', function() use ( $message, $type ) {
			printf( 
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( $message )
			);
		} );
	}

	/**
	 * Get inline CSS for UTM manager
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_css(): string {
		return '
			.utm-campaigns-table .utm-status {
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: bold;
				text-transform: uppercase;
			}
			.utm-status-active {
				background: #d4edda;
				color: #155724;
			}
			.utm-status-paused {
				background: #fff3cd;
				color: #856404;
			}
			.utm-status-completed {
				background: #d1ecf1;
				color: #0c5460;
			}
			.utm-url-preview {
				position: relative;
			}
			.utm-url-actions {
				margin-top: 5px;
			}
			.utm-url-actions .button {
				margin-right: 5px;
			}
			.utm-campaign-details {
				display: grid;
				grid-template-columns: 2fr 1fr;
				gap: 20px;
				margin-top: 20px;
			}
			.utm-stats-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 15px;
			}
			.utm-stat-card {
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 5px;
				padding: 15px;
				text-align: center;
			}
			.utm-stat-number {
				font-size: 24px;
				font-weight: bold;
				color: #0073aa;
			}
			.utm-stat-label {
				font-size: 12px;
				color: #666;
				margin-top: 5px;
			}
			.utm-url-display {
				display: flex;
				gap: 5px;
			}
			.utm-url-display input {
				flex: 1;
			}
			.utm-filters {
				margin: 15px 0;
			}
			.utm-filters .search-box {
				float: right;
				margin: 0;
			}
			.utm-filters select {
				margin-left: 5px;
			}
			@media (max-width: 768px) {
				.utm-campaign-details {
					grid-template-columns: 1fr;
				}
				.utm-stats-grid {
					grid-template-columns: 1fr;
				}
			}
		';
	}

	/**
	 * Get inline JavaScript for UTM manager
	 *
	 * @return string JavaScript code.
	 */
	private function get_inline_js(): string {
		return '
			jQuery(document).ready(function($) {
				// Load preset functionality
				$("#preset").on("change", function() {
					var presetId = $(this).val();
					if (!presetId) return;
					
					$.post(fpUtmAjax.ajaxurl, {
						action: "fp_utm_load_preset",
						preset_id: presetId,
						nonce: fpUtmAjax.nonce
					}, function(response) {
						if (response.success) {
							$("#utm_source").val(response.data.source || "");
							$("#utm_medium").val(response.data.medium || "");
							if (!$("#campaign_name").val()) {
								$("#campaign_name").val(response.data.name || "");
							}
						}
					});
				});

				// Generate URL functionality
				$("#generate_url_btn").on("click", function() {
					var $btn = $(this);
					var originalText = $btn.text();
					$btn.text(fpUtmAjax.strings.generating).prop("disabled", true);
					
					var data = {
						action: "fp_utm_generate_url",
						base_url: $("#base_url").val(),
						utm_source: $("#utm_source").val(),
						utm_medium: $("#utm_medium").val(),
						utm_campaign: $("#utm_campaign").val(),
						utm_term: $("#utm_term").val(),
						utm_content: $("#utm_content").val(),
						nonce: fpUtmAjax.nonce
					};
					
					$.post(fpUtmAjax.ajaxurl, data, function(response) {
						if (response.success) {
							$("#final_url_preview").val(response.data.final_url);
							if (!$("#campaign_name").val() && response.data.suggested_name) {
								$("#campaign_name").val(response.data.suggested_name);
							}
						} else {
							alert(response.data.message || fpUtmAjax.strings.error);
						}
					}).always(function() {
						$btn.text(originalText).prop("disabled", false);
					});
				});

				// Copy URL functionality
				$(document).on("click", "#copy_url_btn, .copy-url-btn", function() {
					var url = $(this).data("url") || $("#final_url_preview").val();
					if (!url) return;
					
					if (navigator.clipboard) {
						navigator.clipboard.writeText(url).then(function() {
							alert(fpUtmAjax.strings.url_copied);
						}, function() {
							alert(fpUtmAjax.strings.copy_failed);
						});
					} else {
						// Fallback for older browsers
						var $temp = $("<input>");
						$("body").append($temp);
						$temp.val(url).select();
						document.execCommand("copy");
						$temp.remove();
						alert(fpUtmAjax.strings.url_copied);
					}
				});

				// Delete campaign functionality
				$(".button-delete-campaign").on("click", function() {
					if (!confirm(fpUtmAjax.strings.confirm_delete)) return;
					
					var campaignId = $(this).data("campaign-id");
					var $row = $(this).closest("tr");
					
					$.post(fpUtmAjax.ajaxurl, {
						action: "fp_utm_delete_campaign",
						campaign_id: campaignId,
						nonce: fpUtmAjax.nonce
					}, function(response) {
						if (response.success) {
							$row.fadeOut(function() {
								$row.remove();
							});
						} else {
							alert(response.data.message || fpUtmAjax.strings.error);
						}
					});
				});

				// Auto-generate URL on field changes
				$("#base_url, #utm_source, #utm_medium, #utm_campaign, #utm_term, #utm_content").on("blur", function() {
					if ($("#base_url").val() && $("#utm_source").val() && $("#utm_medium").val() && $("#utm_campaign").val()) {
						$("#generate_url_btn").trigger("click");
					}
				});
			});
		';
	}
}