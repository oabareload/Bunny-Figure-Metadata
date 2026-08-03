<?php
/**
 * BFM_Entity_Repository
 *
 * Resolves the unique values (and post counts) found in the structured
 * figure metadata, for a given entity (manufacturer, illustrator, series,
 * character).
 *
 * Performance:
 * Each meta field stores a single string value per post (possibly
 * containing several "&"-separated names). A single indexed query per
 * entity retrieves every non-empty value for that meta_key across
 * published posts; splitting by "&" and tallying counts happens in PHP.
 * This avoids both a WP_Query loop over every post and any N+1 pattern:
 * one query per entity screen load, regardless of how many posts exist.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Entity_Repository {

	/**
	 * Get unique values and post counts for a given meta key.
	 *
	 * @param string $meta_key Post meta key to read (e.g. _bunny_figure_manufacturer).
	 * @return array<string, int> Map of value name => number of posts it appears in,
	 *                            ordered by post count descending, then name ascending.
	 */
	public static function get_values_with_counts( string $meta_key ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = 'post'
				AND p.post_status = 'publish'",
				$meta_key
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$counts = array();

		if ( empty( $rows ) ) {
			return $counts;
		}

		$separator      = BFM_Meta_Fields::value_separator();
		$excluded_value = BFM_Meta_Fields::excluded_value();

		foreach ( $rows as $raw_value ) {

			$names = explode( $separator, (string) $raw_value );

			foreach ( $names as $name ) {

				$name = trim( $name );

				if ( '' === $name ) {
					continue;
				}

				if ( $excluded_value === strtolower( $name ) ) {
					continue;
				}

				if ( ! isset( $counts[ $name ] ) ) {
					$counts[ $name ] = 0;
				}

				++$counts[ $name ];
			}
		}

		uksort(
			$counts,
			static function ( $a, $b ) use ( $counts ) {

				if ( $counts[ $a ] === $counts[ $b ] ) {
					return strcasecmp( $a, $b );
				}

				return $counts[ $b ] <=> $counts[ $a ];
			}
		);

		return $counts;
	}
}
