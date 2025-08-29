<?php
/**
 * BreadcrumbList Schema Class
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers\Schema;

/**
 * BreadcrumbList schema generator
 */
class BreadcrumbListSchema extends BaseSchema {

	/**
	 * Generate BreadcrumbList schema data
	 *
	 * @return array|null BreadcrumbList schema or null
	 */
	public static function generate(): ?array {
		$breadcrumbs = self::get_breadcrumbs();

		if ( empty( $breadcrumbs ) ) {
			return null;
		}

		$schema = self::create_base_schema( 'BreadcrumbList', [
			'itemListElement' => $breadcrumbs
		] );

		return apply_filters( 'fp_dms_breadcrumb_schema', $schema );
	}

	/**
	 * Check if BreadcrumbList schema is applicable
	 *
	 * @return bool True if not on home page and breadcrumbs are enabled
	 */
	public static function is_applicable(): bool {
		$settings = get_option( 'fp_digital_marketing_schema_settings', [] );
		$breadcrumbs_enabled = $settings['enable_breadcrumbs'] ?? true;

		// Don't show breadcrumbs on home page
		if ( is_home() || is_front_page() ) {
			return false;
		}

		return $breadcrumbs_enabled;
	}

	/**
	 * Get breadcrumb items for the current page
	 *
	 * @return array Breadcrumb items
	 */
	private static function get_breadcrumbs(): array {
		$breadcrumbs = [];
		$position = 1;

		// Always start with home
		$breadcrumbs[] = [
			'@type' => 'ListItem',
			'position' => $position++,
			'name' => get_bloginfo( 'name' ),
			'item' => home_url()
		];

		// Add context-specific breadcrumbs
		if ( is_category() || is_tag() || is_tax() ) {
			$breadcrumbs = array_merge( $breadcrumbs, self::get_taxonomy_breadcrumbs( $position ) );
		} elseif ( is_single() ) {
			$breadcrumbs = array_merge( $breadcrumbs, self::get_single_breadcrumbs( $position ) );
		} elseif ( is_page() ) {
			$breadcrumbs = array_merge( $breadcrumbs, self::get_page_breadcrumbs( $position ) );
		} elseif ( is_author() ) {
			$breadcrumbs = array_merge( $breadcrumbs, self::get_author_breadcrumbs( $position ) );
		} elseif ( is_archive() ) {
			$breadcrumbs = array_merge( $breadcrumbs, self::get_archive_breadcrumbs( $position ) );
		}

		return apply_filters( 'fp_dms_breadcrumb_items', $breadcrumbs );
	}

	/**
	 * Get breadcrumbs for taxonomy pages
	 *
	 * @param int $position Starting position
	 * @return array Breadcrumb items
	 */
	private static function get_taxonomy_breadcrumbs( int &$position ): array {
		$breadcrumbs = [];
		$term = get_queried_object();

		if ( ! $term instanceof \WP_Term ) {
			return $breadcrumbs;
		}

		// Add parent terms if any
		if ( $term->parent ) {
			$parents = get_ancestors( $term->term_id, $term->taxonomy );
			$parents = array_reverse( $parents );

			foreach ( $parents as $parent_id ) {
				$parent_term = get_term( $parent_id, $term->taxonomy );
				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					$breadcrumbs[] = [
						'@type' => 'ListItem',
						'position' => $position++,
						'name' => $parent_term->name,
						'item' => get_term_link( $parent_term )
					];
				}
			}
		}

		// Add current term
		$breadcrumbs[] = [
			'@type' => 'ListItem',
			'position' => $position++,
			'name' => $term->name,
			'item' => get_term_link( $term )
		];

		return $breadcrumbs;
	}

	/**
	 * Get breadcrumbs for single posts
	 *
	 * @param int $position Starting position
	 * @return array Breadcrumb items
	 */
	private static function get_single_breadcrumbs( int &$position ): array {
		$breadcrumbs = [];
		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return $breadcrumbs;
		}

		// Add post type archive if applicable
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( $post_type_obj && $post_type_obj->has_archive && $post->post_type !== 'post' ) {
			$breadcrumbs[] = [
				'@type' => 'ListItem',
				'position' => $position++,
				'name' => $post_type_obj->labels->name,
				'item' => get_post_type_archive_link( $post->post_type )
			];
		}

		// Add categories for posts
		if ( $post->post_type === 'post' ) {
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$category = $categories[0];
				$breadcrumbs[] = [
					'@type' => 'ListItem',
					'position' => $position++,
					'name' => $category->name,
					'item' => get_category_link( $category->term_id )
				];
			}
		}

		// Add current post
		$breadcrumbs[] = [
			'@type' => 'ListItem',
			'position' => $position++,
			'name' => get_the_title( $post ),
			'item' => get_permalink( $post )
		];

		return $breadcrumbs;
	}

	/**
	 * Get breadcrumbs for pages
	 *
	 * @param int $position Starting position
	 * @return array Breadcrumb items
	 */
	private static function get_page_breadcrumbs( int &$position ): array {
		$breadcrumbs = [];
		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return $breadcrumbs;
		}

		// Add parent pages
		if ( $post->post_parent ) {
			$parents = get_post_ancestors( $post );
			$parents = array_reverse( $parents );

			foreach ( $parents as $parent_id ) {
				$breadcrumbs[] = [
					'@type' => 'ListItem',
					'position' => $position++,
					'name' => get_the_title( $parent_id ),
					'item' => get_permalink( $parent_id )
				];
			}
		}

		// Add current page
		$breadcrumbs[] = [
			'@type' => 'ListItem',
			'position' => $position++,
			'name' => get_the_title( $post ),
			'item' => get_permalink( $post )
		];

		return $breadcrumbs;
	}

	/**
	 * Get breadcrumbs for author pages
	 *
	 * @param int $position Starting position
	 * @return array Breadcrumb items
	 */
	private static function get_author_breadcrumbs( int &$position ): array {
		$author = get_queried_object();

		if ( ! $author instanceof \WP_User ) {
			return [];
		}

		return [
			[
				'@type' => 'ListItem',
				'position' => $position++,
				'name' => $author->display_name,
				'item' => get_author_posts_url( $author->ID )
			]
		];
	}

	/**
	 * Get breadcrumbs for archive pages
	 *
	 * @param int $position Starting position
	 * @return array Breadcrumb items
	 */
	private static function get_archive_breadcrumbs( int &$position ): array {
		$breadcrumbs = [];

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type_obj = get_post_type_object( $post_type );

			if ( $post_type_obj ) {
				$breadcrumbs[] = [
					'@type' => 'ListItem',
					'position' => $position++,
					'name' => $post_type_obj->labels->name,
					'item' => get_post_type_archive_link( $post_type )
				];
			}
		} elseif ( is_date() ) {
			$year = get_query_var( 'year' );
			$month = get_query_var( 'monthnum' );
			$day = get_query_var( 'day' );

			if ( $year ) {
				$breadcrumbs[] = [
					'@type' => 'ListItem',
					'position' => $position++,
					'name' => $year,
					'item' => get_year_link( $year )
				];

				if ( $month ) {
					$breadcrumbs[] = [
						'@type' => 'ListItem',
						'position' => $position++,
						'name' => date( 'F', mktime( 0, 0, 0, $month, 1 ) ),
						'item' => get_month_link( $year, $month )
					];

					if ( $day ) {
						$breadcrumbs[] = [
							'@type' => 'ListItem',
							'position' => $position++,
							'name' => $day,
							'item' => get_day_link( $year, $month, $day )
						];
					}
				}
			}
		}

		return $breadcrumbs;
	}
}