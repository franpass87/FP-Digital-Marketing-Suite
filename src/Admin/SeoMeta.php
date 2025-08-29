<?php
/**
 * SEO Meta Fields Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use FP\DigitalMarketing\Helpers\SeoMetadata;
use FP\DigitalMarketing\Helpers\Capabilities;

/**
 * SEO Meta Fields class for managing SEO metadata in WordPress admin
 */
class SeoMeta {

	/**
	 * Nonce name for security
	 */
	private const NONCE_NAME = 'seo_meta_nonce';

	/**
	 * Nonce action for security
	 */
	private const NONCE_ACTION = 'save_seo_meta';

	/**
	 * Initialize the meta fields
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add meta boxes to post types
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'seo-meta-fields',
				__( 'SEO e Social Media', 'fp-digital-marketing' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'normal',
				'high'
			);
		}
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
		$seo_title = get_post_meta( $post->ID, SeoMetadata::META_TITLE, true );
		$seo_description = get_post_meta( $post->ID, SeoMetadata::META_DESCRIPTION, true );
		$seo_robots = get_post_meta( $post->ID, SeoMetadata::META_ROBOTS, true );
		$seo_canonical = get_post_meta( $post->ID, SeoMetadata::META_CANONICAL, true );

		$og_title = get_post_meta( $post->ID, SeoMetadata::META_OG_TITLE, true );
		$og_description = get_post_meta( $post->ID, SeoMetadata::META_OG_DESCRIPTION, true );
		$og_image = get_post_meta( $post->ID, SeoMetadata::META_OG_IMAGE, true );

		$twitter_title = get_post_meta( $post->ID, SeoMetadata::META_TWITTER_TITLE, true );
		$twitter_description = get_post_meta( $post->ID, SeoMetadata::META_TWITTER_DESCRIPTION, true );
		$twitter_image = get_post_meta( $post->ID, SeoMetadata::META_TWITTER_IMAGE, true );

		// Get preview data for fallbacks.
		$preview_title = SeoMetadata::get_title( $post );
		$preview_description = SeoMetadata::get_description( $post );
		$preview_og_title = SeoMetadata::get_og_title( $post );
		$preview_og_description = SeoMetadata::get_og_description( $post );
		$preview_og_image = SeoMetadata::get_og_image( $post );

		?>
		<div class="seo-meta-container">
			<div class="seo-tabs">
				<ul class="seo-tab-nav">
					<li class="seo-tab-nav-item active" data-tab="basic-seo">
						<?php esc_html_e( 'SEO Base', 'fp-digital-marketing' ); ?>
					</li>
					<li class="seo-tab-nav-item" data-tab="social-media">
						<?php esc_html_e( 'Social Media', 'fp-digital-marketing' ); ?>
					</li>
					<li class="seo-tab-nav-item" data-tab="advanced">
						<?php esc_html_e( 'Avanzate', 'fp-digital-marketing' ); ?>
					</li>
					<li class="seo-tab-nav-item" data-tab="preview">
						<?php esc_html_e( 'Anteprima', 'fp-digital-marketing' ); ?>
					</li>
				</ul>

				<!-- Basic SEO Tab -->
				<div class="seo-tab-content active" id="basic-seo">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="seo_title">
										<?php esc_html_e( 'Titolo SEO', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="text" 
										id="seo_title" 
										name="seo_title" 
										value="<?php echo esc_attr( $seo_title ); ?>" 
										class="large-text seo-field" 
										data-counter="title"
										data-limit="<?php echo esc_attr( SeoMetadata::TITLE_MAX_LENGTH ); ?>"
										placeholder="<?php echo esc_attr( $preview_title ); ?>"
									/>
									<p class="description">
										<span class="seo-counter" id="title-counter"></span>
										<br>
										<?php esc_html_e( 'Lascia vuoto per usare il titolo automatico.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="seo_description">
										<?php esc_html_e( 'Meta Description', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<textarea 
										id="seo_description" 
										name="seo_description" 
										rows="3" 
										class="large-text seo-field" 
										data-counter="description"
										data-limit="<?php echo esc_attr( SeoMetadata::DESCRIPTION_MAX_LENGTH ); ?>"
										placeholder="<?php echo esc_attr( $preview_description ); ?>"
									><?php echo esc_textarea( $seo_description ); ?></textarea>
									<p class="description">
										<span class="seo-counter" id="description-counter"></span>
										<br>
										<?php esc_html_e( 'Descrizione mostrata nei risultati di ricerca.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Social Media Tab -->
				<div class="seo-tab-content" id="social-media">
					<h4><?php esc_html_e( 'Open Graph (Facebook)', 'fp-digital-marketing' ); ?></h4>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="og_title">
										<?php esc_html_e( 'Titolo OG', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="text" 
										id="og_title" 
										name="og_title" 
										value="<?php echo esc_attr( $og_title ); ?>" 
										class="large-text seo-field" 
										data-counter="og-title"
										data-limit="<?php echo esc_attr( SeoMetadata::OG_TITLE_MAX_LENGTH ); ?>"
										placeholder="<?php echo esc_attr( $preview_og_title ); ?>"
									/>
									<p class="description">
										<span class="seo-counter" id="og-title-counter"></span>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="og_description">
										<?php esc_html_e( 'Descrizione OG', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<textarea 
										id="og_description" 
										name="og_description" 
										rows="3" 
										class="large-text seo-field" 
										data-counter="og-description"
										data-limit="<?php echo esc_attr( SeoMetadata::OG_DESCRIPTION_MAX_LENGTH ); ?>"
										placeholder="<?php echo esc_attr( $preview_og_description ); ?>"
									><?php echo esc_textarea( $og_description ); ?></textarea>
									<p class="description">
										<span class="seo-counter" id="og-description-counter"></span>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="og_image">
										<?php esc_html_e( 'Immagine OG', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="url" 
										id="og_image" 
										name="og_image" 
										value="<?php echo esc_attr( $og_image ); ?>" 
										class="large-text"
										placeholder="<?php echo esc_attr( $preview_og_image ); ?>"
									/>
									<p class="description">
										<?php esc_html_e( 'URL immagine per condivisioni Facebook.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( 'Twitter Cards', 'fp-digital-marketing' ); ?></h4>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="twitter_title">
										<?php esc_html_e( 'Titolo Twitter', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="text" 
										id="twitter_title" 
										name="twitter_title" 
										value="<?php echo esc_attr( $twitter_title ); ?>" 
										class="large-text seo-field" 
										data-counter="twitter-title"
										data-limit="<?php echo esc_attr( SeoMetadata::TWITTER_TITLE_MAX_LENGTH ); ?>"
									/>
									<p class="description">
										<span class="seo-counter" id="twitter-title-counter"></span>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="twitter_description">
										<?php esc_html_e( 'Descrizione Twitter', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<textarea 
										id="twitter_description" 
										name="twitter_description" 
										rows="3" 
										class="large-text seo-field" 
										data-counter="twitter-description"
										data-limit="<?php echo esc_attr( SeoMetadata::TWITTER_DESCRIPTION_MAX_LENGTH ); ?>"
									><?php echo esc_textarea( $twitter_description ); ?></textarea>
									<p class="description">
										<span class="seo-counter" id="twitter-description-counter"></span>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="twitter_image">
										<?php esc_html_e( 'Immagine Twitter', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="url" 
										id="twitter_image" 
										name="twitter_image" 
										value="<?php echo esc_attr( $twitter_image ); ?>" 
										class="large-text"
									/>
									<p class="description">
										<?php esc_html_e( 'URL immagine per Twitter Cards.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Advanced Tab -->
				<div class="seo-tab-content" id="advanced">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="seo_robots">
										<?php esc_html_e( 'Meta Robots', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<select id="seo_robots" name="seo_robots">
										<option value=""><?php esc_html_e( 'Default (index, follow)', 'fp-digital-marketing' ); ?></option>
										<option value="index, follow" <?php selected( $seo_robots, 'index, follow' ); ?>>
											<?php esc_html_e( 'Index, Follow', 'fp-digital-marketing' ); ?>
										</option>
										<option value="noindex, follow" <?php selected( $seo_robots, 'noindex, follow' ); ?>>
											<?php esc_html_e( 'No Index, Follow', 'fp-digital-marketing' ); ?>
										</option>
										<option value="index, nofollow" <?php selected( $seo_robots, 'index, nofollow' ); ?>>
											<?php esc_html_e( 'Index, No Follow', 'fp-digital-marketing' ); ?>
										</option>
										<option value="noindex, nofollow" <?php selected( $seo_robots, 'noindex, nofollow' ); ?>>
											<?php esc_html_e( 'No Index, No Follow', 'fp-digital-marketing' ); ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Controlla come i motori di ricerca indicizzano questa pagina.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="seo_canonical">
										<?php esc_html_e( 'URL Canonical', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="url" 
										id="seo_canonical" 
										name="seo_canonical" 
										value="<?php echo esc_attr( $seo_canonical ); ?>" 
										class="large-text"
										placeholder="<?php echo esc_attr( SeoMetadata::get_canonical( $post ) ); ?>"
									/>
									<p class="description">
										<?php esc_html_e( 'URL canonico per evitare contenuti duplicati.', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Preview Tab -->
				<div class="seo-tab-content" id="preview">
					<h4><?php esc_html_e( 'Anteprima Risultati di Ricerca', 'fp-digital-marketing' ); ?></h4>
					<div class="seo-preview-search">
						<div class="search-result-preview">
							<div class="search-title" id="search-preview-title"><?php echo esc_html( $preview_title ); ?></div>
							<div class="search-url" id="search-preview-url"><?php echo esc_html( SeoMetadata::get_canonical( $post ) ); ?></div>
							<div class="search-description" id="search-preview-description"><?php echo esc_html( $preview_description ); ?></div>
						</div>
					</div>

					<h4><?php esc_html_e( 'Anteprima Facebook', 'fp-digital-marketing' ); ?></h4>
					<div class="seo-preview-facebook">
						<div class="facebook-preview">
							<?php if ( $preview_og_image ): ?>
								<div class="fb-image">
									<img src="<?php echo esc_url( $preview_og_image ); ?>" alt="" style="max-width: 100%; height: auto;" />
								</div>
							<?php endif; ?>
							<div class="fb-content">
								<div class="fb-title" id="fb-preview-title"><?php echo esc_html( $preview_og_title ); ?></div>
								<div class="fb-description" id="fb-preview-description"><?php echo esc_html( $preview_og_description ); ?></div>
								<div class="fb-domain"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
							</div>
						</div>
					</div>

					<h4><?php esc_html_e( 'Anteprima Twitter', 'fp-digital-marketing' ); ?></h4>
					<div class="seo-preview-twitter">
						<div class="twitter-preview">
							<?php if ( $preview_og_image ): ?>
								<div class="twitter-image">
									<img src="<?php echo esc_url( $preview_og_image ); ?>" alt="" style="max-width: 100%; height: auto;" />
								</div>
							<?php endif; ?>
							<div class="twitter-content">
								<div class="twitter-title" id="twitter-preview-title"><?php echo esc_html( SeoMetadata::get_twitter_title( $post ) ); ?></div>
								<div class="twitter-description" id="twitter-preview-description"><?php echo esc_html( SeoMetadata::get_twitter_description( $post ) ); ?></div>
								<div class="twitter-domain"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
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

		// Save SEO fields.
		$this->save_field( $post_id, SeoMetadata::META_TITLE, 'seo_title' );
		$this->save_field( $post_id, SeoMetadata::META_DESCRIPTION, 'seo_description' );
		$this->save_field( $post_id, SeoMetadata::META_ROBOTS, 'seo_robots' );
		$this->save_field( $post_id, SeoMetadata::META_CANONICAL, 'seo_canonical', 'url' );

		// Save Open Graph fields.
		$this->save_field( $post_id, SeoMetadata::META_OG_TITLE, 'og_title' );
		$this->save_field( $post_id, SeoMetadata::META_OG_DESCRIPTION, 'og_description' );
		$this->save_field( $post_id, SeoMetadata::META_OG_IMAGE, 'og_image', 'url' );

		// Save Twitter fields.
		$this->save_field( $post_id, SeoMetadata::META_TWITTER_TITLE, 'twitter_title' );
		$this->save_field( $post_id, SeoMetadata::META_TWITTER_DESCRIPTION, 'twitter_description' );
		$this->save_field( $post_id, SeoMetadata::META_TWITTER_IMAGE, 'twitter_image', 'url' );
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->get_admin_styles() );
		wp_add_inline_script( 'jquery', $this->get_admin_scripts() );
	}

	/**
	 * Check if we can save meta data
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if we can save, false otherwise.
	 */
	private function can_save_meta( int $post_id ): bool {
		// Check if nonce is valid.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || 
			 ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return false;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Save a single meta field
	 *
	 * @param int    $post_id The post ID.
	 * @param string $meta_key The meta key.
	 * @param string $field_name The form field name.
	 * @param string $type The field type for sanitization.
	 * @return void
	 */
	private function save_field( int $post_id, string $meta_key, string $field_name, string $type = 'text' ): void {
		if ( ! isset( $_POST[ $field_name ] ) ) {
			return;
		}

		$value = $_POST[ $field_name ];

		switch ( $type ) {
			case 'url':
				$value = esc_url_raw( $value );
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $value );
				break;
			default:
				$value = sanitize_text_field( $value );
				break;
		}

		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Get admin styles
	 *
	 * @return string CSS styles.
	 */
	private function get_admin_styles(): string {
		return '
			.seo-meta-container {
				margin: 10px 0;
			}
			
			.seo-tabs {
				background: #fff;
			}
			
			.seo-tab-nav {
				margin: 0;
				padding: 0;
				list-style: none;
				border-bottom: 1px solid #ccd0d4;
				display: flex;
			}
			
			.seo-tab-nav-item {
				margin: 0;
				padding: 10px 15px;
				background: #f1f1f1;
				border: 1px solid #ccd0d4;
				border-bottom: none;
				cursor: pointer;
				margin-right: 2px;
			}
			
			.seo-tab-nav-item.active {
				background: #fff;
				border-bottom: 1px solid #fff;
				margin-bottom: -1px;
			}
			
			.seo-tab-content {
				display: none;
				padding: 20px 0;
			}
			
			.seo-tab-content.active {
				display: block;
			}
			
			.seo-counter {
				font-weight: bold;
			}
			
			.seo-counter.warning {
				color: #d63638;
			}
			
			.seo-counter.good {
				color: #00a32a;
			}
			
			.search-result-preview {
				border: 1px solid #e0e0e0;
				padding: 15px;
				background: #f9f9f9;
				max-width: 600px;
			}
			
			.search-title {
				color: #1a0dab;
				font-size: 18px;
				font-weight: normal;
				margin-bottom: 5px;
			}
			
			.search-url {
				color: #006621;
				font-size: 14px;
				margin-bottom: 5px;
			}
			
			.search-description {
				color: #545454;
				font-size: 14px;
				line-height: 1.4;
			}
			
			.facebook-preview, .twitter-preview {
				border: 1px solid #e0e0e0;
				max-width: 500px;
				background: #fff;
			}
			
			.facebook-preview .fb-content, .twitter-preview .twitter-content {
				padding: 15px;
			}
			
			.fb-title, .twitter-title {
				font-weight: bold;
				margin-bottom: 5px;
				color: #1877f2;
			}
			
			.twitter-title {
				color: #1da1f2;
			}
			
			.fb-description, .twitter-description {
				color: #65676b;
				font-size: 14px;
				margin-bottom: 5px;
			}
			
			.fb-domain, .twitter-domain {
				color: #65676b;
				font-size: 12px;
				text-transform: uppercase;
			}
		';
	}

	/**
	 * Get admin JavaScript
	 *
	 * @return string JavaScript code.
	 */
	private function get_admin_scripts(): string {
		return '
			jQuery(document).ready(function($) {
				// Tab switching
				$(".seo-tab-nav-item").click(function() {
					var tab = $(this).data("tab");
					
					$(".seo-tab-nav-item").removeClass("active");
					$(this).addClass("active");
					
					$(".seo-tab-content").removeClass("active");
					$("#" + tab).addClass("active");
				});
				
				// Character counting
				function updateCounter(field) {
					var $field = $(field);
					var content = $field.val();
					var limit = parseInt($field.data("limit"));
					var counter = $field.data("counter");
					var length = content.length;
					
					var $counter = $("#" + counter + "-counter");
					$counter.removeClass("warning good");
					
					if (length > limit) {
						$counter.addClass("warning");
						$counter.text(length + "/" + limit + " caratteri (" + (length - limit) + " in eccesso)");
					} else {
						$counter.addClass("good");
						$counter.text(length + "/" + limit + " caratteri");
					}
				}
				
				// Initialize counters
				$(".seo-field[data-counter]").each(function() {
					updateCounter(this);
				});
				
				// Update counters on input
				$(".seo-field[data-counter]").on("input", function() {
					updateCounter(this);
					updatePreviews();
				});
				
				// Update preview content
				function updatePreviews() {
					var title = $("#seo_title").val() || $("#seo_title").attr("placeholder");
					var description = $("#seo_description").val() || $("#seo_description").attr("placeholder");
					var ogTitle = $("#og_title").val() || title;
					var ogDescription = $("#og_description").val() || description;
					var twitterTitle = $("#twitter_title").val() || ogTitle;
					var twitterDescription = $("#twitter_description").val() || ogDescription;
					
					$("#search-preview-title").text(title);
					$("#search-preview-description").text(description);
					$("#fb-preview-title").text(ogTitle);
					$("#fb-preview-description").text(ogDescription);
					$("#twitter-preview-title").text(twitterTitle);
					$("#twitter-preview-description").text(twitterDescription);
				}
			});
		';
	}
}