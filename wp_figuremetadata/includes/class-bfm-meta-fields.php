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

	/**
	 * All structured figure meta fields.
	 *
	 * Keys are the post meta keys (unchanged from the existing production
	 * snippet). Labels are unchanged as well.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all(): array {
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

	/**
	 * Meta fields whose values are synchronized to post tags.
	 *
	 * Multiple values within a single field are separated by "&".
	 *
	 * @return array<int, string>
	 */
	public static function tag_sources(): array {
		return array(
			'_bunny_figure_manufacturer',
			'_bunny_figure_illustrator',
			'_bunny_figure_series',
			'_bunny_figure_character',
		);
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
		return array(
			'manufacturers' => array(
				'label'    => __( 'Fabricantes', 'bunny-figure-metadata' ),
				'meta_key' => '_bunny_figure_manufacturer',
			),
			'illustrators' => array(
				'label'    => __( 'Ilustradores', 'bunny-figure-metadata' ),
				'meta_key' => '_bunny_figure_illustrator',
			),
			'series' => array(
				'label'    => __( 'Series', 'bunny-figure-metadata' ),
				'meta_key' => '_bunny_figure_series',
			),
			'characters' => array(
				'label'    => __( 'Personajes', 'bunny-figure-metadata' ),
				'meta_key' => '_bunny_figure_character',
			),
		);
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
