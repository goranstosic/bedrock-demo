<?php
/**
 * Custom post types.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

/**
 * Registers the Sector and Client Story post types.
 */
final class PostTypes {

	public const SECTOR       = 'sector';
	public const CLIENT_STORY = 'client_story';

	/**
	 * Hooks the registration into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_types' ) );
	}

	/**
	 * Registers both post types.
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::SECTOR,
			array(
				'labels'       => array(
					'name'               => __( 'Sectors', 'northwind' ),
					'singular_name'      => __( 'Sector', 'northwind' ),
					'add_new_item'       => __( 'Add new sector', 'northwind' ),
					'edit_item'          => __( 'Edit sector', 'northwind' ),
					'new_item'           => __( 'New sector', 'northwind' ),
					'view_item'          => __( 'View sector', 'northwind' ),
					'search_items'       => __( 'Search sectors', 'northwind' ),
					'not_found'          => __( 'No sectors found.', 'northwind' ),
					'not_found_in_trash' => __( 'No sectors found in the bin.', 'northwind' ),
					'all_items'          => __( 'All sectors', 'northwind' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-building',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'      => array(
					'slug'       => 'sectors',
					'with_front' => false,
				),
			)
		);

		register_post_type(
			self::CLIENT_STORY,
			array(
				'labels'       => array(
					'name'               => __( 'Client stories', 'northwind' ),
					'singular_name'      => __( 'Client story', 'northwind' ),
					'add_new_item'       => __( 'Add new client story', 'northwind' ),
					'edit_item'          => __( 'Edit client story', 'northwind' ),
					'new_item'           => __( 'New client story', 'northwind' ),
					'view_item'          => __( 'View client story', 'northwind' ),
					'search_items'       => __( 'Search client stories', 'northwind' ),
					'not_found'          => __( 'No client stories found.', 'northwind' ),
					'not_found_in_trash' => __( 'No client stories found in the bin.', 'northwind' ),
					'all_items'          => __( 'All client stories', 'northwind' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-awards',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'      => array(
					'slug'       => 'client-stories',
					'with_front' => false,
				),
			)
		);
	}
}
