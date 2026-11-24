<?php
/**
 * Pattern library admin page.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core;

use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;

/**
 * Lists every registered block pattern under Appearance > Pattern library.
 *
 * Gives editors and reviewers one place to see which patterns exist, what
 * they are for and which blocks they are made of.
 */
final class PatternLibrary {

	public const PAGE_SLUG  = 'northwind-pattern-library';
	public const CAPABILITY = 'edit_posts';

	/**
	 * Hooks into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
	}

	/**
	 * Adds the page to the Appearance menu.
	 */
	public static function add_page(): void {
		add_theme_page(
			__( 'Pattern library', 'northwind' ),
			__( 'Pattern library', 'northwind' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * The registered patterns, sorted by title.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function patterns(): array {
		$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();

		usort(
			$patterns,
			static fn( array $a, array $b ): int => strcasecmp( (string) $a['title'], (string) $b['title'] )
		);

		return $patterns;
	}

	/**
	 * The names of the blocks a pattern is made of.
	 *
	 * @param string $content Serialized pattern content.
	 * @return string[]
	 */
	public static function block_names( string $content ): array {
		$names = array();
		$queue = parse_blocks( $content );

		while ( $queue ) {
			$block = array_shift( $queue );

			if ( ! empty( $block['blockName'] ) ) {
				$names[] = $block['blockName'];
			}

			foreach ( $block['innerBlocks'] as $inner_block ) {
				$queue[] = $inner_block;
			}
		}

		$names = array_unique( $names );
		sort( $names );

		return $names;
	}

	/**
	 * Renders the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$patterns   = self::patterns();
		$categories = WP_Block_Pattern_Categories_Registry::get_instance();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pattern library', 'northwind' ); ?></h1>
			<p>
				<?php esc_html_e( 'Patterns are ready-made block layouts. Insert one from the block inserter, under the Patterns tab, then replace the sample content.', 'northwind' ); ?>
			</p>

			<?php if ( ! $patterns ) : ?>
				<p><?php esc_html_e( 'No patterns are registered.', 'northwind' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<caption class="screen-reader-text"><?php esc_html_e( 'Registered block patterns', 'northwind' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Pattern', 'northwind' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Description', 'northwind' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Categories', 'northwind' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Blocks used', 'northwind' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $patterns as $pattern ) : ?>
							<?php
							$labels = array();

							foreach ( (array) ( $pattern['categories'] ?? array() ) as $category_slug ) {
								$category = $categories->get_registered( $category_slug );
								$labels[] = $category['label'] ?? $category_slug;
							}
							?>
							<tr>
								<th scope="row">
									<strong><?php echo esc_html( $pattern['title'] ); ?></strong><br>
									<code><?php echo esc_html( $pattern['name'] ); ?></code>
								</th>
								<td><?php echo esc_html( $pattern['description'] ?? '' ); ?></td>
								<td><?php echo esc_html( implode( ', ', $labels ) ); ?></td>
								<td>
									<?php foreach ( self::block_names( (string) ( $pattern['content'] ?? '' ) ) as $block_name ) : ?>
										<code><?php echo esc_html( $block_name ); ?></code>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
