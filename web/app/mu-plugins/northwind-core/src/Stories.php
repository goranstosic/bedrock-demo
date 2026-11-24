<?php
/**
 * Client story queries.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

use WP_Post;
use WP_Query;

/**
 * Read access to client stories for templates and blocks.
 */
final class Stories {

	/**
	 * ACF field (on a sector) that holds the related client story IDs.
	 */
	public const SECTOR_STORIES_FIELD = 'client_stories';

	/**
	 * ACF field (on a client story) that holds the related sector IDs.
	 */
	public const STORY_SECTORS_FIELD = 'sectors';

	public const DEFAULT_LIMIT = 6;

	/**
	 * Published client stories related to a sector and visible in a region.
	 *
	 * @param int      $sector_id Sector post ID.
	 * @param string[] $regions   Region term slugs. Defaults to the regions
	 *                            visible to the current visitor.
	 * @return WP_Post[]
	 */
	public static function for_sector( int $sector_id, array $regions = array() ): array {
		if ( ! function_exists( 'get_field' ) || PostTypes::SECTOR !== get_post_type( $sector_id ) ) {
			return array();
		}

		$story_ids = get_field( self::SECTOR_STORIES_FIELD, $sector_id, false );
		$story_ids = array_filter( array_map( 'absint', (array) $story_ids ) );

		if ( ! $story_ids ) {
			return array();
		}

		/**
		 * Filters the maximum number of cards shown by the Client Story Cards block.
		 *
		 * @param int $limit     Maximum number of stories.
		 * @param int $sector_id Sector post ID.
		 */
		$limit = (int) apply_filters( 'northwind_client_story_cards_limit', self::DEFAULT_LIMIT, $sector_id );

		$query = new WP_Query(
			array(
				'post_type'           => PostTypes::CLIENT_STORY,
				'post_status'         => 'publish',
				'post__in'            => $story_ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => max( 1, $limit ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				// phpcs:ignore WordPress.DB.SlowDB.slow_db_query_tax_query -- Bounded by post__in.
				'tax_query'           => array(
					array(
						'taxonomy' => Taxonomies::REGION,
						'field'    => 'slug',
						'terms'    => $regions ? $regions : Region::visible_slugs(),
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * The sector a block should use when the editor did not pick one.
	 *
	 * @param int $post_id ID of the post the block is rendered in.
	 * @return int Sector post ID, or 0 when the context is not a sector.
	 */
	public static function contextual_sector_id( int $post_id = 0 ): int {
		if ( $post_id && PostTypes::SECTOR === get_post_type( $post_id ) ) {
			return $post_id;
		}

		if ( is_singular( PostTypes::SECTOR ) ) {
			return (int) get_queried_object_id();
		}

		return 0;
	}
}
