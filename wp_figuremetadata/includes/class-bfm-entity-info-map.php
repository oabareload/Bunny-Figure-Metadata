<?php
/**
 * BFM_Entity_Info_Map
 *
 * Stores complementary information for an entity value (manufacturer,
 * illustrator, series, character): an official/external URL and a logo
 * image (a Media Library attachment ID).
 *
 * This information belongs to the *entity value* (e.g. "Good Smile
 * Company"), not to individual posts, and is independent from the TAG
 * association stored in BFM_Entity_Tag_Map.
 *
 * Persistence:
 * A single row in wp_options (autoload disabled) holding a nested array
 * keyed by meta_key => value name => [ 'url' => string, 'image_id' => int ].
 * No custom database table is needed for this, following the same
 * pattern already used by BFM_Entity_Tag_Map.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Entity_Info_Map {

	/**
	 * Option name used to store the full map.
	 */
	const OPTION_NAME = 'bfm_entity_info_map';

	/**
	 * Read the full map from the options table.
	 *
	 * @return array<string, array<string, array{url: string, image_id: int}>>
	 */
	private static function get_map(): array {
		$map = get_option( self::OPTION_NAME, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Normalize a raw stored entry to always have both keys.
	 *
	 * @param mixed $entry Raw stored value.
	 * @return array{url: string, image_id: int}
	 */
	private static function normalize( $entry ): array {
		$entry = is_array( $entry ) ? $entry : array();

		return array(
			'url'      => isset( $entry['url'] ) ? (string) $entry['url'] : '',
			'image_id' => isset( $entry['image_id'] ) ? absint( $entry['image_id'] ) : 0,
		);
	}

	/**
	 * Get the complementary info for a single entity value.
	 *
	 * @param string $meta_key Source meta key.
	 * @param string $value    Entity value name.
	 * @return array{url: string, image_id: int}
	 */
	public static function get( string $meta_key, string $value ): array {
		$map = self::get_map();

		return self::normalize( $map[ $meta_key ][ $value ] ?? null );
	}

	/**
	 * Get complementary info for every value under a given meta key.
	 *
	 * @param string $meta_key Source meta key.
	 * @return array<string, array{url: string, image_id: int}>
	 */
	public static function get_for_meta_key( string $meta_key ): array {
		$map = self::get_map();

		if ( ! isset( $map[ $meta_key ] ) || ! is_array( $map[ $meta_key ] ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'normalize' ), $map[ $meta_key ] );
	}

	/**
	 * Batch-resolve Media Library thumbnail URLs for a list of attachment
	 * IDs. There is no core "get many attachments' image src" API, so a
	 * single get_posts() call primes WordPress's own post/meta object
	 * cache for every attachment ID involved; the subsequent
	 * wp_get_attachment_image_src() calls then hit that cache instead of
	 * issuing one query per attachment. Shared by the admin entity list
	 * screens and the "Entidades destacadas" block, so this batching
	 * exists in exactly one place.
	 *
	 * @param array<int, int> $image_ids Media Library attachment IDs.
	 * @param string           $size      Registered image size to resolve.
	 * @return array<int, string> Map of image_id => thumbnail URL (only for IDs that resolved).
	 */
	public static function get_logo_urls( array $image_ids, string $size = 'thumbnail' ): array {

		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );

		if ( empty( $image_ids ) ) {
			return array();
		}

		get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post__in'       => $image_ids,
				'posts_per_page' => count( $image_ids ),
				'orderby'        => 'post__in',
			)
		);

		$urls = array();

		foreach ( $image_ids as $image_id ) {

			$src = wp_get_attachment_image_src( $image_id, $size );

			if ( $src ) {
				$urls[ $image_id ] = $src[0];
			}
		}

		return $urls;
	}

	/**
	 * Set (or clear, with an empty string) the URL for an entity value.
	 *
	 * @param string $meta_key Source meta key.
	 * @param string $value    Entity value name.
	 * @param string $url      URL, already validated/sanitized by the caller.
	 * @return bool True on success.
	 */
	public static function set_url( string $meta_key, string $value, string $url ): bool {
		$map = self::get_map();

		$entry        = self::normalize( $map[ $meta_key ][ $value ] ?? null );
		$entry['url'] = $url;

		return self::save_entry( $map, $meta_key, $value, $entry );
	}

	/**
	 * Set the logo attachment ID for an entity value.
	 *
	 * @param string $meta_key   Source meta key.
	 * @param string $value      Entity value name.
	 * @param int    $image_id   Media Library attachment ID.
	 * @return bool True on success.
	 */
	public static function set_image( string $meta_key, string $value, int $image_id ): bool {
		$map = self::get_map();

		$entry              = self::normalize( $map[ $meta_key ][ $value ] ?? null );
		$entry['image_id']  = $image_id;

		return self::save_entry( $map, $meta_key, $value, $entry );
	}

	/**
	 * Remove the logo for an entity value, keeping its URL (if any) intact.
	 *
	 * @param string $meta_key Source meta key.
	 * @param string $value    Entity value name.
	 * @return bool True on success.
	 */
	public static function remove_image( string $meta_key, string $value ): bool {
		return self::set_image( $meta_key, $value, 0 );
	}

	/**
	 * Write an entry back into the map and persist it, pruning the entry
	 * entirely once it has neither a URL nor a logo (keeps the stored
	 * option tidy instead of accumulating empty rows over time).
	 *
	 * @param array<string, array<string, array{url: string, image_id: int}>> $map      Full map (by reference semantics via reassignment).
	 * @param string                                                          $meta_key Source meta key.
	 * @param string                                                          $value    Entity value name.
	 * @param array{url: string, image_id: int}                               $entry    Normalized entry to store.
	 * @return bool
	 */
	private static function save_entry( array $map, string $meta_key, string $value, array $entry ): bool {

		if ( '' === $entry['url'] && 0 === $entry['image_id'] ) {
			unset( $map[ $meta_key ][ $value ] );
		} else {
			if ( ! isset( $map[ $meta_key ] ) || ! is_array( $map[ $meta_key ] ) ) {
				$map[ $meta_key ] = array();
			}
			$map[ $meta_key ][ $value ] = $entry;
		}

		return update_option( self::OPTION_NAME, $map, false );
	}
}
