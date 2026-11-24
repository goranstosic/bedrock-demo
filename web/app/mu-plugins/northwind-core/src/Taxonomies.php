<?php
/**
 * Custom taxonomies.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

/**
 * Registers the Region taxonomy.
 */
final class Taxonomies {

	public const REGION = 'region';

	/**
	 * The fixed set of regions, keyed by term slug.
	 *
	 * @return array<string, string>
	 */
	public static function regions(): array {
		return array(
			'global' => __( 'Global', 'northwind' ),
			'us'     => __( 'United States', 'northwind' ),
			'uk'     => __( 'United Kingdom', 'northwind' ),
			'au'     => __( 'Australia', 'northwind' ),
		);
	}

	/**
	 * Hooks the registration into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_taxonomies' ) );
	}

	/**
	 * Registers the Region taxonomy on client stories.
	 *
	 * Region is a filter facet, not a destination: it has no public archive
	 * and no query var, so the "region" URL parameter stays free for the
	 * visitor's region selection (see Region).
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			self::REGION,
			array( PostTypes::CLIENT_STORY ),
			array(
				'labels'             => array(
					'name'          => __( 'Regions', 'northwind' ),
					'singular_name' => __( 'Region', 'northwind' ),
					'all_items'     => __( 'All regions', 'northwind' ),
					'edit_item'     => __( 'Edit region', 'northwind' ),
					'add_new_item'  => __( 'Add new region', 'northwind' ),
					'search_items'  => __( 'Search regions', 'northwind' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'hierarchical'       => true,
				'query_var'          => false,
				'rewrite'            => false,
			)
		);
	}
}
