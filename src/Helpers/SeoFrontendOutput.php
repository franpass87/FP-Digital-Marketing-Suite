<?php
/**
 * SEO Frontend Output Handler
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * SEO Frontend Output class for rendering meta tags in the frontend
 */
class SeoFrontendOutput {

	/**
	 * Initialize frontend output
	 *
	 * @return void
	 */
	public static function init(): void {
		// Hook into wp_head to output meta tags.
		add_action( 'wp_head', [ self::class, 'output_meta_tags' ], 1 );
		
		// Remove default WordPress meta tags that we'll replace.
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		remove_action( 'wp_head', 'wp_generator' );
		
		// Filter title if needed.
		add_filter( 'pre_get_document_title', [ self::class, 'filter_document_title' ] );
		add_filter( 'document_title_separator', [ self::class, 'filter_title_separator' ] );
	}

	/**
	 * Output all meta tags for the current page
	 *
	 * @return void
	 */
	public static function output_meta_tags(): void {
		global $post;

		// Only output on singular posts/pages and home page.
		if ( ! is_singular() && ! is_home() && ! is_front_page() ) {
			return;
		}

		$current_post = null;
		if ( is_singular() ) {
			$current_post = $post;
		} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
			$current_post = get_post( get_option( 'page_for_posts' ) );
		} elseif ( is_front_page() && get_option( 'page_on_front' ) ) {
			$current_post = get_post( get_option( 'page_on_front' ) );
		}

		// Generate meta tags.
		$meta_tags = [];
		if ( $current_post ) {
			$meta_tags = SeoMetadata::generate_meta_tags( $current_post );
		} else {
			$meta_tags = self::generate_home_meta_tags();
		}

		// Output the meta tags.
		self::render_meta_tags( $meta_tags );
	}

	/**
	 * Filter the document title
	 *
	 * @param string $title The document title.
	 * @return string Filtered title.
	 */
	public static function filter_document_title( string $title ): string {
		global $post;

		if ( is_singular() && $post ) {
			$seo_title = SeoMetadata::get_title( $post );
			if ( $seo_title ) {
				return $seo_title;
			}
		}

		return $title;
	}

	/**
	 * Filter the title separator
	 *
	 * @param string $separator The title separator.
	 * @return string Filtered separator.
	 */
	public static function filter_title_separator( string $separator ): string {
		return '-';
	}

	/**
	 * Generate meta tags for home page
	 *
	 * @return array Array of meta tags.
	 */
	private static function generate_home_meta_tags(): array {
		$meta_tags = [];
		$seo_settings = get_option( 'fp_digital_marketing_seo_settings', [] );

		// Title.
		$site_name = get_bloginfo( 'name' );
		$tagline = get_bloginfo( 'description' );
		
		$title_template = $seo_settings['home_title_template'] ?? '{site_name} - {tagline}';
		$title = str_replace(
			[ '{site_name}', '{tagline}' ],
			[ $site_name, $tagline ],
			$title_template
		);
		
		if ( $title ) {
			$meta_tags['title'] = wp_strip_all_tags( $title );
		}

		// Description.
		if ( $tagline && ( $seo_settings['auto_generate_descriptions'] ?? true ) ) {
			$meta_tags['description'] = wp_strip_all_tags( $tagline );
		}

		// Robots.
		$meta_tags['robots'] = 'index, follow';

		// Canonical.
		$meta_tags['canonical'] = home_url( '/' );

		// Open Graph.
		if ( $title ) {
			$meta_tags['og:title'] = $meta_tags['title'];
		}
		if ( isset( $meta_tags['description'] ) ) {
			$meta_tags['og:description'] = $meta_tags['description'];
		}
		$meta_tags['og:type'] = 'website';
		$meta_tags['og:url'] = home_url( '/' );
		$meta_tags['og:site_name'] = $site_name;

		// Default OG image.
		if ( ! empty( $seo_settings['default_og_image'] ) ) {
			$meta_tags['og:image'] = esc_url( $seo_settings['default_og_image'] );
		}

		// Twitter Cards.
		$meta_tags['twitter:card'] = 'summary_large_image';
		if ( isset( $meta_tags['og:title'] ) ) {
			$meta_tags['twitter:title'] = $meta_tags['og:title'];
		}
		if ( isset( $meta_tags['og:description'] ) ) {
			$meta_tags['twitter:description'] = $meta_tags['og:description'];
		}
		if ( isset( $meta_tags['og:image'] ) ) {
			$meta_tags['twitter:image'] = $meta_tags['og:image'];
		}

		// Twitter site.
		if ( ! empty( $seo_settings['twitter_site'] ) ) {
			$meta_tags['twitter:site'] = sanitize_text_field( $seo_settings['twitter_site'] );
		}

		return $meta_tags;
	}

	/**
	 * Render meta tags as HTML
	 *
	 * @param array $meta_tags Array of meta tags to render.
	 * @return void
	 */
	private static function render_meta_tags( array $meta_tags ): void {
		echo "\n<!-- SEO Meta Tags by FP Digital Marketing Suite -->\n";

		// Title tag.
		if ( isset( $meta_tags['title'] ) ) {
			echo '<title>' . esc_html( $meta_tags['title'] ) . "</title>\n";
		}

		// Meta description.
		if ( isset( $meta_tags['description'] ) ) {
			echo '<meta name="description" content="' . esc_attr( $meta_tags['description'] ) . '">' . "\n";
		}

		// Meta robots.
		if ( isset( $meta_tags['robots'] ) ) {
			echo '<meta name="robots" content="' . esc_attr( $meta_tags['robots'] ) . '">' . "\n";
		}

		// Canonical URL.
		if ( isset( $meta_tags['canonical'] ) ) {
			echo '<link rel="canonical" href="' . esc_url( $meta_tags['canonical'] ) . '">' . "\n";
		}

		// Open Graph meta tags.
		$og_tags = [
			'og:title',
			'og:description',
			'og:type',
			'og:url',
			'og:site_name',
			'og:image',
		];

		foreach ( $og_tags as $og_tag ) {
			if ( isset( $meta_tags[ $og_tag ] ) ) {
				echo '<meta property="' . esc_attr( $og_tag ) . '" content="' . esc_attr( $meta_tags[ $og_tag ] ) . '">' . "\n";
			}
		}

		// Twitter Card meta tags.
		$twitter_tags = [
			'twitter:card',
			'twitter:title',
			'twitter:description',
			'twitter:image',
			'twitter:site',
		];

		foreach ( $twitter_tags as $twitter_tag ) {
			if ( isset( $meta_tags[ $twitter_tag ] ) ) {
				echo '<meta name="' . esc_attr( $twitter_tag ) . '" content="' . esc_attr( $meta_tags[ $twitter_tag ] ) . '">' . "\n";
			}
		}

		echo "<!-- End SEO Meta Tags -->\n\n";
	}
}