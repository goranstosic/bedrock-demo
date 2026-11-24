<?php
/**
 * Redirect importer.
 *
 * @package Northwind\Core
 */

declare( strict_types=1 );

namespace Northwind\Core\CLI;

use Red_Group;
use Red_Item;
use WP_CLI;

/**
 * Imports redirects from a CSV file into the Redirection plugin.
 *
 * Used for site migrations, where the redirect map is reviewed as a file in a
 * pull request and applied to every environment the same way.
 */
final class RedirectsCommand {

	/**
	 * Name of the Redirection group that holds imported redirects.
	 */
	public const GROUP_NAME = 'Northwind imports';

	/**
	 * Redirection's "WordPress" module, which serves redirects through PHP.
	 */
	private const MODULE_WORDPRESS = 1;

	/**
	 * HTTP status codes the importer accepts.
	 */
	private const CODES = array( 301, 302, 307, 308 );

	/**
	 * Columns the CSV must start with.
	 */
	private const COLUMNS = array( 'source', 'target', 'code' );

	/**
	 * Imports redirects from a CSV file.
	 *
	 * The file needs a header row with the columns source, target and code.
	 * Source is a path on this site (for example /old-page/). Target is a path
	 * or a full URL. Code is 301, 302, 307 or 308 and defaults to 301.
	 *
	 * The import is idempotent: redirects are matched by source path inside
	 * the "Northwind imports" group, then created, updated or left unchanged.
	 * Redirects added by hand in other groups are never touched. Valid rows are
	 * imported even when other rows are invalid; the command then exits with an
	 * error so CI and deploy scripts notice.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate the file and report what would change, without writing.
	 *
	 * [--format=<format>]
	 * : Format of the summary.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp northwind redirects import data/redirects.csv --dry-run
	 *     wp northwind redirects import data/redirects.csv
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		if ( ! class_exists( Red_Item::class ) || ! class_exists( Red_Group::class ) ) {
			WP_CLI::error( 'The Redirection plugin must be active before importing redirects.' );
		}

		$file    = $args[0];
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$format  = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$rows     = $this->read_csv( $file );
		$group_id = $dry_run ? $this->find_group() : $this->find_or_create_group();
		$report   = array();
		$totals   = array();
		$seen     = array();

		foreach ( $rows as $line => $row ) {
			$error = $this->validate( $row, $seen );

			if ( null !== $error ) {
				$result = 'invalid: ' . $error;
			} else {
				$seen[ $row['source'] ] = $line;
				$result                 = $this->apply( $row, $group_id, $dry_run );
			}

			$status            = strtok( $result, ':' );
			$totals[ $status ] = ( $totals[ $status ] ?? 0 ) + 1;
			$report[]          = array(
				'line'   => $line,
				'source' => $row['source'],
				'target' => $row['target'],
				'code'   => $row['code'],
				'result' => $result,
			);
		}

		WP_CLI\Utils\format_items( $format, $report, array( 'line', 'source', 'target', 'code', 'result' ) );

		$summary = sprintf(
			'%s%d created, %d updated, %d unchanged, %d invalid.',
			$dry_run ? 'Dry run, nothing was written. Would have: ' : '',
			$totals['created'] ?? 0,
			$totals['updated'] ?? 0,
			$totals['unchanged'] ?? 0,
			$totals['invalid'] ?? 0
		);

		if ( ! empty( $totals['invalid'] ) ) {
			WP_CLI::error( $summary );
		}

		WP_CLI::success( $summary );
	}

	/**
	 * Reads the CSV into rows keyed by line number.
	 *
	 * @param string $file Path to the CSV file.
	 * @return array<int, array{source: string, target: string, code: string}>
	 */
	private function read_csv( string $file ): array {
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'Cannot read "%s".', $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a local CSV in WP-CLI.
		$handle = fopen( $file, 'r' );
		$header = fgetcsv( $handle, null, ',', '"', '' );
		$header = is_array( $header ) ? array_map( static fn( $column ): string => strtolower( trim( (string) $column, " \t\n\r\0\x0B\xEF\xBB\xBF" ) ), $header ) : array();

		if ( array_slice( $header, 0, 3 ) !== self::COLUMNS ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			WP_CLI::error( sprintf( 'The first row of "%s" must be: %s', $file, implode( ',', self::COLUMNS ) ) );
		}

		$rows = array();
		$line = 1;

		while ( true ) {
			$values = fgetcsv( $handle, null, ',', '"', '' );

			if ( false === $values ) {
				break;
			}

			++$line;

			// Skip blank lines and comment lines.
			if ( array( null ) === $values || str_starts_with( trim( (string) $values[0] ), '#' ) ) {
				continue;
			}

			$rows[ $line ] = array(
				'source' => trim( (string) ( $values[0] ?? '' ) ),
				'target' => trim( (string) ( $values[1] ?? '' ) ),
				'code'   => trim( (string) ( $values[2] ?? '' ) ),
			);

			if ( '' === $rows[ $line ]['code'] ) {
				$rows[ $line ]['code'] = '301';
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		if ( ! $rows ) {
			WP_CLI::error( sprintf( '"%s" has no redirects.', $file ) );
		}

		return $rows;
	}

	/**
	 * Checks a row.
	 *
	 * @param array{source: string, target: string, code: string} $row  Row from the CSV.
	 * @param array<string, int>                                  $seen Sources already imported, with their line.
	 * @return string|null Reason the row is invalid, or null when it is valid.
	 */
	private function validate( array $row, array $seen ): ?string {
		if ( ! str_starts_with( $row['source'], '/' ) || str_starts_with( $row['source'], '//' ) ) {
			return 'source must be a path starting with /';
		}

		if ( isset( $seen[ $row['source'] ] ) ) {
			return sprintf( 'duplicate of line %d', $seen[ $row['source'] ] );
		}

		$target_is_path = str_starts_with( $row['target'], '/' ) && ! str_starts_with( $row['target'], '//' );
		$target_is_url  = (bool) preg_match( '#^https?://[^/\s]+#i', $row['target'] );

		if ( ! $target_is_path && ! $target_is_url ) {
			return 'target must be a path starting with / or an http(s) URL';
		}

		if ( untrailingslashit( $row['source'] ) === untrailingslashit( $row['target'] ) ) {
			return 'source and target are the same';
		}

		if ( ! in_array( (int) $row['code'], self::CODES, true ) || (string) (int) $row['code'] !== $row['code'] ) {
			return 'code must be one of ' . implode( ', ', self::CODES );
		}

		return null;
	}

	/**
	 * Creates or updates the redirect for one row.
	 *
	 * @param array{source: string, target: string, code: string} $row      Valid row.
	 * @param int                                                 $group_id Group ID, or 0 in a dry run before the group exists.
	 * @param bool                                                $dry_run  Whether to skip writing.
	 * @return string Result: created, updated or unchanged, optionally followed by details.
	 */
	private function apply( array $row, int $group_id, bool $dry_run ): string {
		$existing = $group_id ? $this->find_redirect( $group_id, $row['source'] ) : null;

		if ( $existing
			&& $existing->get_action_data() === $row['target']
			&& $existing->get_action_code() === (int) $row['code']
			&& $existing->is_enabled()
		) {
			return 'unchanged';
		}

		if ( $dry_run ) {
			return $existing ? 'updated' : 'created';
		}

		$details = array(
			'url'         => $row['source'],
			'match_type'  => 'url',
			'action_type' => 'url',
			'action_code' => (int) $row['code'],
			'action_data' => array( 'url' => $row['target'] ),
			'group_id'    => $group_id,
			'regex'       => false,
			'status'      => 'enabled',
		);

		if ( $existing ) {
			$result = $existing->update( $details );

			if ( is_wp_error( $result ) ) {
				return 'invalid: ' . $result->get_error_message();
			}

			if ( ! $existing->is_enabled() ) {
				$existing->enable();
			}

			return 'updated';
		}

		$result = Red_Item::create( $details );

		return is_wp_error( $result ) ? 'invalid: ' . $result->get_error_message() : 'created';
	}

	/**
	 * The imported redirect for a source path, if there is one.
	 *
	 * @param int    $group_id Group ID.
	 * @param string $source   Source path exactly as written in the CSV.
	 */
	private function find_redirect( int $group_id, string $source ): ?Red_Item {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Redirection has no lookup by exact source and group.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}redirection_items WHERE group_id = %d AND url = %s ORDER BY id LIMIT 1",
				$group_id,
				$source
			)
		);

		$item = $id ? Red_Item::get_by_id( (int) $id ) : false;

		return $item instanceof Red_Item ? $item : null;
	}

	/**
	 * The ID of the import group, or 0 when it does not exist yet.
	 */
	private function find_group(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Redirection has no lookup by group name.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}redirection_groups WHERE name = %s AND module_id = %d ORDER BY id LIMIT 1",
				self::GROUP_NAME,
				self::MODULE_WORDPRESS
			)
		);
	}

	/**
	 * The ID of the import group, created when missing.
	 */
	private function find_or_create_group(): int {
		$group_id = $this->find_group();

		if ( $group_id ) {
			return $group_id;
		}

		$group = Red_Group::create( self::GROUP_NAME, self::MODULE_WORDPRESS );

		if ( ! $group ) {
			WP_CLI::error( 'Could not create the Redirection group. Run "wp redirection database install" first.' );
		}

		return (int) $group->get_id();
	}
}
