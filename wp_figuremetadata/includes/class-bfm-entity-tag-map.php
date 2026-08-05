<?php
/**
 * BFM_Entity_Tag_Map
 *
 * Stores the association between a metadata entity value (e.g. the
 * manufacturer name "Good Smile Company") and a WordPress tag term_id.
 *
 * Persistence:
 * A single row in wp_options (autoload disabled) holding a nested array
 * keyed by meta_key => value name => term_id. No custom database table
 * is needed for this. The association is keyed by term_id, not by tag
 * name, so renaming the tag later does not break the association.
 *
 * This mapping is independent of post metadata: it associates an
 * *entity value* (a string found in metadata) with a tag, not a post
 * with a tag. It does not read or write post meta.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Entity_Tag_Map {

	/**
	 * Option name used to store the full map.
	 */
	const OPTION_NAME = 'bfm_entity_tag_map';

	/**
	 * Read the full map from the options table.
	 *
	 * @return array<string, array<string, int>>
	 */
	private static function get_map(): array {
		$map = get_option( self::OPTION_NAME, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Get the associated term_id for a single entity value, if any.
	 *
	 * @param string $meta_key Source meta key (e.g. _bunny_figure_manufacturer).
	 * @param string $value    Entity value name (e.g. "Good Smile Company").
	 * @return int Term ID, or 0 if no association exists.
	 */
	public static function get( string $meta_key, string $value ): int {
		$map = self::get_map();

		return isset( $map[ $meta_key ][ $value ] )
			? absint( $map[ $meta_key ][ $value ] )
			: 0;
	}

	/**
	 * Get all value => term_id associations for a given meta key.
	 *
	 * @param string $meta_key Source meta key.
	 * @return array<string, int> Map of value name => term_id.
	 */
	public static function get_for_meta_key( string $meta_key ): array {
		$map = self::get_map();

		return isset( $map[ $meta_key ] ) && is_array( $map[ $meta_key ] )
			? $map[ $meta_key ]
			: array();
	}

	/**
	 * Associate an entity value with a tag term_id (a value can only
	 * ever have one associated term_id — setting it replaces any
	 * previous association).
	 *
	 * @param string $meta_key Source meta key.
	 * @param string $value    Entity value name.
	 * @param int    $term_id  Tag term_id.
	 * @return bool True on success.
	 */
	public static function set( string $meta_key, string $value, int $term_id ): bool {
		$map = self::get_map();

		if ( ! isset( $map[ $meta_key ] ) || ! is_array( $map[ $meta_key ] ) ) {
			$map[ $meta_key ] = array();
		}

		$map[ $meta_key ][ $value ] = $term_id;

		return update_option( self::OPTION_NAME, $map, false );
	}

	/**
	 * Remove the association for an entity value, if any.
	 *
	 * @param string $meta_key Source meta key.
	 * @param string $value    Entity value name.
	 * @return bool True on success (or if there was nothing to remove).
	 */
	public static function delete( string $meta_key, string $value ): bool {
		$map = self::get_map();

		if ( ! isset( $map[ $meta_key ][ $value ] ) ) {
			return true;
		}

		unset( $map[ $meta_key ][ $value ] );

		return update_option( self::OPTION_NAME, $map, false );
	}
}
