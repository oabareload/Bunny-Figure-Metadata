<?php
/**
 * BFM_Character_Identity
 *
 * Character-only disambiguation: two figures can share the exact same
 * `_bunny_figure_character` value ("Eve") while being different
 * characters — e.g. "Eve" from Stellar Blade vs. an original character
 * named "Eve" illustrated by rurudo. This class builds a single internal
 * identity key per character occurrence, composed from the character
 * name plus its post's Series (and, only when that Series is configured
 * as the "original character" trigger value, also its Illustrator).
 *
 * This is the one place that builds that key. BFM_Entity_Repository
 * uses it (via a shared per-row resolver) wherever it needs to group or
 * match character values, so the resolution logic itself is never
 * duplicated. BFM_Entity_Tag_Map and the TAG/URL association shown on
 * the admin screen are intentionally left keyed by the plain character
 * name — unaffected by this — since only image disambiguation was
 * requested; only the image-related lookups (BFM_Entity_Info_Map's
 * image_id, BFM_Entity_Repository's automatic image resolution) use the
 * composite identity key as their "value".
 *
 * Deliberately narrow and NOT a generic entity-identity framework: it
 * only ever combines these two specific complementary fields for this
 * one meta key, and every other entity (manufacturer, illustrator,
 * series) is completely untouched by its existence.
 *
 * Persistence: a single small option (autoload disabled), following the
 * same pattern as BFM_Entity_Tag_Map / BFM_Entity_Info_Map. No new
 * table, ID, or migration of existing data — the identity key is
 * computed on the fly from metadata that already exists.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Character_Identity {

	/**
	 * Option name used to store the identity configuration.
	 */
	const OPTION_NAME = 'bfm_character_identity_settings';

	/**
	 * The only meta key this class ever applies to.
	 */
	const CHARACTER_META_KEY = '_bunny_figure_character';

	/**
	 * Complementary meta keys that can contribute to the identity.
	 */
	const SERIES_META_KEY = '_bunny_figure_series';
	const ILLUSTRATOR_META_KEY = '_bunny_figure_illustrator';

	/**
	 * Separator used to join identity parts into a single internal key.
	 * Never shown to the user; chosen only to be unlikely to collide
	 * with real metadata text.
	 */
	const KEY_SEPARATOR = '|';

	/**
	 * Default configuration: both complementary fields contribute, and
	 * "PERSONAJE ORIGINAL" is the Series value that also pulls in the
	 * Illustrator.
	 *
	 * @return array{identity_fields: array<int,string>, original_value: string}
	 */
	public static function default_settings(): array {
		return array(
			'identity_fields' => array( 'series', 'illustrator' ),
			'original_value'  => 'PERSONAJE ORIGINAL',
		);
	}

	/**
	 * Seed the option with defaults on first use, without touching any
	 * existing post metadata.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		add_option( self::OPTION_NAME, self::default_settings(), '', false );
	}

	/**
	 * Read the current identity configuration.
	 *
	 * @return array{identity_fields: array<int,string>, original_value: string}
	 */
	public static function settings(): array {
		self::maybe_migrate();

		$stored  = get_option( self::OPTION_NAME, array() );
		$default = self::default_settings();

		$identity_fields = is_array( $stored['identity_fields'] ?? null )
			? array_values( array_intersect( array( 'series', 'illustrator' ), $stored['identity_fields'] ) )
			: $default['identity_fields'];

		$original_value = ( isset( $stored['original_value'] ) && '' !== trim( (string) $stored['original_value'] ) )
			? (string) $stored['original_value']
			: $default['original_value'];

		return array(
			'identity_fields' => $identity_fields,
			'original_value'  => $original_value,
		);
	}

	/**
	 * Sanitize a raw settings submission (used by BFM_Admin's settings
	 * sanitize callback).
	 *
	 * @param mixed $raw_settings Submitted option value.
	 * @return array{identity_fields: array<int,string>, original_value: string}
	 */
	public static function sanitize_settings( $raw_settings ): array {
		$raw      = is_array( $raw_settings ) ? $raw_settings : array();
		$default  = self::default_settings();
		$fields   = is_array( $raw['identity_fields'] ?? null ) ? $raw['identity_fields'] : array();
		$original = isset( $raw['original_value'] ) ? sanitize_text_field( (string) $raw['original_value'] ) : '';

		return array(
			'identity_fields' => array_values( array_intersect( array( 'series', 'illustrator' ), $fields ) ),
			'original_value'  => '' !== $original ? $original : $default['original_value'],
		);
	}

	/**
	 * Case-insensitive, whitespace-tolerant comparison: "PERSONAJE
	 * ORIGINAL", "Personaje Original", and "personaje  original" are all
	 * treated as the same value.
	 *
	 * @param string $a First value.
	 * @param string $b Second value.
	 * @return bool
	 */
	private static function values_match( string $a, string $b ): bool {
		$normalize = static function ( string $value ): string {
			return strtolower( trim( preg_replace( '/\s+/', ' ', $value ) ) );
		};

		return $normalize( $a ) === $normalize( $b );
	}

	/**
	 * Build the internal identity key for one character occurrence.
	 *
	 * This is the single reusable helper requested: every caller that
	 * needs to group, match, or store something by "which character,
	 * exactly" for the character field goes through this function
	 * instead of re-implementing the rule.
	 *
	 * @param string $character_name    Character name as stored/displayed (e.g. "Eve"). Never modified.
	 * @param string $series_value      Raw Series meta value of the same post ('' if the post has none).
	 * @param string $illustrator_value Raw Illustrator meta value of the same post ('' if the post has none).
	 * @return string Identity key, e.g. "Eve|Stellar Blade" or "Eve|PERSONAJE ORIGINAL|rurudo". Falls back to the bare character name when no identity fields are configured.
	 */
	public static function build_identity_key( string $character_name, string $series_value, string $illustrator_value ): string {

		$settings = self::settings();
		$parts    = array( $character_name );

		if ( in_array( 'series', $settings['identity_fields'], true ) ) {

			$parts[] = $series_value;

			if (
				in_array( 'illustrator', $settings['identity_fields'], true )
				&& self::values_match( $series_value, $settings['original_value'] )
			) {
				$parts[] = $illustrator_value;
			}
		}

		return implode( self::KEY_SEPARATOR, $parts );
	}

	/**
	 * Extract the display-facing character name from an identity key —
	 * what the user should always see, regardless of how the identity
	 * was disambiguated internally. Returns the input unchanged when it
	 * isn't a composite key (e.g. identity fields are unconfigured).
	 *
	 * @param string $identity_key Identity key produced by build_identity_key().
	 * @return string
	 */
	public static function display_name( string $identity_key ): string {
		$parts = explode( self::KEY_SEPARATOR, $identity_key, 2 );

		return $parts[0];
	}
}
