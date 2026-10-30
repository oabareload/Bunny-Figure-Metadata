<?php
/**
 * BFM_Metadata_Field_Block
 *
 * Registers the "Bunny Figure: Metadata Field" Gutenberg dynamic block.
 *
 * Generic presentation component for any structured figure metadata registered
 * in Bunny Figure Metadata. Renders server-side without storing HTML in post content.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Metadata_Field_Block {

	/**
	 * Fully qualified block name.
	 */
	const BLOCK_NAME = 'bunny-figure-metadata/metadata-field';

	/**
	 * Allowed HTML tags for label and value elements.
	 */
	const ALLOWED_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type, editor scripts, and frontend styles.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$block_dir = BFM_PATH . 'blocks/metadata-field';

		wp_register_script(
			'bfm-metadata-field-block-editor',
			BFM_URL . 'blocks/metadata-field/index.js',
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
			'bfm-metadata-field-block-editor',
			'BFM_METADATA_FIELD_BLOCK',
			array(
				'fields' => $this->fields_options_for_js(),
			)
		);

		wp_register_style(
			'bfm-metadata-field-block-style',
			BFM_URL . 'blocks/metadata-field/style.css',
			array(),
			BFM_VERSION
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => 'bfm-metadata-field-block-editor',
				'style'           => 'bfm-metadata-field-block-style',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Build options array for editor script dropdown.
	 *
	 * @return array<int, array{value: string, label: string, type: string}>
	 */
	public function fields_options_for_js(): array {
		$options = array();
		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {
			$options[] = array(
				'value' => $meta_key,
				'label' => $field['label'] ?? $meta_key,
				'type'  => $field['type'] ?? 'text',
			);
		}
		return $options;
	}

	/**
	 * Sanitize an HTML tag against ALLOWED_TAGS allowlist.
	 *
	 * @param mixed  $tag      Candidate tag name.
	 * @param string $fallback Fallback tag if invalid.
	 * @return string
	 */
	public static function sanitize_html_tag( $tag, string $fallback = 'div' ): string {
		$tag = is_string( $tag ) ? strtolower( trim( $tag ) ) : '';
		return in_array( $tag, self::ALLOWED_TAGS, true ) ? $tag : $fallback;
	}

	/**
	 * Dynamic render callback for the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content (empty for dynamic block).
	 * @param WP_Block|null        $block      Block instance.
	 * @return string Rendered HTML.
	 */
	public function render( array $attributes, string $content = '', $block = null ): string {
		$post_id = 0;
		if ( is_object( $block ) && ! empty( $block->context['postId'] ) ) {
			$post_id = absint( $block->context['postId'] );
		}
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return '';
		}

		$meta_key = isset( $attributes['metaKey'] ) ? sanitize_key( (string) $attributes['metaKey'] ) : '';
		if ( '' === $meta_key ) {
			return '';
		}

		$fields = BFM_Meta_Fields::all();
		if ( ! isset( $fields[ $meta_key ] ) ) {
			// Field no longer exists or identifier changed; fail gracefully without errors.
			return '';
		}
		$field_def = $fields[ $meta_key ];

		$raw_value = get_post_meta( $post_id, $meta_key, true );

		// Check if empty (null, empty string, or whitespace-only strings, or excluded 'no dice').
		$string_val = is_scalar( $raw_value ) ? trim( (string) $raw_value ) : '';
		$is_empty   = ( '' === $string_val || 'no dice' === strtolower( $string_val ) );

		$hide_if_empty = ! array_key_exists( 'hideIfEmpty', $attributes ) || ! empty( $attributes['hideIfEmpty'] );
		if ( $is_empty && $hide_if_empty ) {
			return '';
		}

		// Format value corresponding to metadata type.
		$type = $field_def['type'] ?? 'text';
		if ( 'textarea' === $type ) {
			// Preserve line breaks and safely escape HTML.
			$formatted_value = nl2br( esc_html( (string) $raw_value ) );
		} else {
			$formatted_value = esc_html( (string) $raw_value );
		}

		// Configure label.
		$show_label = ! array_key_exists( 'showLabel', $attributes ) || ! empty( $attributes['showLabel'] );
		$label_html = '';
		if ( $show_label ) {
			$custom_label = isset( $attributes['customLabel'] ) ? trim( (string) $attributes['customLabel'] ) : '';
			$label_text   = ( '' !== $custom_label ) ? $custom_label : ( $field_def['label'] ?? $meta_key );
			$label_tag    = self::sanitize_html_tag( $attributes['labelTag'] ?? 'h2', 'h2' );

			$label_html = sprintf(
				'<%1$s class="bfm-metadata-field-label">%2$s</%1$s>',
				$label_tag,
				esc_html( $label_text )
			);
		}

		// Configure value.
		$value_tag     = self::sanitize_html_tag( $attributes['valueTag'] ?? 'div', 'div' );
		$extra_classes = isset( $attributes['valueClassName'] ) ? sanitize_html_class( (string) $attributes['valueClassName'] ) : '';
		$value_class   = trim( 'bfm-metadata-field-value ' . $extra_classes );

		$value_html = sprintf(
			'<%1$s class="%2$s">%3$s</%1$s>',
			$value_tag,
			esc_attr( $value_class ),
			$formatted_value
		);

		$slug = sanitize_html_class( preg_replace( '/^_bunny_figure_/', '', $meta_key ) );
		$wrapper_class = 'bfm-metadata-field-block bfm-metadata-field-' . $slug;

		return sprintf(
			'<div class="%1$s">%2$s%3$s</div>',
			esc_attr( $wrapper_class ),
			$label_html,
			$value_html
		);
	}
}
