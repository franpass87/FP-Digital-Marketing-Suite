<?php
/**
 * Cliente Custom Post Type
 *
 * @package FP_Digital_Marketing_Suite
 */

declare(strict_types=1);

namespace FP\DigitalMarketing\PostTypes;

/**
 * Cliente Custom Post Type class
 */
class ClientePostType {

	/**
	 * Post type slug
	 *
	 * @var string
	 */
	public const POST_TYPE = 'cliente';

	/**
	 * Initialize the post type
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the Cliente custom post type
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Clienti', 'Post type general name', 'fp-digital-marketing' ),
			'singular_name'         => _x( 'Cliente', 'Post type singular name', 'fp-digital-marketing' ),
			'menu_name'             => _x( 'Clienti', 'Admin Menu text', 'fp-digital-marketing' ),
			'name_admin_bar'        => _x( 'Cliente', 'Add New on Toolbar', 'fp-digital-marketing' ),
			'add_new'               => __( 'Aggiungi Nuovo', 'fp-digital-marketing' ),
			'add_new_item'          => __( 'Aggiungi Nuovo Cliente', 'fp-digital-marketing' ),
			'new_item'              => __( 'Nuovo Cliente', 'fp-digital-marketing' ),
			'edit_item'             => __( 'Modifica Cliente', 'fp-digital-marketing' ),
			'view_item'             => __( 'Visualizza Cliente', 'fp-digital-marketing' ),
			'all_items'             => __( 'Tutti i Clienti', 'fp-digital-marketing' ),
			'search_items'          => __( 'Cerca Clienti', 'fp-digital-marketing' ),
			'parent_item_colon'     => __( 'Cliente Genitore:', 'fp-digital-marketing' ),
			'not_found'             => __( 'Nessun cliente trovato.', 'fp-digital-marketing' ),
			'not_found_in_trash'    => __( 'Nessun cliente trovato nel cestino.', 'fp-digital-marketing' ),
			'featured_image'        => _x( 'Immagine Cliente', 'Overrides the "Featured Image" phrase', 'fp-digital-marketing' ),
			'set_featured_image'    => _x( 'Imposta immagine cliente', 'Overrides the "Set featured image" phrase', 'fp-digital-marketing' ),
			'remove_featured_image' => _x( 'Rimuovi immagine cliente', 'Overrides the "Remove featured image" phrase', 'fp-digital-marketing' ),
			'use_featured_image'    => _x( 'Usa come immagine cliente', 'Overrides the "Use as featured image" phrase', 'fp-digital-marketing' ),
			'archives'              => _x( 'Archivi Cliente', 'The post type archive label', 'fp-digital-marketing' ),
			'insert_into_item'      => _x( 'Inserisci nel cliente', 'Overrides the "Insert into post" phrase', 'fp-digital-marketing' ),
			'uploaded_to_this_item' => _x( 'Caricato in questo cliente', 'Overrides the "Uploaded to this post" phrase', 'fp-digital-marketing' ),
			'filter_items_list'     => _x( 'Filtra lista clienti', 'Screen reader text for the filter links', 'fp-digital-marketing' ),
			'items_list_navigation' => _x( 'Navigazione lista clienti', 'Screen reader text for the pagination', 'fp-digital-marketing' ),
			'items_list'            => _x( 'Lista clienti', 'Screen reader text for the items list', 'fp-digital-marketing' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'fp-digital-marketing-dashboard',
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'cliente' ],
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-businessman',
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'show_in_rest'       => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
