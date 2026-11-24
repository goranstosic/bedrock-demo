<?php
/**
 * Northwind theme setup.
 *
 * The theme is presentation only: the content model lives in the
 * northwind-core must-use plugin.
 *
 * @package Northwind\Theme
 */

declare( strict_types=1 );

namespace Northwind\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports and editor styles.
 */
function setup(): void {
	add_editor_style( 'assets/css/base.css' );

	// The pattern library should only contain patterns owned by this project.
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\setup' );

/**
 * Enqueues the small global stylesheet.
 */
function enqueue_assets(): void {
	wp_enqueue_style(
		'northwind-base',
		get_theme_file_uri( 'assets/css/base.css' ),
		array(),
		(string) wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Registers every block found in /blocks.
 *
 * Each block.json declares its own stylesheet, which WordPress only prints on
 * pages where the block is rendered.
 */
function register_blocks(): void {
	foreach ( glob( __DIR__ . '/blocks/*/block.json' ) as $block_json ) {
		register_block_type( dirname( $block_json ) );
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Wrapper attributes for an ACF block, escaped and ready to print.
 *
 * Adds the block's base class, its alignment class and its anchor. This works
 * the same on the front end and in the editor preview, where
 * get_block_wrapper_attributes() has no block context.
 *
 * @param array<string, mixed>  $block      Block settings passed by ACF.
 * @param string                $base_class BEM block class, for example "nw-stats".
 * @param array<string, string> $extra     Extra attributes, for example aria-labelledby.
 */
function block_wrapper_attributes( array $block, string $base_class, array $extra = array() ): string {
	$classes = array( $base_class );

	if ( ! empty( $block['align'] ) && is_string( $block['align'] ) ) {
		$classes[] = 'align' . sanitize_html_class( $block['align'] );
	}

	if ( ! empty( $block['className'] ) && is_string( $block['className'] ) ) {
		foreach ( preg_split( '/\s+/', $block['className'] ) as $class_name ) {
			$classes[] = sanitize_html_class( $class_name );
		}
	}

	$attributes = array( 'class' => implode( ' ', array_filter( $classes ) ) );

	if ( ! empty( $block['anchor'] ) && is_string( $block['anchor'] ) ) {
		$attributes['id'] = $block['anchor'];
	}

	$attributes = array_merge( $attributes, $extra );
	$html       = array();

	foreach ( $attributes as $name => $value ) {
		$html[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}

	return implode( ' ', $html );
}

/**
 * A unique, stable ID for an element inside a block, such as its heading.
 *
 * @param array<string, mixed> $block  Block settings passed by ACF.
 * @param string               $suffix What the ID is for, for example "heading".
 */
function block_element_id( array $block, string $suffix ): string {
	$base = ! empty( $block['anchor'] ) && is_string( $block['anchor'] )
		? $block['anchor']
		: (string) ( $block['id'] ?? wp_unique_id( 'nw-block-' ) );

	return sanitize_html_class( $base . '-' . $suffix );
}

/**
 * Prints a hint that is only visible in the editor.
 *
 * Blocks render nothing on the front end when they have no content. In the
 * editor that would leave an invisible, unclickable block, so a short
 * explanation is shown instead.
 *
 * @param string $message Plain text message.
 */
function editor_hint( string $message ): void {
	printf( '<p class="nw-editor-hint">%s</p>', esc_html( $message ) );
}

/**
 * Registers the pattern category used by this theme.
 */
function register_pattern_categories(): void {
	register_block_pattern_category(
		'northwind',
		array(
			'label'       => __( 'Northwind', 'northwind' ),
			'description' => __( 'Layouts built from the Northwind block library.', 'northwind' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_pattern_categories' );

/**
 * Adds a "Northwind" category at the top of the block inserter.
 *
 * @param array<int, array<string, mixed>> $categories Registered block categories.
 * @return array<int, array<string, mixed>>
 */
function register_block_category( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'northwind',
			'title' => __( 'Northwind', 'northwind' ),
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', __NAMESPACE__ . '\\register_block_category' );
