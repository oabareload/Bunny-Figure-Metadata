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
				'label'   => $field['label'],
				'default' => '',
				'entity'  => in_array( $meta_key, self::legacy_entity_keys(), true ),
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
	 * @return array<string, array{label:string,default:string,entity:bool}>
	 */
	private static function normalize( array $fields ): array {
		$normalized = array();
		foreach ( $fields as $meta_key => $field ) {
			$meta_key = sanitize_key( (string) $meta_key );
			if ( '' === $meta_key || ! is_array( $field ) ) {
				continue;
			}
			$normalized[ $meta_key ] = array(
				'label'   => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : $meta_key,
				'default' => isset( $field['default'] ) ? sanitize_text_field( $field['default'] ) : '',
				'entity'  => ! empty( $field['entity'] ),
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
			return ! empty( $field['entity'] );
		} ) );
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
				'label'    => $field['label'],
				'meta_key' => $meta_key,
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
