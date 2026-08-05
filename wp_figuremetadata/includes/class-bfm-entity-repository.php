<?php
/**
 * BFM_Entity_Repository
 *
 * Resolves the unique values (and post counts) found in the structured
 * figure metadata, for a given entity (manufacturer, illustrator, series,
 * character), and resolves which posts contain a specific entity value.
 *
 * Performance:
 * Each meta field stores a single string value per post (possibly
 * containing several "&"-separated names). A single indexed query per
 * entity retrieves every non-empty value for that meta_key across
 * published posts; splitting by "&" happens in PHP. This avoids both a
 * WP_Query loop over every post and any N+1 pattern: one query per
 * entity screen load, regardless of how many posts exist.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Entity_Repository {

	/**
	 * Fetch every non-empty ( post_id, meta_value ) row for a meta key,
	 * across all relevant post statuses.
	 *
	 * Includes published, scheduled.
	 * Excludes draft, pending, private poststrash, auto-draft and internal/inherited posts.
	 *
	 * Shared by get_values_with_counts() and
	 * get_matching_post_ids() so the query text exists in exactly one
	 * place.
	 *
	 * @param string $meta_key Post meta key to read.
	 * @return array<int, object{post_id: int, meta_value: string}>
	 */
	private static function raw_meta_rows( string $meta_key ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = 'post'
				AND p.post_status IN ('publish', 'future')",
				/* AND p.post_status = 'publish'", */
				$meta_key
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get unique values and post counts for a given meta key.
	 *
	 * @param string $meta_key Post meta key to read (e.g. _bunny_figure_manufacturer).
	 * @return array<string, int> Map of value name => number of posts it appears in,
	 *                            ordered by post count descending, then name ascending.
	 */
	public static function get_values_with_counts( string $meta_key ): array {

		$rows = self::raw_meta_rows( $meta_key );

		$counts = array();

		if ( empty( $rows ) ) {
			return $counts;
		}

		$separator      = BFM_Meta_Fields::value_separator();
		$excluded_value = BFM_Meta_Fields::excluded_value();

		foreach ( $rows as $row ) {

			$names = explode( $separator, (string) $row->meta_value );

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

	/**
	 * Re-order an already-resolved values map by name or post count.
	 *
	 * Pure in-memory sort of the array already produced by
	 * get_values_with_counts() — no additional query, no loop over posts.
	 * Used by the admin screens to honor the sortable column headers.
	 *
	 * @param array<string, int> $values  Map of value name => post count.
	 * @param string              $orderby 'name' or 'posts'.
	 * @param string              $order   'asc' or 'desc'.
	 * @return array<string, int> Re-ordered map.
	 */
	public static function sort_values( array $values, string $orderby, string $order ): array {

		$orderby = in_array( $orderby, array( 'name', 'posts' ), true ) ? $orderby : 'posts';
		$order   = 'asc' === $order ? 'asc' : 'desc';

		uksort(
			$values,
			static function ( $a, $b ) use ( $values, $orderby, $order ) {

				if ( 'name' === $orderby ) {
					$result = strcasecmp( $a, $b );
					return 'asc' === $order ? $result : -$result;
				}

				$result = $values[ $a ] <=> $values[ $b ];

				if ( 0 === $result ) {
					// Ties always break by name ascending, regardless of the
					// requested primary direction — only the primary comparison
					// (post count) is reversed for 'desc', not the tie-break.
					return strcasecmp( $a, $b );
				}

				return 'asc' === $order ? $result : -$result;
			}
		);

		return $values;
	}

	/**
	 * Resolve which post IDs contain an exact entity value in a meta
	 * field, treating "&"-separated values as independent values (a post
	 * whose field is "A & B" matches both "A" and "B").
	 *
	 * Reuses the same raw_meta_rows() query as get_values_with_counts(),
	 * so this is a single query regardless of how many posts exist.
	 *
	 * @param string $meta_key Post meta key to read.
	 * @param string $value    Exact entity value to match (already trimmed).
	 * @return array<int, int> Matching post IDs.
	 */
	public static function get_matching_post_ids( string $meta_key, string $value ): array {

		$rows = self::raw_meta_rows( $meta_key );
		$separator = BFM_Meta_Fields::value_separator();
		$post_ids  = array();

		foreach ( $rows as $row ) {

			foreach ( explode( $separator, (string) $row->meta_value ) as $name ) {

				if ( $value === trim( $name ) ) {
					$post_ids[] = (int) $row->post_id;
					break;
				}
			}
		}

		return $post_ids;
	}

	/**
	 * Get a paginated page of posts that contain an exact entity value,
	 * along with total counts for pagination.
	 *
	 * Only two queries are issued in total, regardless of how many posts
	 * match: one to resolve the full list of matching post IDs (shared,
	 * see get_matching_post_ids()), and one WP_Query scoped to that list
	 * with post__in + posts_per_page to fetch just the current page's
	 * post objects. found_posts is derived from the already-known total
	 * instead of an extra SQL_CALC_FOUND_ROWS query.
	 *
	 * @param string $meta_key Post meta key to read.
	 * @param string $value    Exact entity value to match.
	 * @param int    $paged    1-indexed page number.
	 * @param int    $per_page Posts per page.
	 * @return array{posts: WP_Post[], total: int, total_pages: int}
	 */
	public static function get_posts_for_value( string $meta_key, string $value, int $paged = 1, int $per_page = 20 ): array {

		$post_ids = self::get_matching_post_ids( $meta_key, $value );
		$total    = count( $post_ids );

		if ( 0 === $total ) {
			return array(
				'posts'       => array(),
				'total'       => 0,
				'total_pages' => 0,
			);
		}

		$paged    = max( 1, $paged );
		$per_page = max( 1, $per_page );

		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => array(
					'publish',
					'future',
				),
				'post__in'            => $post_ids,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'paged'               => $paged,
				'posts_per_page'      => $per_page,
				'ignore_sticky_posts' => true,
				// We already know the total from $post_ids, so skip the extra
				// SQL_CALC_FOUND_ROWS query WP_Query would otherwise run.
				'no_found_rows'       => true,
			)
		);

		return array(
			'posts'       => $query->posts,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}
}
