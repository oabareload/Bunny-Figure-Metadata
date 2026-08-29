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
	 * Resolve the single Media Library attachment ID that should actually
	 * be displayed for every value under a meta key, given its configured
	 * image source. This is the one place that decides between a manual
	 * pick and the automatic related-post pick, so every caller — the
	 * admin entity list, the "Entidades destacadas" block, or any future
	 * one — resolves an entity's image the same way:
	 *
	 *   1. A manually stored image_id (this class) always wins when present.
	 *   2. Otherwise, for image_source = 'related', the automatic pick from
	 *      BFM_Entity_Repository::get_related_image_attachment_ids().
	 *   3. Otherwise 0 (no image).
	 *
	 * For image_source = 'custom' this returns exactly the stored image_id
	 * map (step 2 never runs), so custom-logo fields are unaffected.
	 *
	 * @param string $meta_key     Source meta key.
	 * @param string $image_source 'custom' or 'related' (BFM_Meta_Fields::image_source_for_meta_key()).
	 * @return array<string, int> Map of value name => effective attachment ID (0 = no image).
	 */
	public static function get_effective_image_ids( string $meta_key, string $image_source ): array {

		$info_map = self::get_for_meta_key( $meta_key );

		$auto_ids = ( 'related' === $image_source )
			? BFM_Entity_Repository::get_related_image_attachment_ids( $meta_key )
			: array();

		$values = array_unique( array_merge( array_keys( $info_map ), array_keys( $auto_ids ) ) );

		$effective = array();

		foreach ( $values as $value ) {

			$manual_id = absint( $info_map[ $value ]['image_id'] ?? 0 );
			$auto_id   = absint( $auto_ids[ $value ] ?? 0 );

			$effective[ $value ] = $manual_id > 0 ? $manual_id : $auto_id;
		}

		return $effective;
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
	 * When the requested size doesn't exist for a given attachment, falls
	 * back to the smallest registered size actually available for it
	 * (never "full"/"large"), via resolve_display_size().
	 *
	 * @param array<int, int> $image_ids Media Library attachment IDs.
	 * @param string           $size      Preferred registered image size.
	 * @return array<int, string> Map of image_id => resolved image URL (only for IDs that resolved).
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

			$resolved_size = self::resolve_display_size( $image_id, $size );

			if ( '' === $resolved_size ) {
				continue;
			}

			$src = wp_get_attachment_image_src( $image_id, $resolved_size );

			if ( $src ) {
				$urls[ $image_id ] = $src[0];
			}
		}

		return $urls;
	}

	/**
	 * Build a ready-to-output <img> tag for an entity's display image
	 * using WordPress's own wp_get_attachment_image(), requesting the
	 * smallest available size (never "full"/"large") via
	 * resolve_display_size(). Returns an empty string when there is no
	 * image to show, so callers can render a placeholder instead.
	 *
	 * @param int                  $attachment_id Media Library attachment ID (0 = no image).
	 * @param array<string, mixed> $attr          Extra attributes passed through to wp_get_attachment_image().
	 * @return string
	 */
	public static function get_display_image_html( int $attachment_id, array $attr = array() ): string {

		if ( $attachment_id <= 0 ) {
			return '';
		}

		$size = self::resolve_display_size( $attachment_id, 'thumbnail' );

		if ( '' === $size ) {
			return '';
		}

		return (string) wp_get_attachment_image( $attachment_id, $size, false, $attr );
	}

	/**
	 * Resolve which registered image size to request for an attachment:
	 * the preferred size when it exists for that attachment, otherwise
	 * the smallest size actually registered in its metadata, otherwise
	 * "full" as a last resort (e.g. an SVG with no intermediate sizes at
	 * all). Never picks "full"/"large" over a smaller available size, so
	 * callers never load an unnecessarily large image.
	 *
	 * @param int    $attachment_id  Media Library attachment ID.
	 * @param string $preferred_size Preferred registered image size.
	 * @return string Resolved size slug, or '' if the attachment has no image src at all.
	 */
	private static function resolve_display_size( int $attachment_id, string $preferred_size ): string {

		if ( wp_get_attachment_image_src( $attachment_id, $preferred_size ) ) {
			return $preferred_size;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {

			$smallest_size_name = null;
			$smallest_width     = null;

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {

				$width = isset( $size_data['width'] ) ? (int) $size_data['width'] : 0;

				if ( $width > 0 && ( null === $smallest_width || $width < $smallest_width ) ) {
					$smallest_width     = $width;
					$smallest_size_name = $size_name;
				}
			}

			if ( null !== $smallest_size_name ) {
				return $smallest_size_name;
			}
		}

		// Last resort: the original file itself (e.g. no intermediate sizes
		// were ever generated for it).
		return wp_get_attachment_image_src( $attachment_id, 'full' ) ? 'full' : '';
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
