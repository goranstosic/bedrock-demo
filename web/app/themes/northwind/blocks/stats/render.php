<?php
/**
 * Stats block.
 *
 * Renders nothing on the front end when there are no complete rows.
 *
 * @package Northwind\Theme
 *
 * @var array<string, mixed> $block      Block settings and attributes.
 * @var bool                 $is_preview True in the editor.
 */

declare( strict_types=1 );

use function Northwind\Theme\block_element_id;
use function Northwind\Theme\block_wrapper_attributes;
use function Northwind\Theme\editor_hint;

defined( 'ABSPATH' ) || exit;

$nw_heading = trim( (string) get_field( 'heading' ) );
$nw_stats   = array_filter(
	(array) get_field( 'stats' ),
	static fn( $row ): bool => is_array( $row ) && '' !== trim( (string) ( $row['number'] ?? '' ) ) && '' !== trim( (string) ( $row['label'] ?? '' ) )
);

if ( ! $nw_stats ) {
	if ( $is_preview ) {
		editor_hint( __( 'Stats: add at least one figure with a number and a label.', 'northwind' ) );
	}

	return;
}

$nw_heading_id = block_element_id( $block, 'heading' );
$nw_extra      = $nw_heading ? array( 'aria-labelledby' => $nw_heading_id ) : array();
?>
<section <?php echo block_wrapper_attributes( $block, 'nw-stats', $nw_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>>
	<?php if ( $nw_heading ) : ?>
		<h2 class="nw-stats__heading" id="<?php echo esc_attr( $nw_heading_id ); ?>"><?php echo esc_html( $nw_heading ); ?></h2>
	<?php endif; ?>

	<ul class="nw-stats__list" role="list">
		<?php foreach ( $nw_stats as $nw_stat ) : ?>
			<li class="nw-stats__item">
				<p class="nw-stats__number"><?php echo esc_html( $nw_stat['number'] ); ?></p>
				<p class="nw-stats__label"><?php echo esc_html( $nw_stat['label'] ); ?></p>
				<?php if ( ! empty( $nw_stat['source'] ) ) : ?>
					<p class="nw-stats__source">
						<?php
						/* translators: %s: where a figure comes from. */
						echo esc_html( sprintf( __( 'Source: %s', 'northwind' ), $nw_stat['source'] ) );
						?>
					</p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
