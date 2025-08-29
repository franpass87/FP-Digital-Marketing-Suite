<?php
/**
 * Content SEO Analyzer
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\Helpers;

/**
 * Content SEO Analyzer class for analyzing content SEO and readability
 */
class ContentSeoAnalyzer {

	/**
	 * Meta field keys for content analysis
	 */
	public const META_FOCUS_KEYWORD = '_seo_focus_keyword';
	public const META_CONTENT_SCORE = '_seo_content_score';
	public const META_READABILITY_SCORE = '_seo_readability_score';
	public const META_ANALYSIS_DATA = '_seo_analysis_data';

	/**
	 * Analysis scoring weights
	 */
	private const KEYWORD_WEIGHTS = [
		'title' => 20,
		'h1' => 15,
		'headings' => 10,
		'url' => 10,
		'meta_description' => 15,
		'first_paragraph' => 15,
		'image_alt' => 10,
		'content_density' => 5,
	];

	/**
	 * Readability scoring weights
	 */
	private const READABILITY_WEIGHTS = [
		'flesch_score' => 70,
		'paragraph_length' => 30,
	];

	/**
	 * Analyze content for SEO and readability
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 * @param string $focus_keyword The focus keyword to analyze for.
	 * @return array Analysis results.
	 */
	public static function analyze_content( $post, string $focus_keyword ): array {
		$post = get_post( $post );
		if ( ! $post ) {
			return self::get_empty_analysis();
		}

		if ( empty( trim( $focus_keyword ) ) ) {
			return self::get_empty_analysis();
		}

		$original_keyword = trim( $focus_keyword );
		$focus_keyword = strtolower( $original_keyword );
		
		// Perform keyword analysis
		$keyword_analysis = self::analyze_keyword_presence( $post, $focus_keyword );
		
		// Perform readability analysis
		$readability_analysis = self::analyze_readability( $post );
		
		// Calculate overall scores
		$keyword_score = self::calculate_keyword_score( $keyword_analysis );
		$readability_score = self::calculate_readability_score( $readability_analysis );
		$overall_score = round( ( $keyword_score * 0.6 ) + ( $readability_score * 0.4 ) );
		
		// Generate suggestions
		$suggestions = self::generate_suggestions( $keyword_analysis, $readability_analysis, $original_keyword );
		
		return [
			'focus_keyword' => $original_keyword,
			'overall_score' => max( 0, min( 100, $overall_score ) ),
			'keyword_score' => max( 0, min( 100, $keyword_score ) ),
			'readability_score' => max( 0, min( 100, $readability_score ) ),
			'keyword_analysis' => $keyword_analysis,
			'readability_analysis' => $readability_analysis,
			'suggestions' => $suggestions,
			'grade' => self::get_score_grade( $overall_score ),
		];
	}

	/**
	 * Analyze keyword presence in content
	 *
	 * @param \WP_Post $post Post object.
	 * @param string $focus_keyword Focus keyword.
	 * @return array Keyword analysis results.
	 */
	private static function analyze_keyword_presence( $post, string $focus_keyword ): array {
		$analysis = [];
		
		// Analyze title
		$title = strtolower( get_the_title( $post ) );
		$analysis['title'] = [
			'present' => str_contains( $title, $focus_keyword ),
			'position' => strpos( $title, $focus_keyword ),
			'score' => str_contains( $title, $focus_keyword ) ? 100 : 0,
		];
		
		// Analyze URL slug
		$slug = strtolower( $post->post_name );
		$analysis['url'] = [
			'present' => str_contains( $slug, str_replace( ' ', '-', $focus_keyword ) ),
			'score' => str_contains( $slug, str_replace( ' ', '-', $focus_keyword ) ) ? 100 : 0,
		];
		
		// Analyze meta description
		$meta_description = '';
		try {
			$meta_description = strtolower( SeoMetadata::get_description( $post ) );
		} catch ( \Exception $e ) {
			// Fallback: try to get excerpt or description manually
			$excerpt = get_the_excerpt( $post );
			if ( ! empty( $excerpt ) ) {
				$meta_description = strtolower( $excerpt );
			}
		}
		
		$analysis['meta_description'] = [
			'present' => str_contains( $meta_description, $focus_keyword ),
			'score' => str_contains( $meta_description, $focus_keyword ) ? 100 : 0,
		];
		
		// Analyze content
		$content = strtolower( wp_strip_all_tags( $post->post_content ) );
		
		// H1 analysis (first heading)
		$h1_match = [];
		preg_match( '/<h1[^>]*>(.*?)<\/h1>/i', $post->post_content, $h1_match );
		$h1_text = isset( $h1_match[1] ) ? strtolower( wp_strip_all_tags( $h1_match[1] ) ) : '';
		$analysis['h1'] = [
			'present' => ! empty( $h1_text ) && str_contains( $h1_text, $focus_keyword ),
			'score' => ! empty( $h1_text ) && str_contains( $h1_text, $focus_keyword ) ? 100 : ( empty( $h1_text ) ? 0 : 50 ),
		];
		
		// Headings analysis (H2, H3, etc.)
		$headings_match = [];
		preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/i', $post->post_content, $headings_match );
		$headings_with_keyword = 0;
		$total_headings = count( $headings_match[1] );
		
		foreach ( $headings_match[1] as $heading ) {
			if ( str_contains( strtolower( wp_strip_all_tags( $heading ) ), $focus_keyword ) ) {
				$headings_with_keyword++;
			}
		}
		
		$analysis['headings'] = [
			'total' => $total_headings,
			'with_keyword' => $headings_with_keyword,
			'score' => $total_headings > 0 ? round( ( $headings_with_keyword / $total_headings ) * 100 ) : 0,
		];
		
		// First paragraph analysis
		$paragraphs = explode( "\n", trim( $content ) );
		$first_paragraph = isset( $paragraphs[0] ) ? trim( $paragraphs[0] ) : '';
		$analysis['first_paragraph'] = [
			'present' => str_contains( $first_paragraph, $focus_keyword ),
			'score' => str_contains( $first_paragraph, $focus_keyword ) ? 100 : 0,
		];
		
		// Image alt text analysis
		$img_match = [];
		preg_match_all( '/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i', $post->post_content, $img_match );
		$images_with_keyword = 0;
		$total_images = count( $img_match[1] );
		
		foreach ( $img_match[1] as $alt_text ) {
			if ( str_contains( strtolower( $alt_text ), $focus_keyword ) ) {
				$images_with_keyword++;
			}
		}
		
		$analysis['image_alt'] = [
			'total' => $total_images,
			'with_keyword' => $images_with_keyword,
			'score' => $total_images > 0 ? round( ( $images_with_keyword / $total_images ) * 100 ) : ( $total_images === 0 ? 100 : 0 ),
		];
		
		// Content density analysis
		$keyword_count = substr_count( $content, $focus_keyword );
		$word_count = str_word_count( $content );
		$density = $word_count > 0 ? ( $keyword_count / $word_count ) * 100 : 0;
		
		$analysis['content_density'] = [
			'density' => round( $density, 2 ),
			'keyword_count' => $keyword_count,
			'word_count' => $word_count,
			'score' => self::score_keyword_density( $density ),
		];
		
		return $analysis;
	}

	/**
	 * Analyze content readability
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Readability analysis results.
	 */
	private static function analyze_readability( $post ): array {
		$content = wp_strip_all_tags( $post->post_content );
		$content = preg_replace( '/\s+/', ' ', trim( $content ) );
		
		// Calculate Flesch Reading Ease Score
		$flesch_score = self::calculate_flesch_score( $content );
		
		// Analyze paragraph lengths
		$paragraphs = explode( "\n", $content );
		$paragraph_analysis = self::analyze_paragraph_lengths( $paragraphs );
		
		return [
			'flesch_score' => $flesch_score,
			'flesch_grade' => self::get_flesch_grade( $flesch_score ),
			'paragraph_analysis' => $paragraph_analysis,
		];
	}

	/**
	 * Calculate Flesch Reading Ease Score (simplified for Italian/English)
	 *
	 * @param string $content Content to analyze.
	 * @return float Flesch score.
	 */
	private static function calculate_flesch_score( string $content ): float {
		if ( empty( trim( $content ) ) ) {
			return 0;
		}
		
		// Count sentences (simplified - count periods, exclamation marks, question marks)
		$sentence_count = preg_match_all( '/[.!?]+/', $content );
		$sentence_count = max( 1, $sentence_count ); // Avoid division by zero
		
		// Count words
		$word_count = str_word_count( $content );
		if ( $word_count === 0 ) {
			return 0;
		}
		
		// Count syllables (simplified estimation)
		$syllable_count = self::estimate_syllables( $content );
		
		// Flesch Reading Ease formula (adapted for Italian/English)
		$avg_sentence_length = $word_count / $sentence_count;
		$avg_syllables_per_word = $syllable_count / $word_count;
		
		$flesch_score = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables_per_word );
		
		return max( 0, min( 100, $flesch_score ) );
	}

	/**
	 * Estimate syllable count (simplified)
	 *
	 * @param string $content Content to analyze.
	 * @return int Estimated syllable count.
	 */
	private static function estimate_syllables( string $content ): int {
		$words = str_word_count( $content, 1 );
		$total_syllables = 0;
		
		foreach ( $words as $word ) {
			$word = strtolower( $word );
			$syllables = preg_match_all( '/[aeiouàèìòù]/i', $word );
			$syllables = max( 1, $syllables ); // Each word has at least 1 syllable
			$total_syllables += $syllables;
		}
		
		return $total_syllables;
	}

	/**
	 * Analyze paragraph lengths
	 *
	 * @param array $paragraphs Array of paragraphs.
	 * @return array Paragraph analysis results.
	 */
	private static function analyze_paragraph_lengths( array $paragraphs ): array {
		$lengths = [];
		$total_words = 0;
		$long_paragraphs = 0;
		$paragraph_count = 0;
		
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( empty( $paragraph ) ) {
				continue;
			}
			
			$word_count = str_word_count( $paragraph );
			$lengths[] = $word_count;
			$total_words += $word_count;
			$paragraph_count++;
			
			// Consider paragraphs with more than 150 words as too long
			if ( $word_count > 150 ) {
				$long_paragraphs++;
			}
		}
		
		$avg_length = $paragraph_count > 0 ? round( $total_words / $paragraph_count ) : 0;
		$long_paragraph_ratio = $paragraph_count > 0 ? ( $long_paragraphs / $paragraph_count ) : 0;
		
		// Score based on paragraph length (optimal: 50-150 words per paragraph)
		$score = 100;
		if ( $avg_length > 150 ) {
			$score = max( 50, 100 - ( ( $avg_length - 150 ) / 2 ) );
		} elseif ( $avg_length < 50 ) {
			$score = max( 50, 50 + $avg_length );
		}
		
		return [
			'average_length' => $avg_length,
			'total_paragraphs' => $paragraph_count,
			'long_paragraphs' => $long_paragraphs,
			'long_paragraph_ratio' => round( $long_paragraph_ratio * 100 ),
			'score' => round( $score ),
		];
	}

	/**
	 * Calculate overall keyword score
	 *
	 * @param array $keyword_analysis Keyword analysis results.
	 * @return int Overall keyword score.
	 */
	private static function calculate_keyword_score( array $keyword_analysis ): float {
		$total_score = 0;
		$total_weight = array_sum( self::KEYWORD_WEIGHTS );
		
		foreach ( self::KEYWORD_WEIGHTS as $component => $weight ) {
			if ( isset( $keyword_analysis[ $component ]['score'] ) ) {
				$total_score += $keyword_analysis[ $component ]['score'] * $weight;
			}
		}
		
		return round( $total_score / $total_weight );
	}

	/**
	 * Calculate overall readability score
	 *
	 * @param array $readability_analysis Readability analysis results.
	 * @return int Overall readability score.
	 */
	private static function calculate_readability_score( array $readability_analysis ): float {
		$flesch_score = $readability_analysis['flesch_score'] ?? 0;
		$paragraph_score = $readability_analysis['paragraph_analysis']['score'] ?? 0;
		
		$weighted_score = ( $flesch_score * self::READABILITY_WEIGHTS['flesch_score'] + 
						   $paragraph_score * self::READABILITY_WEIGHTS['paragraph_length'] ) / 
						  array_sum( self::READABILITY_WEIGHTS );
		
		return round( $weighted_score );
	}

	/**
	 * Score keyword density
	 *
	 * @param float $density Keyword density percentage.
	 * @return int Score (0-100).
	 */
	private static function score_keyword_density( float $density ): int {
		// Optimal density: 0.5% - 2.5%
		if ( $density >= 0.5 && $density <= 2.5 ) {
			return 100;
		} elseif ( $density > 2.5 && $density <= 4.0 ) {
			return 75; // Slightly high but acceptable
		} elseif ( $density > 4.0 ) {
			return 25; // Too high, keyword stuffing
		} else {
			return 50; // Too low
		}
	}

	/**
	 * Generate improvement suggestions
	 *
	 * @param array $keyword_analysis Keyword analysis results.
	 * @param array $readability_analysis Readability analysis results.
	 * @param string $focus_keyword Focus keyword.
	 * @return array Suggestions array.
	 */
	private static function generate_suggestions( array $keyword_analysis, array $readability_analysis, string $focus_keyword ): array {
		$suggestions = [];
		
		// Keyword suggestions
		if ( ! $keyword_analysis['title']['present'] ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'high',
				'message' => sprintf( __( 'Includi la parola chiave "%s" nel titolo del post', 'fp-digital-marketing' ), $focus_keyword ),
			];
		}
		
		if ( ! $keyword_analysis['h1']['present'] ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'high',
				'message' => sprintf( __( 'Includi la parola chiave "%s" nell\'H1 della pagina', 'fp-digital-marketing' ), $focus_keyword ),
			];
		}
		
		if ( ! $keyword_analysis['meta_description']['present'] ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'medium',
				'message' => sprintf( __( 'Includi la parola chiave "%s" nella meta description', 'fp-digital-marketing' ), $focus_keyword ),
			];
		}
		
		if ( ! $keyword_analysis['first_paragraph']['present'] ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'medium',
				'message' => sprintf( __( 'Includi la parola chiave "%s" nel primo paragrafo', 'fp-digital-marketing' ), $focus_keyword ),
			];
		}
		
		if ( $keyword_analysis['headings']['total'] > 0 && $keyword_analysis['headings']['with_keyword'] === 0 ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'low',
				'message' => sprintf( __( 'Includi la parola chiave "%s" in almeno un sottotitolo', 'fp-digital-marketing' ), $focus_keyword ),
			];
		}
		
		$density = $keyword_analysis['content_density']['density'] ?? 0;
		if ( $density > 4.0 ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'high',
				'message' => sprintf( __( 'Riduci la densità della parola chiave (attuale: %.1f%%, raccomandato: 0.5-2.5%%)', 'fp-digital-marketing' ), $density ),
			];
		} elseif ( $density < 0.5 ) {
			$suggestions[] = [
				'type' => 'keyword',
				'priority' => 'medium',
				'message' => sprintf( __( 'Aumenta la densità della parola chiave (attuale: %.1f%%, raccomandato: 0.5-2.5%%)', 'fp-digital-marketing' ), $density ),
			];
		}
		
		// Readability suggestions
		$flesch_score = $readability_analysis['flesch_score'] ?? 0;
		if ( $flesch_score < 30 ) {
			$suggestions[] = [
				'type' => 'readability',
				'priority' => 'medium',
				'message' => __( 'Il contenuto è difficile da leggere. Usa frasi più brevi e parole più semplici', 'fp-digital-marketing' ),
			];
		}
		
		$avg_paragraph_length = $readability_analysis['paragraph_analysis']['average_length'] ?? 0;
		if ( $avg_paragraph_length > 150 ) {
			$suggestions[] = [
				'type' => 'readability',
				'priority' => 'low',
				'message' => sprintf( __( 'I paragrafi sono troppo lunghi (media: %d parole). Dividi i paragrafi lunghi', 'fp-digital-marketing' ), $avg_paragraph_length ),
			];
		}
		
		return $suggestions;
	}

	/**
	 * Get score grade
	 *
	 * @param int $score Score (0-100).
	 * @return string Grade letter.
	 */
	private static function get_score_grade( float $score ): string {
		if ( $score >= 90 ) {
			return 'A';
		} elseif ( $score >= 80 ) {
			return 'B';
		} elseif ( $score >= 70 ) {
			return 'C';
		} elseif ( $score >= 60 ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Get Flesch reading ease grade
	 *
	 * @param float $score Flesch score.
	 * @return string Grade description.
	 */
	private static function get_flesch_grade( float $score ): string {
		if ( $score >= 90 ) {
			return __( 'Molto facile', 'fp-digital-marketing' );
		} elseif ( $score >= 80 ) {
			return __( 'Facile', 'fp-digital-marketing' );
		} elseif ( $score >= 70 ) {
			return __( 'Abbastanza facile', 'fp-digital-marketing' );
		} elseif ( $score >= 60 ) {
			return __( 'Standard', 'fp-digital-marketing' );
		} elseif ( $score >= 50 ) {
			return __( 'Abbastanza difficile', 'fp-digital-marketing' );
		} elseif ( $score >= 30 ) {
			return __( 'Difficile', 'fp-digital-marketing' );
		} else {
			return __( 'Molto difficile', 'fp-digital-marketing' );
		}
	}

	/**
	 * Get empty analysis structure
	 *
	 * @return array Empty analysis results.
	 */
	private static function get_empty_analysis(): array {
		return [
			'focus_keyword' => '',
			'overall_score' => 0,
			'keyword_score' => 0,
			'readability_score' => 0,
			'keyword_analysis' => [],
			'readability_analysis' => [],
			'suggestions' => [
				[
					'type' => 'keyword',
					'priority' => 'high',
					'message' => __( 'Aggiungi una parola chiave focus per iniziare l\'analisi', 'fp-digital-marketing' ),
				],
			],
			'grade' => 'F',
		];
	}

	/**
	 * Save analysis results
	 *
	 * @param int $post_id Post ID.
	 * @param array $analysis Analysis results.
	 * @return bool Success status.
	 */
	public static function save_analysis( int $post_id, array $analysis ): bool {
		$saved = true;
		
		$saved &= update_post_meta( $post_id, self::META_FOCUS_KEYWORD, $analysis['focus_keyword'] );
		$saved &= update_post_meta( $post_id, self::META_CONTENT_SCORE, $analysis['overall_score'] );
		$saved &= update_post_meta( $post_id, self::META_READABILITY_SCORE, $analysis['readability_score'] );
		$saved &= update_post_meta( $post_id, self::META_ANALYSIS_DATA, $analysis );
		
		return (bool) $saved;
	}

	/**
	 * Get saved analysis results
	 *
	 * @param int $post_id Post ID.
	 * @return array Analysis results or empty analysis if not found.
	 */
	public static function get_saved_analysis( int $post_id ): array {
		$analysis = get_post_meta( $post_id, self::META_ANALYSIS_DATA, true );
		
		if ( ! is_array( $analysis ) || empty( $analysis ) ) {
			return self::get_empty_analysis();
		}
		
		return $analysis;
	}
}