<?php
/**
 * Demo content seeder.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core\CLI;

use Northwind\Core\PostTypes;
use Northwind\Core\Stories;
use Northwind\Core\Taxonomies;
use WP_CLI;

/**
 * Creates the sample content the demo needs.
 */
final class SeedCommand {

	/**
	 * Number of items created per content type, for the summary.
	 *
	 * @var array<string, int>
	 */
	private array $created = array();

	/**
	 * Number of items that already existed per content type.
	 *
	 * @var array<string, int>
	 */
	private array $skipped = array();

	/**
	 * Seeds regions, sectors, client stories and a home page.
	 *
	 * Safe to run more than once: content is matched by slug and existing
	 * items are left untouched.
	 *
	 * ## EXAMPLES
	 *
	 *     wp northwind seed
	 *
	 * @when after_wp_load
	 */
	public function __invoke(): void {
		if ( ! function_exists( 'update_field' ) ) {
			WP_CLI::error( 'Advanced Custom Fields Pro must be active before seeding.' );
		}

		$this->seed_regions();

		$sector_ids = array();
		foreach ( $this->sectors() as $slug => $sector ) {
			$sector_ids[ $slug ] = $this->seed_post(
				PostTypes::SECTOR,
				$slug,
				$sector['title'],
				$sector['excerpt'],
				$this->paragraph( $sector['intro'] ) . $this->stats_block( $sector['stats_heading'], $sector['stats'] )
			);
		}

		foreach ( $this->stories() as $slug => $story ) {
			$story_id = $this->seed_post(
				PostTypes::CLIENT_STORY,
				$slug,
				$story['title'],
				$story['excerpt'],
				$this->paragraph( $story['excerpt'] ) . $this->paragraph( $story['body'] )
			);

			wp_set_object_terms( $story_id, $story['regions'], Taxonomies::REGION );

			/*
			 * Writing one side of the bidirectional relationship is enough:
			 * ACF mirrors it onto the "client_stories" field of each sector.
			 */
			update_field(
				Stories::STORY_SECTORS_FIELD,
				array_map( static fn( string $sector ): int => $sector_ids[ $sector ], $story['sectors'] ),
				$story_id
			);
		}

		$home_id = $this->seed_post(
			'page',
			'home',
			'Technology partners for regulated industries',
			'',
			$this->home_content( $sector_ids['financial-services'] )
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );

		$rows = array();
		foreach ( array_keys( $this->created + $this->skipped ) as $type ) {
			$rows[] = array(
				'content' => $type,
				'created' => $this->created[ $type ] ?? 0,
				'existed' => $this->skipped[ $type ] ?? 0,
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'content', 'created', 'existed' ) );
		WP_CLI::success( 'Demo content is in place.' );
	}

	/**
	 * Creates the region terms.
	 */
	private function seed_regions(): void {
		foreach ( Taxonomies::regions() as $slug => $name ) {
			if ( term_exists( $slug, Taxonomies::REGION ) ) {
				$this->count( 'region', false );
				continue;
			}

			$term = wp_insert_term( $name, Taxonomies::REGION, array( 'slug' => $slug ) );

			if ( is_wp_error( $term ) ) {
				WP_CLI::error( sprintf( 'Could not create region "%s": %s', $slug, $term->get_error_message() ) );
			}

			$this->count( 'region', true );
		}
	}

	/**
	 * Creates a post unless one with the same slug exists.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Post slug.
	 * @param string $title     Post title.
	 * @param string $excerpt   Post excerpt.
	 * @param string $content   Serialized block content.
	 * @return int Post ID.
	 */
	private function seed_post( string $post_type, string $slug, string $title, string $excerpt, string $content ): int {
		$existing = get_page_by_path( $slug, OBJECT, $post_type );

		if ( $existing ) {
			$this->count( $post_type, false );

			return $existing->ID;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_content' => wp_slash( $content ),
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( sprintf( 'Could not create %s "%s": %s', $post_type, $slug, $post_id->get_error_message() ) );
		}

		$this->count( $post_type, true );

		return $post_id;
	}

	/**
	 * Records an item for the summary table.
	 *
	 * @param string $type    Content type.
	 * @param bool   $created Whether the item was created (true) or already existed (false).
	 */
	private function count( string $type, bool $created ): void {
		if ( $created ) {
			$this->created[ $type ] = ( $this->created[ $type ] ?? 0 ) + 1;
		} else {
			$this->skipped[ $type ] = ( $this->skipped[ $type ] ?? 0 ) + 1;
		}
	}

	/**
	 * A serialized paragraph block.
	 *
	 * @param string $text Plain text.
	 */
	private function paragraph( string $text ): string {
		return sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n", esc_html( $text ) );
	}

	/**
	 * A serialized Stats block.
	 *
	 * @param string                    $heading Block heading.
	 * @param array<int, array<string>> $stats   Rows of number, label and optional source.
	 */
	private function stats_block( string $heading, array $stats ): string {
		$data = array(
			'heading'  => $heading,
			'_heading' => 'field_nw_stats_heading',
		);

		foreach ( $stats as $index => $stat ) {
			$data[ "stats_{$index}_number" ]  = $stat[0];
			$data[ "_stats_{$index}_number" ] = 'field_nw_stats_item_number';
			$data[ "stats_{$index}_label" ]   = $stat[1];
			$data[ "_stats_{$index}_label" ]  = 'field_nw_stats_item_label';
			$data[ "stats_{$index}_source" ]  = $stat[2] ?? '';
			$data[ "_stats_{$index}_source" ] = 'field_nw_stats_item_source';
		}

		$data['stats']  = count( $stats );
		$data['_stats'] = 'field_nw_stats_items';

		return $this->acf_block( 'northwind/stats', $data, array( 'align' => 'wide' ) );
	}

	/**
	 * A serialized Client Story Cards block.
	 *
	 * @param string $heading   Block heading.
	 * @param int    $sector_id Sector post ID.
	 */
	private function cards_block( string $heading, int $sector_id ): string {
		return $this->acf_block(
			'northwind/client-story-cards',
			array(
				'heading'  => $heading,
				'_heading' => 'field_nw_cards_heading',
				'sector'   => $sector_id,
				'_sector'  => 'field_nw_cards_sector',
			),
			array( 'align' => 'wide' )
		);
	}

	/**
	 * A serialized ACF block.
	 *
	 * @param string               $name       Block name.
	 * @param array<string, mixed> $data       ACF field data.
	 * @param array<string, mixed> $attributes Additional block attributes.
	 */
	private function acf_block( string $name, array $data, array $attributes = array() ): string {
		$attributes = array_merge(
			array(
				'name' => $name,
				'data' => $data,
				'mode' => 'preview',
			),
			$attributes
		);

		return get_comment_delimited_block_content( $name, $attributes, '' ) . "\n\n";
	}

	/**
	 * Content of the seeded home page.
	 *
	 * @param int $sector_id Sector whose client stories are featured.
	 */
	private function home_content( int $sector_id ): string {
		$content  = $this->paragraph( 'Northwind helps banks, hospitals, utilities and public bodies ship dependable digital services. This page is seeded demo content: every figure and client name is fictional.' );
		$content .= $this->stats_block(
			'Northwind in numbers',
			array(
				array( '120+', 'Delivery specialists', '' ),
				array( '14', 'Years in regulated industries', '' ),
				array( '96%', 'Clients who renew', 'Client survey, sample data' ),
			)
		);
		$content .= $this->cards_block( 'Financial services stories', $sector_id );

		return $content;
	}

	/**
	 * Sample sectors, keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function sectors(): array {
		return array(
			'financial-services' => array(
				'title'         => 'Financial Services',
				'excerpt'       => 'Digital onboarding, payments and claims platforms for banks and insurers.',
				'intro'         => 'We help banks, credit unions and insurers replace slow, paper-heavy journeys with services their customers can finish in one sitting, without loosening compliance.',
				'stats_heading' => 'Financial services at a glance',
				'stats'         => array(
					array( '60%', 'Faster customer onboarding', 'Average across sample projects' ),
					array( '32', 'Platforms in production', '' ),
					array( '99.98%', 'Measured uptime', '' ),
				),
			),
			'healthcare'         => array(
				'title'         => 'Healthcare',
				'excerpt'       => 'Patient-facing services and clinical tools that work under pressure.',
				'intro'         => 'From booking to discharge, we design and build services that give clinicians time back and give patients a clear view of their care.',
				'stats_heading' => 'Healthcare at a glance',
				'stats'         => array(
					array( '4.2m', 'Patient records unified', '' ),
					array( '27%', 'Shorter waiting lists', 'Sample data' ),
				),
			),
			'energy-utilities'   => array(
				'title'         => 'Energy and Utilities',
				'excerpt'       => 'Asset data, field tooling and customer portals for network operators.',
				'intro'         => 'Networks generate more data than teams can read. We turn it into maintenance schedules, outage maps and bills that customers understand.',
				'stats_heading' => 'Energy and utilities at a glance',
				'stats'         => array(
					array( '18%', 'Fewer unplanned outages', 'Sample data' ),
					array( '9', 'Network operators supported', '' ),
				),
			),
			'public-sector'      => array(
				'title'         => 'Public Sector',
				'excerpt'       => 'Accessible, plain-language services for local and national government.',
				'intro'         => 'Public services have to work for everyone. We build to WCAG 2.2 AA from the first prototype and test with the people who rely on the service.',
				'stats_heading' => 'Public sector at a glance',
				'stats'         => array(
					array( '45', 'Services launched', '' ),
					array( '100%', 'Audited to WCAG 2.2 AA', '' ),
				),
			),
		);
	}

	/**
	 * Sample client stories, keyed by slug.
	 *
	 * The public sector story is deliberately limited to Australia, so the
	 * Public Sector page shows the Client Story Cards block rendering nothing
	 * in every other region.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function stories(): array {
		$body = 'This client story is sample content created by the seed command. Replace it with the challenge, the approach and the measurable outcome of a real engagement.';

		return array(
			'harbour-bank-onboarding'        => array(
				'title'   => 'Harbour Bank cuts onboarding time by 60%',
				'excerpt' => 'A rebuilt account opening journey takes new customers from first click to a funded account in under ten minutes.',
				'body'    => $body,
				'regions' => array( 'global' ),
				'sectors' => array( 'financial-services' ),
			),
			'meridian-mutual-claims'         => array(
				'title'   => 'Meridian Mutual settles simple claims in a day',
				'excerpt' => 'Straight-through processing now handles two thirds of household claims without manual review.',
				'body'    => $body,
				'regions' => array( 'us' ),
				'sectors' => array( 'financial-services' ),
			),
			'thames-credit-union-digital'    => array(
				'title'   => 'Thames Credit Union opens its first digital branch',
				'excerpt' => 'Members can join, save and borrow online, with the same staff on hand through secure messaging.',
				'body'    => $body,
				'regions' => array( 'uk' ),
				'sectors' => array( 'financial-services' ),
			),
			'southern-cross-health-records'  => array(
				'title'   => 'Southern Cross Health unifies 4.2 million patient records',
				'excerpt' => 'One record per patient across eleven hospitals, available to clinicians at the bedside.',
				'body'    => $body,
				'regions' => array( 'au' ),
				'sectors' => array( 'healthcare' ),
			),
			'lakeside-clinics-waiting-lists' => array(
				'title'   => 'Lakeside Clinics shorten waiting lists by 27%',
				'excerpt' => 'Self-service rebooking fills cancelled appointments within hours instead of leaving them empty.',
				'body'    => $body,
				'regions' => array( 'global' ),
				'sectors' => array( 'healthcare' ),
			),
			'pennine-power-maintenance'      => array(
				'title'   => 'Pennine Power moves to predictive maintenance',
				'excerpt' => 'Sensor data now schedules crews before a fault, cutting unplanned outages by 18%.',
				'body'    => $body,
				'regions' => array( 'uk' ),
				'sectors' => array( 'energy-utilities' ),
			),
			'coastal-grid-outage-map'        => array(
				'title'   => 'Coastal Grid gives customers a live outage map',
				'excerpt' => 'Call volumes during storms fell by a third once customers could see restoration times for themselves.',
				'body'    => $body,
				'regions' => array( 'us', 'au' ),
				'sectors' => array( 'energy-utilities' ),
			),
			'riverina-council-permits'       => array(
				'title'   => 'Riverina Council moves permits online',
				'excerpt' => 'Residents apply, pay and track building permits in one accessible service.',
				'body'    => $body,
				'regions' => array( 'au' ),
				'sectors' => array( 'public-sector' ),
			),
		);
	}
}
