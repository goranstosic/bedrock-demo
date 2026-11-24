<?php
/**
 * ACF local JSON.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

/**
 * Keeps ACF field groups in version control.
 *
 * Field groups are saved to and loaded from the acf-json directory of this
 * plugin. The repository is the source of truth: the field group editor is
 * only available outside production, and changes travel through pull
 * requests like any other code.
 */
final class AcfJson {

	/**
	 * Hooks into ACF.
	 */
	public static function register(): void {
		add_filter( 'acf/settings/save_json', array( self::class, 'path' ) );
		add_filter( 'acf/settings/load_json', array( self::class, 'load_paths' ) );
		add_filter( 'acf/settings/show_admin', array( self::class, 'show_admin' ) );
	}

	/**
	 * The directory that holds the versioned field groups.
	 */
	public static function path(): string {
		return PLUGIN_DIR . '/acf-json';
	}

	/**
	 * Loads field groups from this plugin only.
	 *
	 * @param string[] $paths Default load paths (the active theme).
	 * @return string[]
	 */
	public static function load_paths( array $paths ): array {
		unset( $paths );

		return array( self::path() );
	}

	/**
	 * Hides the ACF admin screens in production.
	 */
	public static function show_admin(): bool {
		return 'production' !== wp_get_environment_type();
	}
}
