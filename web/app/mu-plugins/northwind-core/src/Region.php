<?php
/**
 * Visitor region.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

use WP_HTML_Tag_Processor;

/**
 * Resolves the region the current visitor is browsing.
 *
 * The region travels in the URL as ?region=<slug>. A URL parameter (and not a
 * cookie or geolocation) keeps every page fully cacheable on a page cache
 * such as WP Engine's, because each region is its own URL.
 */
final class Region {

	public const QUERY_ARG = 'region';
	public const FALLBACK  = 'global';

	/**
	 * Hooks into WordPress.
	 */
	public static function register(): void {
		add_filter( 'post_type_link', array( self::class, 'persist' ) );
		add_filter( 'post_type_archive_link', array( self::class, 'persist' ) );
		add_filter( 'render_block_core/navigation-link', array( self::class, 'persist_in_block' ) );
		add_filter( 'render_block_core/site-title', array( self::class, 'persist_in_block' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_story_archive' ) );
	}

	/**
	 * The slug of the region the visitor is browsing.
	 */
	public static function current(): string {
		$region = self::FALLBACK;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display preference, validated against a fixed list below.
		if ( isset( $_GET[ self::QUERY_ARG ] ) && is_string( $_GET[ self::QUERY_ARG ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) );

			if ( array_key_exists( $requested, Taxonomies::regions() ) ) {
				$region = $requested;
			}
		}

		/**
		 * Filters the region the current visitor is browsing.
		 *
		 * Lets a project replace the URL parameter with another source, for
		 * example a subdirectory or a domain per region.
		 *
		 * @param string $region Region term slug.
		 */
		$filtered = (string) apply_filters( 'northwind_current_region', $region );

		return array_key_exists( $filtered, Taxonomies::regions() ) ? $filtered : self::FALLBACK;
	}

	/**
	 * The region slugs whose content is shown to the current visitor.
	 *
	 * Content tagged "global" is shown in every region.
	 *
	 * @return string[]
	 */
	public static function visible_slugs(): array {
		return array_values( array_unique( array( self::current(), self::FALLBACK ) ) );
	}

	/**
	 * The given URL for a specific region.
	 *
	 * @param string $url    Any URL on this site.
	 * @param string $region Region term slug.
	 */
	public static function url_for( string $url, string $region ): string {
		$url = remove_query_arg( self::QUERY_ARG, $url );

		if ( self::FALLBACK === $region ) {
			return $url;
		}

		return add_query_arg( self::QUERY_ARG, $region, $url );
	}

	/**
	 * Limits the client story archive to the visitor's region.
	 *
	 * Uses the same rule as the Client Story Cards block: stories tagged with
	 * the current region or with Global.
	 *
	 * @param \WP_Query $query The query being prepared.
	 */
	public static function filter_story_archive( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( PostTypes::CLIENT_STORY ) ) {
			return;
		}

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => Taxonomies::REGION,
			'field'    => 'slug',
			'terms'    => self::visible_slugs(),
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Keeps the selected region when the visitor follows a generated link.
	 *
	 * @param string $url Permalink or archive link.
	 */
	public static function persist( string $url ): string {
		if ( is_admin() ) {
			return $url;
		}

		return self::url_for( $url, self::current() );
	}

	/**
	 * Keeps the selected region on links rendered by navigation blocks.
	 *
	 * @param string $html Rendered block markup.
	 */
	public static function persist_in_block( string $html ): string {
		$region = self::current();

		if ( self::FALLBACK === $region ) {
			return $html;
		}

		$tags = new WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag( 'a' ) ) {
			$href = $tags->get_attribute( 'href' );

			if ( is_string( $href ) && self::is_internal( $href ) ) {
				$tags->set_attribute( 'href', self::url_for( $href, $region ) );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Whether a URL points at this site.
	 *
	 * @param string $url URL or root-relative path.
	 */
	private static function is_internal( string $url ): bool {
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return true;
		}

		return str_starts_with( $url, home_url() );
	}
}
