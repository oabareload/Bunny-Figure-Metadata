<?php
/**
 * BFM_Features_Block
 *
 * Registers the "Bunny Figure: Características" Gutenberg block: a
 * dynamic/server-side block that reads the configured structured figure meta
 * fields of the current post and renders them, replacing the previous
 * dependency on an external plugin for this display.
 *
 * No HTML is ever stored in post_content for this block — only the
 * block's heading attributes are saved; field display settings are global
 * plugin configuration and the markup is
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

	const OPTION_NAME = 'bfm_features_settings';

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

	/** @return array<int, string> */
	public static function fixed_field_keys(): array {
		return array_keys( BFM_Meta_Fields::all() );
	}

	/**
	 * Default global settings for all features fields.
	 *
	 * @return array<string, array{show:bool,prefix:string,default:string,prefixTag:string,prefixClass:string,valueTag:string,valueClass:string,link_target:string,order:int}>
	 */
	public static function default_global_settings(): array {
		$settings = array();
		foreach ( self::fixed_field_keys() as $index => $meta_key ) {
			$settings[ $meta_key ] = array(
				'show'        => true,
				'prefix'      => ( BFM_Meta_Fields::all()[ $meta_key ]['label'] ?? $meta_key ) . ':',
				'default'     => '',
				'prefixTag'   => 'span',
				'prefixClass' => '',
				'valueTag'    => 'span',
				'valueClass'  => '',
				'link_target' => 'tag',
				'order'       => $index + 1,
			);
		}
		return $settings;
	}

	/**
	 * Get the current global configuration for the block.
	 *
	 * @return array<string, array{show:bool,prefix:string,default:string,prefixTag:string,prefixClass:string,valueTag:string,valueClass:string,link_target:string,order:int}>
	 */
	public static function global_field_settings(): array {
		$config = get_option( self::OPTION_NAME, self::default_global_settings() );
		$defaults = self::default_global_settings();
		$clean = array();

		foreach ( self::fixed_field_keys() as $meta_key ) {
			$raw = is_array( $config[ $meta_key ] ?? null ) ? $config[ $meta_key ] : array();
			$base = $defaults[ $meta_key ];
			$clean[ $meta_key ] = array(
				'show'        => ! empty( $raw['show'] ) || ! array_key_exists( 'show', $raw ),
				'prefix'      => isset( $raw['prefix'] ) && '' !== trim( (string) $raw['prefix'] ) ? (string) $raw['prefix'] : $base['prefix'],
				'default'     => isset( $raw['default'] ) ? sanitize_text_field( (string) $raw['default'] ) : $base['default'],
				'prefixTag'   => self::sanitize_html_tag( $raw['prefixTag'] ?? $base['prefixTag'] ),
				'prefixClass' => isset( $raw['prefixClass'] ) ? sanitize_html_class( (string) $raw['prefixClass'] ) : $base['prefixClass'],
				'valueTag'    => self::sanitize_html_tag( $raw['valueTag'] ?? $base['valueTag'] ),
				'valueClass'  => isset( $raw['valueClass'] ) ? sanitize_html_class( (string) $raw['valueClass'] ) : $base['valueClass'],
				'link_target' => in_array( $raw['link_target'] ?? $base['link_target'], array( 'tag', 'external' ), true ) ? (string) ( $raw['link_target'] ?? $base['link_target'] ) : 'tag',
				'order'       => max( 1, absint( $raw['order'] ?? $base['order'] ) ),
			);
		}

		return $clean;
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

		$global_settings = self::global_field_settings();
		$entity_meta_keys = BFM_Meta_Fields::entity_sources();
		$separator        = BFM_Meta_Fields::value_separator();
		$excluded_value   = BFM_Meta_Fields::excluded_value();

		$info_by_meta_key = array();
		$tag_links_by_meta_key = array();

		foreach ( $entity_meta_keys as $meta_key ) {
			if ( BFM_Meta_Fields::has_capability( $meta_key, 'url' ) ) {
				$info_by_meta_key[ $meta_key ] = BFM_Entity_Info_Map::get_for_meta_key( $meta_key );
			}

			if ( BFM_Meta_Fields::has_capability( $meta_key, 'tag' ) ) {
				$tag_map = BFM_Entity_Tag_Map::get_for_meta_key( $meta_key );
				$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $tag_map ) ) ) );
				$tag_links_by_meta_key[ $meta_key ] = array();

				if ( ! empty( $term_ids ) ) {
					$terms = get_terms(
						array(
							'taxonomy'   => 'post_tag',
							'include'    => $term_ids,
							'hide_empty' => false,
						)
					);

					if ( ! is_wp_error( $terms ) ) {
						$terms_by_id = array();
						foreach ( $terms as $term ) {
							$terms_by_id[ $term->term_id ] = $term;
						}

						foreach ( $tag_map as $value => $term_id ) {
							$term_id = absint( $term_id );
							if ( $term_id > 0 && isset( $terms_by_id[ $term_id ] ) ) {
								$link = get_term_link( $terms_by_id[ $term_id ] );
								if ( ! is_wp_error( $link ) ) {
									$tag_links_by_meta_key[ $meta_key ][ $value ] = $link;
								}
							}
						}
					}
				}
			}
		}

		$rows = array();
		$visible_settings = array();

		$all_fields = BFM_Meta_Fields::all();
		foreach ( self::fixed_field_keys() as $meta_key ) {
			$field = $all_fields[ $meta_key ] ?? array( 'label' => $meta_key );
			$settings = $this->effective_field_settings( $meta_key, $field, $global_settings );
			$visible_settings[ $meta_key ] = $settings;
		}
		uasort( $visible_settings, static function ( $a, $b ) {
			return ( $a['order'] ?? 1 ) <=> ( $b['order'] ?? 1 );
		} );

		foreach ( $visible_settings as $meta_key => $settings ) {
			$field = $all_fields[ $meta_key ] ?? array( 'label' => $meta_key );

			if ( ! $settings['show'] ) {
				continue;
			}

			$raw_value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );

			if ( '' === $raw_value || 'no dice' === strtolower( $raw_value ) ) {
				$default = trim( (string) ( $settings['default'] ?? '' ) );
				if ( '' === $default || 'no dice' === strtolower( $default ) ) {
					continue;
				}
				$value_html = esc_html( $default );
			} elseif ( in_array( $meta_key, $entity_meta_keys, true ) ) {
				$value_html = $this->render_entity_value(
					$raw_value,
					$separator,
					$excluded_value,
					$info_by_meta_key[ $meta_key ] ?? array(),
					$tag_links_by_meta_key[ $meta_key ] ?? array(),
					$settings['link_target'] ?? 'tag',
					$post_id
				);
				if ( '' === $value_html ) {
					$default = trim( (string) ( $settings['default'] ?? '' ) );
					if ( '' === $default || 'no dice' === strtolower( $default ) ) {
						continue;
					}
					$value_html = esc_html( $default );
				}
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
	 * Resolve the effective settings for a field from the global configuration.
	 *
	 * @param string               $meta_key  Meta key.
	 * @param array<string,string> $field     Field definition (label).
	 * @param array<string,mixed>  $settings  Global feature settings.
	 * @return array{show:bool,prefix:string,default:string,prefixTag:string,prefixClass:string,valueTag:string,valueClass:string,link_target:string,order:int}
	 */
	private function effective_field_settings( string $meta_key, array $field, array $settings ): array {
		$config = is_array( $settings[ $meta_key ] ?? null ) ? $settings[ $meta_key ] : self::default_global_settings()[ $meta_key ];

		return array(
			'show'        => ! empty( $config['show'] ),
			'prefix'      => isset( $config['prefix'] ) && '' !== trim( (string) $config['prefix'] ) ? (string) $config['prefix'] : $field['label'] . ':',
			'default'     => isset( $config['default'] ) ? sanitize_text_field( (string) $config['default'] ) : '',
			'prefixTag'   => self::sanitize_html_tag( $config['prefixTag'] ?? 'span' ),
			'prefixClass' => isset( $config['prefixClass'] ) ? sanitize_html_class( (string) $config['prefixClass'] ) : '',
			'valueTag'    => self::sanitize_html_tag( $config['valueTag'] ?? 'span' ),
			'valueClass'  => isset( $config['valueClass'] ) ? sanitize_html_class( (string) $config['valueClass'] ) : '',
			'link_target' => in_array( $config['link_target'] ?? 'tag', array( 'tag', 'external' ), true ) ? (string) ( $config['link_target'] ?? 'tag' ) : 'tag',
			'order'       => max( 1, absint( $config['order'] ?? 1 ) ),
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
	 * @param int                  $post_id        Post being rendered; passed to WPAM when signing external links.
	 * @return string
	 */
	private function render_entity_value( string $raw_value, string $separator, string $excluded_value, array $info_map, array $tag_links, string $link_target = 'tag', int $post_id = 0 ): string {

		$parts = array_filter(
			array_map( 'trim', explode( $separator, $raw_value ) ),
			static function ( $part ) {
				return '' !== $part;
			}
		);

		$rendered = array();

		foreach ( $parts as $part ) {

			if ( $excluded_value === strtolower( $part ) || 'no dice' === strtolower( $part ) ) {
				continue;
			}

			$external_url = isset( $info_map[ $part ]['url'] ) ? (string) $info_map[ $part ]['url'] : '';
			$tag_url      = isset( $tag_links[ $part ] ) ? (string) $tag_links[ $part ] : '';
			$link_url     = '';

			if ( 'external' === $link_target && '' !== $external_url ) {
				$link_url = $external_url;
			} elseif ( 'tag' === $link_target && '' !== $tag_url ) {
				$link_url = $tag_url;
			} elseif ( 'external' === $link_target && '' === $external_url && '' !== $tag_url ) {
				$link_url = $tag_url;
			}

			if ( '' !== $link_url ) {
				$is_external = 'external' === $link_target && '' !== $external_url;

				// Optional WPAM integration: route genuinely external URLs through /goext/.
				// The original URL is kept when WPAM is inactive, the URL is internal, or WPAM returns ''.
				if ( $is_external && function_exists( 'wpam_get_external_link_url' ) && function_exists( 'wp_is_internal_link' ) && ! wp_is_internal_link( $link_url ) ) {
					$signed_url = wpam_get_external_link_url( $link_url, $post_id );

					if ( '' !== $signed_url ) {
						$link_url = $signed_url;
					}
				}

				$link_markup = $is_external
					? '<a class="bfm-feature-link" href="%1$s" target="_blank" rel="noopener noreferrer"><span>%2$s</span><span class="bfm-feature-link-external-icon" aria-hidden="true">↗</span></a>'
					: '<a class="bfm-feature-link" href="%1$s">%2$s</a>';

				$rendered[] = sprintf(
					$link_markup,
					esc_url( $link_url ),
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
	public static function sanitize_html_tag( $tag ): string {

		$tag = strtolower( (string) $tag );

		return in_array( $tag, self::ALLOWED_TAGS, true ) ? $tag : 'span';
	}
}
