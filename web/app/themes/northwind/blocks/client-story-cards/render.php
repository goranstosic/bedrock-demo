<?php
/**
 * Client Story Cards block.
 *
 * Lists the client stories related to a sector that are visible in the
 * visitor's region. Uses the sector picked in the block, or the sector being
 * viewed when none is picked. Renders nothing on the front end when no story
 * matches.
 *
 * @package Northwind\Theme
 *
 * @var array<string, mixed> $block      Block settings and attributes.
 * @var bool                 $is_preview True in the editor.
 * @var int|string           $post_id    ID of the post the block is in.
 */

declare( strict_types=1 );

use Northwind\Core\Region;
use Northwind\Core\Stories;
use Northwind\Core\Taxonomies;

use function Northwind\Theme\block_element_id;
use function Northwind\Theme\block_wrapper_attributes;
use function Northwind\Theme\editor_hint;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( Stories::class ) ) {
	return;
}

$nw_sector_id = absint( get_field( 'sector' ) );
if ( ! $nw_sector_id ) {
	$nw_sector_id = Stories::contextual_sector_id( absint( $post_id ) );
}

if ( ! $nw_sector_id ) {
	if ( $is_preview ) {
		editor_hint( __( 'Client Story Cards: choose a sector in the block settings, or place this block on a sector page.', 'northwind' ) );
	}

	return;
}

$nw_stories = Stories::for_sector( $nw_sector_id );

if ( ! $nw_stories ) {
	if ( $is_preview ) {
		$nw_regions = Taxonomies::regions();
		editor_hint(
			sprintf(
				/* translators: 1: sector name, 2: region name. */
				__( 'Client Story Cards: %1$s has no published client stories for %2$s or Global, so visitors see nothing here.', 'northwind' ),
				get_the_title( $nw_sector_id ),
				$nw_regions[ Region::current() ] ?? Region::current()
			)
		);
	}

	return;
}

$nw_heading    = trim( (string) get_field( 'heading' ) );
$nw_heading    = $nw_heading ? $nw_heading : __( 'Client stories', 'northwind' );
$nw_heading_id = block_element_id( $block, 'heading' );
?>
<section <?php echo block_wrapper_attributes( $block, 'nw-story-cards', array( 'aria-labelledby' => $nw_heading_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>>
	<h2 class="nw-story-cards__heading" id="<?php echo esc_attr( $nw_heading_id ); ?>"><?php echo esc_html( $nw_heading ); ?></h2>

	<ul class="nw-story-cards__list" role="list">
		<?php foreach ( $nw_stories as $nw_story ) : ?>
			<li class="nw-story-card">
				<h3 class="nw-story-card__title">
					<a class="nw-story-card__link" href="<?php echo esc_url( get_permalink( $nw_story ) ); ?>">
						<?php echo esc_html( get_the_title( $nw_story ) ); ?>
					</a>
				</h3>
				<?php if ( has_excerpt( $nw_story ) ) : ?>
					<p class="nw-story-card__excerpt"><?php echo esc_html( get_the_excerpt( $nw_story ) ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
