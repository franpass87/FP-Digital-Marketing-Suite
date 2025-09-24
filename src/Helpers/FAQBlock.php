<?php
/**
 * FAQ Gutenberg Block
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * FAQ Block Handler for Gutenberg integration
 */
class FAQBlock {

	/**
	 * Initialize FAQ block
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_block_assets' ] );
	}

	/**
	 * Register the FAQ block
	 *
	 * @return void
	 */
	public static function register_block(): void {
		// Register block only if Gutenberg is available
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type( 'fp-dms/faq', [
			'editor_script' => 'fp-dms-faq-block',
			'render_callback' => [ self::class, 'render_block' ],
			'attributes' => [
				'faqs' => [
					'type' => 'array',
					'default' => [
						[
							'question' => '',
							'answer' => ''
						]
					]
				],
				'className' => [
					'type' => 'string',
					'default' => ''
				]
			]
		] );
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @return void
	 */
	public static function enqueue_block_assets(): void {
                $script_path = FP_DIGITAL_MARKETING_PLUGIN_DIR . 'assets/js/faq-block.js';
                $script_url  = FP_DIGITAL_MARKETING_PLUGIN_URL . 'assets/js/faq-block.js';

		// Only enqueue if the file exists
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'fp-dms-faq-block',
				$script_url,
				[ 'wp-blocks', 'wp-editor', 'wp-components', 'wp-element' ],
				'1.0.0',
				true
			);
		}
	}

	/**
	 * Render the FAQ block
	 *
	 * @param array $attributes Block attributes
	 * @return string Rendered block content
	 */
	public static function render_block( array $attributes ): string {
		$faqs = $attributes['faqs'] ?? [];
		$class_name = $attributes['className'] ?? '';

		if ( empty( $faqs ) ) {
			return '';
		}

		$output = '<div class="fp-dms-faq-block ' . esc_attr( $class_name ) . '">';

		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$question = esc_html( $faq['question'] );
			$answer = wp_kses_post( $faq['answer'] );

			$output .= sprintf(
				'<details class="faq-item"><summary class="faq-question">%s</summary><div class="faq-answer">%s</div></details>',
				$question,
				$answer
			);
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Get FAQ data from post content for Schema.org
	 *
	 * @param string $content Post content
	 * @return array FAQ items
	 */
	public static function extract_faq_data( string $content ): array {
		if ( ! has_blocks( $content ) ) {
			return [];
		}

		$faq_items = [];
		$blocks = parse_blocks( $content );

		foreach ( $blocks as $block ) {
			$faq_items = array_merge( $faq_items, self::extract_faq_from_block( $block ) );
		}

		return $faq_items;
	}

	/**
	 * Extract FAQ data from a block recursively
	 *
	 * @param array $block Block data
	 * @return array FAQ items
	 */
	private static function extract_faq_from_block( array $block ): array {
		$faq_items = [];

		// Check for our custom FAQ block
		if ( $block['blockName'] === 'fp-dms/faq' ) {
			$faqs = $block['attrs']['faqs'] ?? [];
			foreach ( $faqs as $faq ) {
				if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
					$faq_items[] = [
						'question' => wp_strip_all_tags( $faq['question'] ),
						'answer' => wp_strip_all_tags( $faq['answer'] )
					];
				}
			}
		}

		// Recursively check inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$faq_items = array_merge( $faq_items, self::extract_faq_from_block( $inner_block ) );
			}
		}

		return $faq_items;
	}
}