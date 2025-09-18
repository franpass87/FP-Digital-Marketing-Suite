<?php
/**
 * SEO Meta Fields Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Admin;

use Exception;
use FP\DigitalMarketing\Helpers\SeoMetadata;
use FP\DigitalMarketing\Helpers\Capabilities;
use FP\DigitalMarketing\Helpers\ContentSeoAnalyzer;

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
		add_action( 'wp_ajax_fp_analyze_content_seo', [ $this, 'ajax_analyze_content_seo' ] );
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

		// Get focus keyword and content analysis data.
		$focus_keyword = get_post_meta( $post->ID, SeoMetadata::META_FOCUS_KEYWORD, true );
		$saved_analysis = ContentSeoAnalyzer::get_saved_analysis( $post->ID );
		
		// Perform fresh analysis if we have a focus keyword
		$current_analysis = $saved_analysis;
		if ( ! empty( $focus_keyword ) ) {
			$current_analysis = ContentSeoAnalyzer::analyze_content( $post, $focus_keyword );
		}

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
					<li class="seo-tab-nav-item" data-tab="content-analysis">
						<?php esc_html_e( 'Analisi Contenuto', 'fp-digital-marketing' ); ?>
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

				<!-- Content Analysis Tab -->
				<div class="seo-tab-content" id="content-analysis">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="focus_keyword">
										<?php esc_html_e( 'Parola Chiave Focus', 'fp-digital-marketing' ); ?>
									</label>
								</th>
								<td>
									<input 
										type="text" 
										id="focus_keyword" 
										name="focus_keyword" 
										value="<?php echo esc_attr( $focus_keyword ); ?>" 
										class="large-text seo-field" 
										placeholder="<?php esc_attr_e( 'es. marketing digitale', 'fp-digital-marketing' ); ?>"
									/>
									<p class="description">
										<?php esc_html_e( 'La parola chiave principale su cui vuoi ottimizzare questo contenuto', 'fp-digital-marketing' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php if ( ! empty( $focus_keyword ) && ! empty( $current_analysis['overall_score'] ) ): ?>
					<div class="seo-analysis-results">
						<h4><?php esc_html_e( 'Risultati Analisi SEO', 'fp-digital-marketing' ); ?></h4>
						
						<!-- Overall Score -->
						<div class="seo-score-overview">
							<div class="score-circle score-<?php echo esc_attr( strtolower( $current_analysis['grade'] ) ); ?>">
								<span class="score-number"><?php echo esc_html( $current_analysis['overall_score'] ); ?></span>
								<span class="score-grade"><?php echo esc_html( $current_analysis['grade'] ); ?></span>
							</div>
							<div class="score-breakdown">
								<div class="score-item">
									<span class="score-label"><?php esc_html_e( 'SEO Keywords:', 'fp-digital-marketing' ); ?></span>
									<span class="score-value"><?php echo esc_html( $current_analysis['keyword_score'] ); ?>/100</span>
								</div>
								<div class="score-item">
									<span class="score-label"><?php esc_html_e( 'Leggibilità:', 'fp-digital-marketing' ); ?></span>
									<span class="score-value"><?php echo esc_html( $current_analysis['readability_score'] ); ?>/100</span>
								</div>
							</div>
						</div>

						<!-- Keyword Analysis Details -->
						<div class="seo-analysis-section">
							<h5><?php esc_html_e( 'Analisi Parole Chiave', 'fp-digital-marketing' ); ?></h5>
							<div class="seo-checks">
								<?php if ( isset( $current_analysis['keyword_analysis']['title'] ) ): ?>
								<div class="seo-check <?php echo $current_analysis['keyword_analysis']['title']['present'] ? 'check-pass' : 'check-fail'; ?>">
									<span class="dashicons <?php echo $current_analysis['keyword_analysis']['title']['present'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
									<span class="check-label"><?php esc_html_e( 'Titolo', 'fp-digital-marketing' ); ?></span>
									<span class="check-status">
										<?php echo $current_analysis['keyword_analysis']['title']['present'] ? 
											esc_html__( 'Keyword presente', 'fp-digital-marketing' ) : 
											esc_html__( 'Keyword mancante', 'fp-digital-marketing' ); ?>
									</span>
								</div>
								<?php endif; ?>

								<?php if ( isset( $current_analysis['keyword_analysis']['h1'] ) ): ?>
								<div class="seo-check <?php echo $current_analysis['keyword_analysis']['h1']['present'] ? 'check-pass' : 'check-fail'; ?>">
									<span class="dashicons <?php echo $current_analysis['keyword_analysis']['h1']['present'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
									<span class="check-label"><?php esc_html_e( 'H1', 'fp-digital-marketing' ); ?></span>
									<span class="check-status">
										<?php echo $current_analysis['keyword_analysis']['h1']['present'] ? 
											esc_html__( 'Keyword presente', 'fp-digital-marketing' ) : 
											esc_html__( 'Keyword mancante', 'fp-digital-marketing' ); ?>
									</span>
								</div>
								<?php endif; ?>

								<?php if ( isset( $current_analysis['keyword_analysis']['meta_description'] ) ): ?>
								<div class="seo-check <?php echo $current_analysis['keyword_analysis']['meta_description']['present'] ? 'check-pass' : 'check-fail'; ?>">
									<span class="dashicons <?php echo $current_analysis['keyword_analysis']['meta_description']['present'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
									<span class="check-label"><?php esc_html_e( 'Meta Description', 'fp-digital-marketing' ); ?></span>
									<span class="check-status">
										<?php echo $current_analysis['keyword_analysis']['meta_description']['present'] ? 
											esc_html__( 'Keyword presente', 'fp-digital-marketing' ) : 
											esc_html__( 'Keyword mancante', 'fp-digital-marketing' ); ?>
									</span>
								</div>
								<?php endif; ?>

								<?php if ( isset( $current_analysis['keyword_analysis']['content_density'] ) ): ?>
								<div class="seo-check">
									<span class="dashicons dashicons-chart-pie"></span>
									<span class="check-label"><?php esc_html_e( 'Densità Keyword', 'fp-digital-marketing' ); ?></span>
									<span class="check-status">
										<?php printf( 
											esc_html__( '%s%% (%d occorrenze)', 'fp-digital-marketing' ),
											esc_html( $current_analysis['keyword_analysis']['content_density']['density'] ),
											esc_html( $current_analysis['keyword_analysis']['content_density']['keyword_count'] )
										); ?>
									</span>
								</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Readability Analysis -->
						<div class="seo-analysis-section">
							<h5><?php esc_html_e( 'Analisi Leggibilità', 'fp-digital-marketing' ); ?></h5>
							<div class="readability-info">
								<?php if ( isset( $current_analysis['readability_analysis']['flesch_score'] ) ): ?>
								<div class="readability-item">
									<span class="readability-label"><?php esc_html_e( 'Punteggio Flesch:', 'fp-digital-marketing' ); ?></span>
									<span class="readability-value">
										<?php echo esc_html( round( $current_analysis['readability_analysis']['flesch_score'] ) ); ?>
										(<?php echo esc_html( $current_analysis['readability_analysis']['flesch_grade'] ?? '' ); ?>)
									</span>
								</div>
								<?php endif; ?>

								<?php if ( isset( $current_analysis['readability_analysis']['paragraph_analysis'] ) ): ?>
								<div class="readability-item">
									<span class="readability-label"><?php esc_html_e( 'Lunghezza Paragrafi:', 'fp-digital-marketing' ); ?></span>
									<span class="readability-value">
										<?php printf(
											esc_html__( 'Media %d parole/paragrafo', 'fp-digital-marketing' ),
											esc_html( $current_analysis['readability_analysis']['paragraph_analysis']['average_length'] ?? 0 )
										); ?>
									</span>
								</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Suggestions -->
						<?php if ( ! empty( $current_analysis['suggestions'] ) ): ?>
						<div class="seo-analysis-section">
							<h5><?php esc_html_e( 'Suggerimenti di Miglioramento', 'fp-digital-marketing' ); ?></h5>
							<div class="seo-suggestions">
								<?php foreach ( $current_analysis['suggestions'] as $suggestion ): ?>
								<div class="seo-suggestion priority-<?php echo esc_attr( $suggestion['priority'] ); ?>">
									<span class="suggestion-priority"><?php echo esc_html( ucfirst( $suggestion['priority'] ) ); ?></span>
									<span class="suggestion-message"><?php echo esc_html( $suggestion['message'] ); ?></span>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>
					</div>
					<?php else: ?>
					<div class="seo-analysis-placeholder">
						<p><?php esc_html_e( 'Inserisci una parola chiave focus per iniziare l\'analisi del contenuto.', 'fp-digital-marketing' ); ?></p>
					</div>
					<?php endif; ?>
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

		// Save focus keyword and perform content analysis.
		$this->save_field( $post_id, SeoMetadata::META_FOCUS_KEYWORD, 'focus_keyword' );
		
		// Perform and save content analysis if focus keyword is provided.
		$focus_keyword = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( $_POST['focus_keyword'] ) : '';
		if ( ! empty( $focus_keyword ) ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$analysis = ContentSeoAnalyzer::analyze_content( $post, $focus_keyword );
				ContentSeoAnalyzer::save_analysis( $post_id, $analysis );
			}
		}
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
		
		// Add AJAX data for live analysis
		wp_localize_script( 'jquery', 'fpSeoAnalysis', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fp_seo_analysis' ),
			'post_id' => get_the_ID() ?: 0,
		] );
		
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
			
			/* Content Analysis Styles */
			.seo-analysis-results {
				margin-top: 20px;
			}
			
			.seo-score-overview {
				display: flex;
				align-items: center;
				gap: 20px;
				margin-bottom: 20px;
				padding: 15px;
				background: #f8f9fa;
				border-left: 4px solid #0073aa;
			}
			
			.score-circle {
				width: 80px;
				height: 80px;
				border-radius: 50%;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				color: white;
				font-weight: bold;
			}
			
			.score-circle.score-a { background: #46b450; }
			.score-circle.score-b { background: #00a32a; }
			.score-circle.score-c { background: #dba617; }
			.score-circle.score-d { background: #d54e21; }
			.score-circle.score-f { background: #dc3232; }
			
			.score-number {
				font-size: 24px;
				line-height: 1;
			}
			
			.score-grade {
				font-size: 16px;
				line-height: 1;
			}
			
			.score-breakdown {
				flex: 1;
			}
			
			.score-item {
				display: flex;
				justify-content: space-between;
				margin-bottom: 8px;
				font-size: 14px;
			}
			
			.score-label {
				font-weight: 600;
			}
			
			.score-value {
				color: #666;
			}
			
			.seo-analysis-section {
				margin-bottom: 25px;
				padding: 15px;
				background: #fff;
				border: 1px solid #e1e1e1;
			}
			
			.seo-analysis-section h5 {
				margin-top: 0;
				margin-bottom: 15px;
				color: #23282d;
				font-size: 14px;
				font-weight: 600;
			}
			
			.seo-checks {
				display: grid;
				gap: 10px;
			}
			
			.seo-check {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 8px 12px;
				border-radius: 4px;
				background: #f9f9f9;
			}
			
			.seo-check.check-pass {
				background: #d4edda;
				border-left: 3px solid #28a745;
			}
			
			.seo-check.check-fail {
				background: #f8d7da;
				border-left: 3px solid #dc3545;
			}
			
			.seo-check .dashicons {
				width: 16px;
				height: 16px;
				font-size: 16px;
			}
			
			.check-pass .dashicons-yes {
				color: #28a745;
			}
			
			.check-fail .dashicons-no {
				color: #dc3545;
			}
			
			.check-label {
				font-weight: 600;
				min-width: 120px;
			}
			
			.check-status {
				color: #666;
				font-size: 13px;
			}
			
			.readability-info {
				display: grid;
				gap: 10px;
			}
			
			.readability-item {
				display: flex;
				justify-content: space-between;
				padding: 8px 12px;
				background: #f9f9f9;
				border-radius: 4px;
			}
			
			.readability-label {
				font-weight: 600;
			}
			
			.readability-value {
				color: #666;
			}
			
			.seo-suggestions {
				display: grid;
				gap: 8px;
			}
			
			.seo-suggestion {
				padding: 10px 12px;
				border-radius: 4px;
				border-left: 3px solid #ccc;
			}
			
			.seo-suggestion.priority-high {
				background: #f8d7da;
				border-left-color: #dc3545;
			}
			
			.seo-suggestion.priority-medium {
				background: #fff3cd;
				border-left-color: #ffc107;
			}
			
			.seo-suggestion.priority-low {
				background: #d1ecf1;
				border-left-color: #17a2b8;
			}
			
			.suggestion-priority {
				display: inline-block;
				font-size: 10px;
				font-weight: bold;
				text-transform: uppercase;
				background: #666;
				color: white;
				padding: 2px 6px;
				border-radius: 3px;
				margin-right: 8px;
			}
			
			.priority-high .suggestion-priority {
				background: #dc3545;
			}
			
			.priority-medium .suggestion-priority {
				background: #ffc107;
				color: #000;
			}
			
			.priority-low .suggestion-priority {
				background: #17a2b8;
			}
			
			.suggestion-message {
				font-size: 13px;
			}
			
			.seo-analysis-placeholder {
				text-align: center;
				padding: 40px 20px;
				color: #666;
				background: #f9f9f9;
				border: 2px dashed #ddd;
				border-radius: 8px;
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
				
				// Content Analysis Live Updates (with debouncing)
				var analysisTimeout;
				var isAnalyzing = false;
				
				function debouncedAnalysis() {
					clearTimeout(analysisTimeout);
					analysisTimeout = setTimeout(function() {
						performLiveAnalysis();
					}, 1000); // 1 second debounce
				}
				
				function performLiveAnalysis() {
					var focusKeyword = $("#focus_keyword").val().trim();
					
					if (!focusKeyword) {
						showAnalysisPlaceholder();
						return;
					}
					
					if (isAnalyzing) {
						return; // Prevent multiple simultaneous requests
					}
					
					isAnalyzing = true;
					showAnalysisLoading();
					
					// Get current post content
					var title = $("#title").val() || $("#seo_title").val() || "";
					var content = "";
					var metaDescription = $("#seo_description").val() || "";
					
					// Get content from editor
					if (typeof tinymce !== "undefined" && tinymce.activeEditor) {
						content = tinymce.activeEditor.getContent();
					} else if ($("#content").length) {
						content = $("#content").val();
					}
					
					// Send AJAX request for server-side analysis
					$.ajax({
						url: fpSeoAnalysis.ajax_url,
						type: "POST",
						data: {
							action: "fp_analyze_content_seo",
							nonce: fpSeoAnalysis.nonce,
							post_id: fpSeoAnalysis.post_id,
							focus_keyword: focusKeyword,
							title: title,
							content: content,
							meta_description: metaDescription
						},
						success: function(response) {
							isAnalyzing = false;
							if (response.success) {
								displayAnalysisResults(response.data);
							} else {
								if (window.WP_DEBUG) {
									console.error("Analysis error:", response.data.message);
								}
								// Fallback to client-side analysis
								var quickAnalysis = performQuickAnalysis(focusKeyword, content);
								displayAnalysisResults(quickAnalysis);
							}
						},
						error: function() {
							isAnalyzing = false;
							// Fallback to client-side analysis
							var quickAnalysis = performQuickAnalysis(focusKeyword, content);
							displayAnalysisResults(quickAnalysis);
						}
					});
				}
				
				function performQuickAnalysis(keyword, content) {
					var lowerKeyword = keyword.toLowerCase();
					var lowerContent = content.toLowerCase();
					var title = $("#seo_title").val() || $("#title").val() || "";
					var description = $("#seo_description").val() || "";
					
					// Basic keyword presence checks
					var analysis = {
						focus_keyword: keyword,
						checks: {
							title: title.toLowerCase().includes(lowerKeyword),
							meta_description: description.toLowerCase().includes(lowerKeyword),
							content: lowerContent.includes(lowerKeyword),
							density: calculateKeywordDensity(lowerKeyword, lowerContent)
						}
					};
					
					// Calculate basic score
					var score = 0;
					if (analysis.checks.title) score += 25;
					if (analysis.checks.meta_description) score += 20;
					if (analysis.checks.content) score += 25;
					if (analysis.checks.density >= 0.5 && analysis.checks.density <= 2.5) score += 30;
					else if (analysis.checks.density > 0) score += 15;
					
					analysis.overall_score = Math.min(100, score);
					analysis.grade = getScoreGrade(analysis.overall_score);
					
					return analysis;
				}
				
				function calculateKeywordDensity(keyword, content) {
					if (!content) return 0;
					var words = content.split(/\s+/).filter(word => word.length > 0);
					var keywordCount = (content.match(new RegExp(keyword, "gi")) || []).length;
					return words.length > 0 ? (keywordCount / words.length * 100) : 0;
				}
				
				function getScoreGrade(score) {
					if (score >= 90) return "A";
					if (score >= 80) return "B";
					if (score >= 70) return "C";
					if (score >= 60) return "D";
					return "F";
				}
				
				function showAnalysisPlaceholder() {
					$("#content-analysis .seo-analysis-results").hide();
					$("#content-analysis .seo-analysis-placeholder").show();
				}
				
				function showAnalysisLoading() {
					// Add loading state if needed
				}
				
				function displayAnalysisResults(analysis) {
					// Update score circle
					var $scoreCircle = $(".score-circle");
					$scoreCircle.removeClass("score-a score-b score-c score-d score-f");
					$scoreCircle.addClass("score-" + analysis.grade.toLowerCase());
					$scoreCircle.find(".score-number").text(analysis.overall_score);
					$scoreCircle.find(".score-grade").text(analysis.grade);
					
					// Update keyword and readability scores
					$(".score-item").each(function() {
						var $item = $(this);
						var label = $item.find(".score-label").text();
						if (label.includes("Keywords")) {
							$item.find(".score-value").text(analysis.keyword_score + "/100");
						} else if (label.includes("Leggibilità")) {
							$item.find(".score-value").text(analysis.readability_score + "/100");
						}
					});
					
					// Update individual checks (server response format)
					if (analysis.keyword_analysis) {
						updateCheck("title", analysis.keyword_analysis.title?.present || false);
						updateCheck("meta_description", analysis.keyword_analysis.meta_description?.present || false);
						updateCheck("h1", analysis.keyword_analysis.h1?.present || false);
						
						// Update density info
						if (analysis.keyword_analysis.content_density) {
							var density = analysis.keyword_analysis.content_density.density || 0;
							var count = analysis.keyword_analysis.content_density.keyword_count || 0;
							$(".check-label").filter(function() {
								return $(this).text().includes("Densità");
							}).siblings(".check-status").text(density.toFixed(2) + "% (" + count + " occorrenze)");
						}
					}
					
					// Update readability info
					if (analysis.readability_analysis) {
						$(".readability-item").each(function() {
							var $item = $(this);
							var label = $item.find(".readability-label").text();
							if (label.includes("Flesch") && analysis.readability_analysis.flesch_score) {
								var score = Math.round(analysis.readability_analysis.flesch_score);
								var grade = analysis.readability_analysis.flesch_grade || "";
								$item.find(".readability-value").text(score + " (" + grade + ")");
							} else if (label.includes("Paragrafi") && analysis.readability_analysis.paragraph_analysis) {
								var avgLength = analysis.readability_analysis.paragraph_analysis.average_length || 0;
								$item.find(".readability-value").text("Media " + avgLength + " parole/paragrafo");
							}
						});
					}
					
					// Update suggestions
					if (analysis.suggestions && analysis.suggestions.length > 0) {
						var $suggestions = $(".seo-suggestions");
						$suggestions.empty();
						
						analysis.suggestions.forEach(function(suggestion) {
							var priorityClass = "priority-" + suggestion.priority;
							var $suggestion = $("<div class=\"seo-suggestion " + priorityClass + "\"><span class=\"suggestion-priority\">" + suggestion.priority + "</span><span class=\"suggestion-message\">" + suggestion.message + "</span></div>");
							$suggestions.append($suggestion);
						});
						
						$(".seo-analysis-section").last().show();
					}
					
					$("#content-analysis .seo-analysis-results").show();
					$("#content-analysis .seo-analysis-placeholder").hide();
				}
				
				function updateCheck(checkName, passed) {
					var $check = $(".check-label:contains(\\"" + getCheckLabel(checkName) + "\\")").closest(".seo-check");
					$check.removeClass("check-pass check-fail");
					$check.addClass(passed ? "check-pass" : "check-fail");
					
					var $icon = $check.find(".dashicons");
					$icon.removeClass("dashicons-yes dashicons-no");
					$icon.addClass(passed ? "dashicons-yes" : "dashicons-no");
					
					var status = passed ? "Keyword presente" : "Keyword mancante";
					$check.find(".check-status").text(status);
				}
				
				function getCheckLabel(checkName) {
					var labels = {
						title: "Titolo",
						meta_description: "Meta Description",
						h1: "H1"
					};
					return labels[checkName] || checkName;
				}
				
				// Bind events for live analysis
				$("#focus_keyword").on("input", debouncedAnalysis);
				$("#seo_title, #seo_description").on("input", debouncedAnalysis);
				
				// Listen for editor content changes
				if (typeof tinymce !== "undefined") {
					$(document).on("tinymce-editor-init", function() {
						tinymce.activeEditor.on("input keyup", debouncedAnalysis);
					});
				}
				$("#content").on("input", debouncedAnalysis);
				
				// Initial analysis on page load
				if ($("#focus_keyword").val()) {
					setTimeout(performLiveAnalysis, 500);
				}
			});
		';
	}

	/**
	 * AJAX handler for live content SEO analysis
	 *
	 * @return void
	 */
	public function ajax_analyze_content_seo(): void {
		// Security check
		check_ajax_referer( 'fp_seo_analysis', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions', 'fp-digital-marketing' ) );
		}
		
		$post_id = intval( $_POST['post_id'] ?? 0 );
		$focus_keyword = sanitize_text_field( $_POST['focus_keyword'] ?? '' );
		$title = sanitize_text_field( $_POST['title'] ?? '' );
		$content = wp_kses_post( $_POST['content'] ?? '' );
		$meta_description = sanitize_text_field( $_POST['meta_description'] ?? '' );
		
		if ( empty( $focus_keyword ) ) {
			wp_send_json_error( [ 'message' => __( 'Focus keyword is required', 'fp-digital-marketing' ) ] );
		}
		
		// Create a temporary post object for analysis
		$temp_post = (object) [
			'ID' => $post_id,
			'post_title' => $title,
			'post_content' => $content,
			'post_name' => sanitize_title( $title ),
			'post_excerpt' => $meta_description,
		];
		
		try {
			$analysis = ContentSeoAnalyzer::analyze_content( $temp_post, $focus_keyword );
			wp_send_json_success( $analysis );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}
}