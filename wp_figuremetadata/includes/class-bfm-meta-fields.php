<?php
/**
 * BFM_Meta_Fields
 *
 * Single source of truth for the structured figure metadata field
 * definitions and which of those fields are synchronized to post tags.
 *
 * Both the post editor metabox and the admin entity screens read from
 * this class, so field definitions are never duplicated.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Meta_Fields {

	const OPTION_NAME = 'bfm_metadata_fields';

	/**
	 * Ensure the global configuration exists without changing stored post meta.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$fields = array();
		foreach ( self::legacy_fields() as $meta_key => $field ) {
			$fields[ $meta_key ] = array(
				'label'        => $field['label'],
				'entity'       => in_array( $meta_key, self::legacy_entity_keys(), true ),
				'type'         => 'text',
				'searchable'   => false,
				'tag'          => in_array( $meta_key, self::legacy_entity_keys(), true ),
				'url'          => in_array( $meta_key, self::legacy_entity_keys(), true ),
				'logo'         => in_array( $meta_key, self::legacy_entity_keys(), true ),
				'image_source' => 'custom',
			);
		}

		add_option( self::OPTION_NAME, $fields, '', false );
	}

	/**
	 * All structured figure meta fields.
	 *
	 * Keys are the post meta keys (unchanged from the existing production
	 * snippet). Labels are unchanged as well.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all(): array {
		self::maybe_migrate();

		$configured = get_option( self::OPTION_NAME, array() );
		return is_array( $configured ) ? self::normalize( $configured ) : array();
	}

	/**
	 * Original field definitions used to seed the global configuration.
	 *
	 * @return array<string, array{label:string}>
	 */
	private static function legacy_fields(): array {
		return array(
			'_bunny_figure_scale' => array(
				'label' => '📐 Escala',
			),
			'_bunny_figure_height' => array(
				'label' => '📏 Altura',
			),
			'_bunny_figure_price' => array(
				'label' => '💴 Precio',
			),
			'_bunny_figure_release_date' => array(
				'label' => '📅 Lanzamiento',
			),
			'_bunny_figure_material' => array(
				'label' => '🧱 Material',
			),
			'_bunny_figure_manufacturer' => array(
				'label' => '🏭 Fabricante',
			),
			'_bunny_figure_sculptor' => array(
				'label' => '🗿 Escultor',
			),
			'_bunny_figure_paintwork' => array(
				'label' => '🎨 Pintura',
			),
			'_bunny_figure_illustrator' => array(
				'label' => '✏️ Ilustrador',
			),
			'_bunny_figure_series' => array(
				'label' => '🎮 Serie',
			),
			'_bunny_figure_character' => array(
				'label' => '👤 Personaje',
			),
			'_bunny_figure_version' => array(
				'label' => '🏷️ Versión',
			),
		);
	}

	/** @return array<int, string> */
	private static function legacy_entity_keys(): array {
		return array(
			'_bunny_figure_manufacturer',
			'_bunny_figure_illustrator',
			'_bunny_figure_series',
			'_bunny_figure_character',
		);
	}

	/**
	 * @param array<mixed, mixed> $fields Raw option value.
	 * @return array<string, array{label:string,entity:bool,type:string}>
	 */
	private static function normalize( array $fields ): array {
		$normalized = array();
		foreach ( $fields as $meta_key => $field ) {
			$meta_key = sanitize_key( (string) $meta_key );
			if ( '' === $meta_key || ! is_array( $field ) ) {
				continue;
			}
			$types = array( 'text', 'number', 'currency', 'month', 'textarea' );
			$type  = isset( $field['type'] ) && in_array( $field['type'], $types, true ) ? $field['type'] : 'text';
			$is_entity = ! empty( $field['entity'] );
			$image_sources = array( 'custom', 'related' );
			$image_source  = isset( $field['image_source'] ) && in_array( $field['image_source'], $image_sources, true ) ? $field['image_source'] : 'custom';
			$normalized[ $meta_key ] = array(
				'label'        => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : $meta_key,
				'entity'       => $is_entity,
				'type'         => $type,
				'searchable'   => ! empty( $field['searchable'] ),
				'tag'          => $is_entity && ( ! array_key_exists( 'tag', $field ) || ! empty( $field['tag'] ) ),
				'url'          => $is_entity && ( ! array_key_exists( 'url', $field ) || ! empty( $field['url'] ) ),
				'logo'         => $is_entity && ( ! array_key_exists( 'logo', $field ) || ! empty( $field['logo'] ) ),
				// Only meaningful when 'logo' is enabled: whether the entity's
				// image is a custom uploaded logo (default, previous behavior)
				// or auto-resolved from one of its related posts.
				'image_source' => $is_entity ? $image_source : 'custom',
			);
		}
		return $normalized;
	}

	/**
	 * Meta fields whose values are synchronized to post tags.
	 *
	 * Multiple values within a single field are separated by "&".
	 *
	 * @return array<int, string>
	 */
	public static function tag_sources(): array {
		return array_keys( array_filter( self::all(), static function ( $field ) {
			return ! empty( $field['tag'] );
		} ) );
	}

	/**
	 * Meta fields marked as searchable in WordPress search queries.
	 *
	 * @return array<int, string>
	 */
	public static function searchable_keys(): array {
		return array_keys( array_filter( self::all(), static function ( $field ) {
			return ! empty( $field['searchable'] );
		} ) );
	}

	/** @return array<int, string> */
	public static function entity_sources(): array {
		return array_keys( array_filter( self::all(), static function ( $field ) {
			return ! empty( $field['entity'] );
		} ) );
	}

	public static function has_capability( string $meta_key, string $capability ): bool {
		$field = self::all()[ $meta_key ] ?? array();
		return ! empty( $field['entity'] ) && ! empty( $field[ $capability ] );
	}

	/**
	 * Resolve the configured image source ('custom' or 'related') for a
	 * single meta key by its meta_key (rather than by entity slug, as
	 * entities() is keyed). Non-entity fields always resolve to 'custom'.
	 * Shared by every block/screen that needs to know which image
	 * resolution strategy applies to a given entity meta key, so this
	 * lookup exists in exactly one place.
	 *
	 * @param string $meta_key Source meta key.
	 * @return string 'custom' or 'related'.
	 */
	public static function image_source_for_meta_key( string $meta_key ): string {
		$field = self::all()[ $meta_key ] ?? array();
		return ( ! empty( $field['entity'] ) && 'related' === ( $field['image_source'] ?? 'custom' ) ) ? 'related' : 'custom';
	}

	/**
	 * Administrable entities in this first phase.
	 *
	 * Maps an entity slug (used in admin URLs) to its source meta key
	 * and a human label. Order here defines menu order.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function entities(): array {
		$legacy_slugs = array(
			'_bunny_figure_manufacturer' => 'manufacturers',
			'_bunny_figure_illustrator'  => 'illustrators',
			'_bunny_figure_series'       => 'series',
			'_bunny_figure_character'    => 'characters',
		);
		$entities = array();
		foreach ( self::all() as $meta_key => $field ) {
			if ( empty( $field['entity'] ) ) {
				continue;
			}
			$slug = $legacy_slugs[ $meta_key ] ?? sanitize_title( preg_replace( '/^_bunny_figure_/', '', $meta_key ) );
			$entities[ $slug ] = array(
				'label'        => $field['label'],
				'meta_key'     => $meta_key,
				'tag'          => ! empty( $field['tag'] ),
				'url'          => ! empty( $field['url'] ),
				'logo'         => ! empty( $field['logo'] ),
				'image_source' => $field['image_source'] ?? 'custom',
			);
		}
		return $entities;
	}

	/**
	 * Value that is valid metadata but must never become a tag or be
	 * counted as a distinct entity value.
	 *
	 * @return string
	 */
	public static function excluded_value(): string {
		return 'no dice';
	}

	/**
	 * Separator used for multiple values within a single meta field.
	 *
	 * @return string
	 */
	public static function value_separator(): string {
		return '&';
	}
}
