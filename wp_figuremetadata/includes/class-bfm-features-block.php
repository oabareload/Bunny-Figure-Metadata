<?php
/**
 * BFM_Features_Block
 *
 * Registers the "Bunny Figure: Características" Gutenberg block: a
 * dynamic/server-side block that reads the 12 structured figure meta
 * fields of the current post and renders them, replacing the previous
 * dependency on an external plugin for this display.
 *
 * No HTML is ever stored in post_content for this block — only the
 * block's attributes (display configuration) are saved; the markup is
 * generated fresh on every render via render_callback(), reusing the
 * plugin's existing classes:
 *   - BFM_Meta_Fields   for the field list, labels, "&" separator, and
 *                       the excluded "No dice" value.
 *   - BFM_Entity_Info_Map for the per-entity-value URL (Manufacturer /
 *                       Illustrator / Series / Character only). Logos
 *                       are intentionally not rendered by this block yet.
 *
 * The block never writes to metadata, entities, tags, or URLs — it is
 * read-only display.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Features_Block {

	/**
	 * Fully qualified block name.
	 */
	const BLOCK_NAME = 'bunny-figure-metadata/features';

	/**
	 * HTML tags a user is allowed to choose for the heading, prefix, or
	 * value elements. Kept to a small allow-list so a block attribute
	 * (arbitrary user input) can never be used to inject an unexpected
	 * tag name into the page.
	 */
	const ALLOWED_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span', 'strong', 'em' );

	/**
	 * Register all hooks owned by this class.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block's editor script/style and the block type itself.
	 *
	 * The editor script is registered manually (rather than relying on
	 * block.json's file-based "editorScript", which requires a generated
	 * *.asset.php dependency manifest from a build step) so this block
	 * needs no build tooling, consistent with the rest of the plugin's
	 * plain-JS admin assets.
	 *
	 * @return void
	 */
	public function register_block(): void {

		$block_dir = BFM_PATH . 'blocks/features';

		wp_register_script(
			'bfm-features-block-editor',
			BFM_URL . 'blocks/features/index.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
			),
			BFM_VERSION,
			true
		);

		wp_localize_script(
			'bfm-features-block-editor',
			'BFM_FEATURES_BLOCK',
			array(
				'fields' => $this->field_definitions_for_js(),
			)
		);

		wp_register_style(
			'bfm-features-block-style',
			BFM_URL . 'blocks/features/style.css',
			array(),
			BFM_VERSION
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => 'bfm-features-block-editor',
				'style'           => 'bfm-features-block-style',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Build the { metaKey, label } list the editor script uses to render
	 * one Inspector panel per field, sourced from BFM_Meta_Fields (the
	 * single source of truth already used across the plugin).
	 *
	 * @return array<int, array{metaKey: string, label: string}>
	 */
	private function field_definitions_for_js(): array {

		$fields = array();

		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {
			$fields[] = array(
				'metaKey' => $meta_key,
				'label'   => $field['label'],
			);
		}

		return $fields;
	}

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner block content (unused, this block has none).
	 * @param WP_Block             $block      Block instance (used for postId context, if available).
	 * @return string
	 */
	public function render( $attributes, $content, $block ): string {

		$post_id = ! empty( $block->context['postId'] )
			? absint( $block->context['postId'] )
			: get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$show_heading  = ! isset( $attributes['showHeading'] ) || (bool) $attributes['showHeading'];
		$heading_tag   = $this->sanitize_html_tag( $attributes['headingTag'] ?? 'h3' );
		$heading_class = isset( $attributes['headingClassName'] ) ? sanitize_html_class( (string) $attributes['headingClassName'] ) : '';

		// A visual-only placeholder for empty fields. Never persisted,
		// never treated as metadata, never turned into an entity or TAG.
		$default_value_text = isset( $attributes['defaultValueText'] ) ? (string) $attributes['defaultValueText'] : __( 'No dice', 'bunny-figure-metadata' );

		$field_overrides = is_array( $attributes['features'] ?? null ) ? $attributes['features'] : array();

		$entity_meta_keys = BFM_Meta_Fields::tag_sources();
		$separator        = BFM_Meta_Fields::value_separator();
		$excluded_value   = BFM_Meta_Fields::excluded_value();

		// Batch-load URL info once per entity meta key present on this
		// block (at most 4 calls total, each a single cached get_option()
		// read via BFM_Entity_Info_Map) — never one lookup per value.
		$info_by_meta_key = array();

		foreach ( $entity_meta_keys as $meta_key ) {
			$info_by_meta_key[ $meta_key ] = BFM_Entity_Info_Map::get_for_meta_key( $meta_key );
		}

		$rows = array();

		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {

			$settings = $this->effective_field_settings( $meta_key, $field, $field_overrides );

			if ( ! $settings['show'] ) {
				continue;
			}

			$raw_value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );

			if ( '' === $raw_value ) {
				$value_html = '' === $default_value_text ? '' : esc_html( $default_value_text );
			} elseif ( in_array( $meta_key, $entity_meta_keys, true ) ) {
				$value_html = $this->render_entity_value(
					$raw_value,
					$separator,
					$excluded_value,
					$info_by_meta_key[ $meta_key ]
				);
			} else {
				$value_html = esc_html( $raw_value );
			}

			$rows[] = $this->render_row( $settings, $value_html );
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$heading_html = '';

		if ( $show_heading ) {
			$heading_html = sprintf(
				'<%1$s class="bfm-features-heading%2$s">%3$s</%1$s>',
				$heading_tag,
				$heading_class ? ' ' . esc_attr( $heading_class ) : '',
				esc_html__( '📦 Características', 'bunny-figure-metadata' )
			);
		}

		$wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'class' => 'bfm-features-block' ) )
			: 'class="bfm-features-block"';

		return sprintf(
			'<div %1$s>%2$s<ul class="bfm-features-list">%3$s</ul></div>',
			$wrapper_attributes,
			$heading_html,
			implode( '', $rows )
		);
	}

	/**
	 * Resolve the effective (already-defaulted) display settings for one
	 * field, merging the block's per-field overrides (if any) with
	 * sensible defaults derived from BFM_Meta_Fields.
	 *
	 * @param string               $meta_key  Meta key.
	 * @param array<string,string> $field     Field definition (label).
	 * @param array<string,mixed>  $overrides Block's `features` attribute.
	 * @return array{show:bool,prefix:string,prefixTag:string,prefixClass:string,valueTag:string,valueClass:string}
	 */
	private function effective_field_settings( string $meta_key, array $field, array $overrides ): array {

		$field_overrides = is_array( $overrides[ $meta_key ] ?? null ) ? $overrides[ $meta_key ] : array();

		return array(
			'show'        => ! isset( $field_overrides['show'] ) || (bool) $field_overrides['show'],
			'prefix'      => isset( $field_overrides['prefix'] ) && '' !== $field_overrides['prefix']
				? (string) $field_overrides['prefix']
				: $field['label'] . ':',
			'prefixTag'   => $this->sanitize_html_tag( $field_overrides['prefixTag'] ?? 'span' ),
			'prefixClass' => isset( $field_overrides['prefixClass'] ) ? sanitize_html_class( (string) $field_overrides['prefixClass'] ) : '',
			'valueTag'    => $this->sanitize_html_tag( $field_overrides['valueTag'] ?? 'span' ),
			'valueClass'  => isset( $field_overrides['valueClass'] ) ? sanitize_html_class( (string) $field_overrides['valueClass'] ) : '',
		);
	}

	/**
	 * Render one <li> row: prefix element + value element.
	 *
	 * @param array<string,mixed> $settings   Effective field settings.
	 * @param string              $value_html Already-escaped/linked value markup.
	 * @return string
	 */
	private function render_row( array $settings, string $value_html ): string {

		$prefix_html = sprintf(
			'<%1$s class="bfm-feature-prefix%2$s">%3$s</%1$s>',
			$settings['prefixTag'],
			$settings['prefixClass'] ? ' ' . esc_attr( $settings['prefixClass'] ) : '',
			esc_html( $settings['prefix'] )
		);

		$value_html_wrapped = sprintf(
			'<%1$s class="bfm-feature-value%2$s">%3$s</%1$s>',
			$settings['valueTag'],
			$settings['valueClass'] ? ' ' . esc_attr( $settings['valueClass'] ) : '',
			$value_html
		);

		return sprintf( '<li class="bfm-feature">%s %s</li>', $prefix_html, $value_html_wrapped );
	}

	/**
	 * Render an entity field's value: split on "&" into independent
	 * values (same convention as BFM_Tag_Sync / BFM_Entity_Repository),
	 * link each one to its configured URL when it has one, and leave the
	 * excluded value ("No dice") as plain text without an entity lookup
	 * — it is valid metadata, but is never treated as an administrable
	 * entity anywhere else in the plugin either.
	 *
	 * @param string               $raw_value      Raw "A & B" stored value.
	 * @param string               $separator      Value separator ("&").
	 * @param string               $excluded_value Excluded value ("no dice", lowercase).
	 * @param array<string,array>  $info_map       value name => [ url, image_id ], from BFM_Entity_Info_Map.
	 * @return string
	 */
	private function render_entity_value( string $raw_value, string $separator, string $excluded_value, array $info_map ): string {

		$parts = array_filter(
			array_map( 'trim', explode( $separator, $raw_value ) ),
			static function ( $part ) {
				return '' !== $part;
			}
		);

		$rendered = array();

		foreach ( $parts as $part ) {

			if ( $excluded_value === strtolower( $part ) ) {
				$rendered[] = esc_html( $part );
				continue;
			}

			$url = isset( $info_map[ $part ]['url'] ) ? (string) $info_map[ $part ]['url'] : '';

			if ( '' !== $url ) {
				$rendered[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html( $part )
				);
			} else {
				$rendered[] = esc_html( $part );
			}
		}

		return implode( ' &amp; ', $rendered );
	}

	/**
	 * Restrict a user-configured HTML tag name to a small allow-list.
	 *
	 * @param mixed $tag Requested tag.
	 * @return string
	 */
	private function sanitize_html_tag( $tag ): string {

		$tag = strtolower( (string) $tag );

		return in_array( $tag, self::ALLOWED_TAGS, true ) ? $tag : 'span';
	}
}
