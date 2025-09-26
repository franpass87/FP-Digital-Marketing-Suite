<?php
/**
 * Cliente Meta Fields Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\PostTypes\ClientePostType;

/**
 * Cliente Meta Fields class
 */
class ClienteMeta {

	/**
	 * Meta field keys
	 */
	public const META_SETTORE            = '_cliente_settore';
	public const META_BUDGET             = '_cliente_budget_mensile';
	public const META_EMAIL              = '_cliente_email_riferimento';
	public const META_ATTIVO             = '_cliente_stato_attivo';
	public const META_CLARITY_PROJECT_ID = '_cliente_clarity_project_id';
	public const META_GOOGLE_PLACE_ID    = '_cliente_google_place_id';

	/**
	 * Nonce name for security
	 */
	private const NONCE_NAME = 'cliente_meta_nonce';

	/**
	 * Nonce action for security
	 */
	private const NONCE_ACTION = 'save_cliente_meta';

	/**
	 * Initialize the meta fields
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_fields' ] );
	}

	/**
	 * Add meta boxes to the Cliente post type
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'cliente-meta-fields',
			__( 'Informazioni Cliente', 'fp-digital-marketing' ),
			[ $this, 'render_meta_box' ],
			ClientePostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box content
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		// Add nonce for security.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		// Get current values.
		$settore                    = get_post_meta( $post->ID, self::META_SETTORE, true );
		$budget                     = get_post_meta( $post->ID, self::META_BUDGET, true );
		$email                      = get_post_meta( $post->ID, self::META_EMAIL, true );
		$attivo                     = get_post_meta( $post->ID, self::META_ATTIVO, true );
				$clarity_project_id = get_post_meta( $post->ID, self::META_CLARITY_PROJECT_ID, true );
				$google_place_id    = get_post_meta( $post->ID, self::META_GOOGLE_PLACE_ID, true );

		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cliente_settore"><?php esc_html_e( 'Settore', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input 
							type="text" 
							id="cliente_settore" 
							name="cliente_settore" 
							value="<?php echo esc_attr( $settore ); ?>" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'Es: Tecnologia, Retail, Servizi...', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Specifica il settore di attività del cliente.', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cliente_budget"><?php esc_html_e( 'Budget Mensile (€)', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input 
							type="number" 
							id="cliente_budget" 
							name="cliente_budget" 
							value="<?php echo esc_attr( $budget ); ?>" 
							class="regular-text" 
							min="0" 
							step="0.01"
							placeholder="<?php esc_attr_e( '0.00', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Budget mensile disponibile per le attività di marketing digitale.', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cliente_email"><?php esc_html_e( 'Email di Riferimento', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<input 
							type="email" 
							id="cliente_email" 
							name="cliente_email" 
							value="<?php echo esc_attr( $email ); ?>" 
							class="regular-text" 
							placeholder="contatto@<?php echo esc_attr( parse_url( home_url(), PHP_URL_HOST ) ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Indirizzo email del contatto principale per questo cliente.', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cliente_attivo"><?php esc_html_e( 'Stato Cliente', 'fp-digital-marketing' ); ?></label>
					</th>
					<td>
						<label for="cliente_attivo">
							<input 
								type="checkbox" 
								id="cliente_attivo" 
								name="cliente_attivo" 
								value="1" 
								<?php checked( $attivo, '1' ); ?>
							/>
							<?php esc_html_e( 'Cliente Attivo', 'fp-digital-marketing' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Indica se il cliente è attualmente attivo per le attività di marketing.', 'fp-digital-marketing' ); ?>
						</p>
					</td>
				</tr>
								<tr>
										<th scope="row">
												<label for="cliente_clarity_project_id"><?php esc_html_e( 'Microsoft Clarity Project ID', 'fp-digital-marketing' ); ?></label>
										</th>
										<td>
						<input 
							type="text" 
							id="cliente_clarity_project_id" 
							name="cliente_clarity_project_id" 
							value="<?php echo esc_attr( $clarity_project_id ); ?>" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'es: abc123def456', 'fp-digital-marketing' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Project ID di Microsoft Clarity per monitorare il sito web di questo cliente. Utilizzato per recuperare dati analitici dal sito del cliente.', 'fp-digital-marketing' ); ?>
												</p>
										</td>
								</tr>
								<tr>
										<th scope="row">
												<label for="cliente_google_place_id"><?php esc_html_e( 'Google Place ID', 'fp-digital-marketing' ); ?></label>
										</th>
										<td>
												<input
														type="text"
														id="cliente_google_place_id"
														name="cliente_google_place_id"
														value="<?php echo esc_attr( $google_place_id ); ?>"
														class="regular-text"
														placeholder="<?php esc_attr_e( 'es: ChIJN1t_tDeuEmsRUsoyG83frY4', 'fp-digital-marketing' ); ?>"
												/>
												<p class="description">
														<?php esc_html_e( 'Identificatore utilizzato per recuperare le recensioni Google del cliente e calcolare il sentiment reale.', 'fp-digital-marketing' ); ?>
												</p>
												<p class="description">
														<?php esc_html_e( 'Puoi trovare il Place ID con lo strumento ufficiale Google Place ID Finder.', 'fp-digital-marketing' ); ?>
												</p>
										</td>
								</tr>
						</tbody>
				</table>
				<?php
	}

	/**
	 * Save the meta fields
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function save_meta_fields( int $post_id ): void {
		// Security checks.
		if ( ! $this->can_save_meta( $post_id ) ) {
			return;
		}

		// Sanitize and save fields.
		$this->save_settore( $post_id );
		$this->save_budget( $post_id );
		$this->save_email( $post_id );
		$this->save_attivo( $post_id );
				$this->save_clarity_project_id( $post_id );
				$this->save_google_place_id( $post_id );
	}

	/**
	 * Check if we can save meta data
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if we can save, false otherwise.
	 */
	private function can_save_meta( int $post_id ): bool {
		// Check if nonce is set.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return false;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return false;
		}

		// Check if user has permission to edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Check if this is the correct post type.
		if ( get_post_type( $post_id ) !== ClientePostType::POST_TYPE ) {
			return false;
		}

		return true;
	}

	/**
	 * Save settore field
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function save_settore( int $post_id ): void {
		if ( isset( $_POST['cliente_settore'] ) ) {
			$settore = sanitize_text_field( wp_unslash( $_POST['cliente_settore'] ) );
			update_post_meta( $post_id, self::META_SETTORE, $settore );
		}
	}

	/**
	 * Save budget field
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function save_budget( int $post_id ): void {
		if ( isset( $_POST['cliente_budget'] ) ) {
			$budget = sanitize_text_field( wp_unslash( $_POST['cliente_budget'] ) );

			// Validate that it's a valid number.
			if ( is_numeric( $budget ) && (float) $budget >= 0 ) {
				update_post_meta( $post_id, self::META_BUDGET, (float) $budget );
			} else {
				delete_post_meta( $post_id, self::META_BUDGET );
			}
		}
	}

	/**
	 * Save email field
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function save_email( int $post_id ): void {
		if ( isset( $_POST['cliente_email'] ) ) {
			$email = sanitize_email( wp_unslash( $_POST['cliente_email'] ) );

			// Validate email format.
			if ( is_email( $email ) ) {
				update_post_meta( $post_id, self::META_EMAIL, $email );
			} else {
				delete_post_meta( $post_id, self::META_EMAIL );
			}
		}
	}

	/**
	 * Save attivo field
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function save_attivo( int $post_id ): void {
		$attivo = isset( $_POST['cliente_attivo'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_ATTIVO, $attivo );
	}

	/**
	 * Save Clarity Project ID field
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function save_clarity_project_id( int $post_id ): void {
		if ( isset( $_POST['cliente_clarity_project_id'] ) ) {
				$project_id = sanitize_text_field( wp_unslash( $_POST['cliente_clarity_project_id'] ) );

				// Validate Project ID format using MicrosoftClarity validation
			if ( ! empty( $project_id ) && \FP\DigitalMarketing\DataSources\MicrosoftClarity::validate_project_id( $project_id ) ) {
				update_post_meta( $post_id, self::META_CLARITY_PROJECT_ID, $project_id );
			} else {
					delete_post_meta( $post_id, self::META_CLARITY_PROJECT_ID );
			}
		}
	}

		/**
		 * Save Google Place ID field
		 *
		 * @param int $post_id The post ID.
		 * @return void
		 */
	private function save_google_place_id( int $post_id ): void {
		if ( ! isset( $_POST['cliente_google_place_id'] ) ) {
				return;
		}

			$place_id = sanitize_text_field( wp_unslash( $_POST['cliente_google_place_id'] ) );

		if ( '' === $place_id ) {
				delete_post_meta( $post_id, self::META_GOOGLE_PLACE_ID );

				return;
		}

			update_post_meta( $post_id, self::META_GOOGLE_PLACE_ID, $place_id );
	}
}