<?php
/**
 * Plugin Name:  Northwind Core
 * Description:  Content model, versioned ACF field groups, WP-CLI commands and editorial tooling for the Northwind Demo site.
 * Version:      1.0.0
 * Requires PHP: 8.3
 * Author:       Northwind Demo
 * License:      MIT
 * Text Domain:  northwind
 *
 * Everything in here must keep working when the theme changes, which is why
 * it lives in a must-use plugin and not in the theme.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

use Northwind\Core\CLI\RedirectsCommand;
use Northwind\Core\CLI\SeedCommand;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const PLUGIN_DIR = __DIR__;

/*
 * Classes are autoloaded by Composer (see "autoload" in composer.json). The
 * guard keeps the site alive with a clear admin notice if the autoloader is
 * missing, for example after a deploy without a build step.
 */
if ( ! class_exists( PostTypes::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Northwind Core is inactive: the Composer autoloader is not loaded. Run "composer install".', 'northwind' )
			);
		}
	);

	return;
}

PostTypes::register();
Taxonomies::register();
AcfJson::register();
Region::register();
PatternLibrary::register();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'northwind seed', SeedCommand::class );
	\WP_CLI::add_command( 'northwind redirects', RedirectsCommand::class );
}
