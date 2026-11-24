<?php
/**
 * Region Switcher block.
 *
 * A short list of links, one per region, each pointing at the current page in
 * that region. Plain links work without JavaScript and keep every region on
 * its own cacheable URL.
 *
 * @package Northwind\Theme
 *
 * @var array<string, mixed> $block      Block settings and attributes.
 * @var bool                 $is_preview True in the editor.
 */

declare( strict_types=1 );

use Northwind\Core\Region;
use Northwind\Core\Taxonomies;

use function Northwind\Theme\block_element_id;
use function Northwind\Theme\block_wrapper_attributes;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( Region::class ) ) {
	return;
}

$nw_current = Region::current();
$nw_label   = block_element_id( $block, 'label' );

// The page the visitor is on, without the region parameter.
$nw_request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
$nw_page    = $is_preview ? home_url( '/' ) : home_url( $nw_request );
?>
<nav <?php echo block_wrapper_attributes( $block, 'nw-region-switcher', array( 'aria-labelledby' => $nw_label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>>
	<span class="nw-region-switcher__label" id="<?php echo esc_attr( $nw_label ); ?>"><?php esc_html_e( 'Region', 'northwind' ); ?></span>
	<ul class="nw-region-switcher__list" role="list">
		<?php foreach ( Taxonomies::regions() as $nw_slug => $nw_name ) : ?>
			<li>
				<a
					class="nw-region-switcher__link"
					href="<?php echo esc_url( Region::url_for( $nw_page, $nw_slug ) ); ?>"
					<?php echo $nw_current === $nw_slug ? 'aria-current="true"' : ''; ?>
				><?php echo esc_html( $nw_name ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
