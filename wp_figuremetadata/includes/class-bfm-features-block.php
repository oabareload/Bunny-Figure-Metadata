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
 * block's own attributes (heading and presentation) are saved; field
 * display settings are global plugin configuration and the markup is
 * generated fresh on every render via render_callback(), reusing the
 * plugin's existing classes:
 *   - BFM_Meta_Fields   for the field list, labels, "&" separator, and
 *                       the excluded "No dice" value.
 *   - BFM_Entity_Info_Map for the per-entity-value URL (Manufacturer /
 *                       Illustrator / Series / Character only) and, in
 *                       the "cards" presentation only, the entity image.
 *
 * Two presentations share exactly the same data resolution
 * (resolve_field_parts()): "classic" (the original list, unchanged) and
 * "cards" (a CSS grid of cards). They only differ in how the already
 * resolved values are turned into markup.
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
	 * Available presentations. "classic" is the default, so blocks saved
	 * before the presentation option existed keep rendering as before.
	 */
	const LAYOUTS        = array( 'classic', 'cards' );
	const DEFAULT_LAYOUT = 'classic';

	/**
	 * Target card width in pixels ("cards" presentation): default and
	 * allowed range. The value is only ever used as a sanitized integer.
	 */
	const DEFAULT_CARD_MIN_WIDTH = 140;
	const MIN_CARD_MIN_WIDTH     = 100;
	const MAX_CARD_MIN_WIDTH     = 250;

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
		$layout        = self::sanitize_layout( $attributes['layout'] ?? self::DEFAULT_LAYOUT );

		$global_settings = self::global_field_settings();
		$entity_meta_keys = BFM_Meta_Fields::entity_sources();

		list( $info_by_meta_key, $tag_links_by_meta_key ) = $this->build_link_context( $entity_meta_keys );

		$context = array(
			'post_id'          => $post_id,
			'entity_meta_keys' => $entity_meta_keys,
			'separator'        => BFM_Meta_Fields::value_separator(),
			'excluded_value'   => BFM_Meta_Fields::excluded_value(),
			'info'             => $info_by_meta_key,
			'tag_links'        => $tag_links_by_meta_key,
		);

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

		// Resolve every visible field once; both presentations render from this.
		$items = array();

		foreach ( $visible_settings as $meta_key => $settings ) {

			if ( ! $settings['show'] ) {
				continue;
			}

			$parts = $this->resolve_field_parts( $meta_key, $settings, $context );

			if ( empty( $parts ) ) {
				continue;
			}

			$items[] = array(
				'meta_key' => $meta_key,
				'settings' => $settings,
				'parts'    => $parts,
			);
		}

		if ( empty( $items ) ) {
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

		if ( 'cards' === $layout ) {

			$card_min_width = self::sanitize_card_min_width( $attributes['cardMinWidth'] ?? self::DEFAULT_CARD_MIN_WIDTH );

			$this->attach_card_images( $items, $post_id );

			$cards = array();

			foreach ( $items as $item ) {
				$cards[] = $this->render_cards_for_item( $item );
			}

			return sprintf(
				'<div %1$s>%2$s<ul class="bfm-features-cards">%3$s</ul></div>',
				$this->wrapper_attributes( 'bfm-features-block bfm-features-block--cards', sprintf( '--bfm-card-min:%dpx;', $card_min_width ) ),
				$heading_html,
				implode( '', $cards )
			);
		}

		$rows = array();

		foreach ( $items as $item ) {
			$rows[] = $this->render_row( $item['settings'], $this->render_parts_inline( $item['parts'] ) );
		}

		return sprintf(
			'<div %1$s>%2$s<ul class="bfm-features-list">%3$s</ul></div>',
			$this->wrapper_attributes( 'bfm-features-block' ),
			$heading_html,
			implode( '', $rows )
		);
	}

	/**
	 * Build the wrapper attribute string for the block root element.
	 *
	 * @param string $class Space-separated classes for the root element.
	 * @param string $style Optional inline style declarations (already sanitized by the caller).
	 * @return string
	 */
	private function wrapper_attributes( string $class, string $style = '' ): string {

		$extra = array( 'class' => $class );

		if ( '' !== $style ) {
			$extra['style'] = $style;
		}

		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( $extra );
		}

		return sprintf(
			'class="%1$s"%2$s',
			esc_attr( $class ),
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''
		);
	}

	/**
	 * Load, once per render, the per-entity URL maps and the TAG archive
	 * links needed to link entity values. Only for the entity fields that
	 * have the "url" / "tag" capability, exactly as before.
	 *
	 * @param array<int, string> $entity_meta_keys Entity meta keys.
	 * @return array{0: array<string, array>, 1: array<string, array<string, string>>} [ info by meta key, tag links by meta key ].
	 */
	private function build_link_context( array $entity_meta_keys ): array {

		$info_by_meta_key      = array();
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

		return array( $info_by_meta_key, $tag_links_by_meta_key );
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
	 * Resolve one field of the post into its displayable value parts.
	 * Single source of truth for "does this field show, and what does it
	 * show", shared by both presentations:
	 *   - empty / "No dice" stored value → the field's configured default,
	 *     or no parts at all (the field is skipped) when there is none;
	 *   - entity field → one part per "&"-separated value (see
	 *     resolve_entity_parts()); when none survive, same default rule;
	 *   - any other field → the stored text as a single part.
	 *
	 * @param string              $meta_key Field meta key.
	 * @param array<string,mixed> $settings Effective field settings.
	 * @param array<string,mixed> $context  Render context built by render().
	 * @return array<int, array{text:string,url:string,external:bool,entity:bool,image_url:string}> Empty when the field must not be shown.
	 */
	private function resolve_field_parts( string $meta_key, array $settings, array $context ): array {

		$raw_value = trim( (string) get_post_meta( $context['post_id'], $meta_key, true ) );

		if ( '' !== $raw_value && 'no dice' !== strtolower( $raw_value ) ) {

			if ( ! in_array( $meta_key, $context['entity_meta_keys'], true ) ) {
				return array( $this->make_part( $raw_value ) );
			}

			$parts = $this->resolve_entity_parts(
				$raw_value,
				$context['separator'],
				$context['excluded_value'],
				$context['info'][ $meta_key ] ?? array(),
				$context['tag_links'][ $meta_key ] ?? array(),
				$settings['link_target'] ?? 'tag',
				$context['post_id']
			);

			if ( ! empty( $parts ) ) {
				return $parts;
			}
		}

		$default = trim( (string) ( $settings['default'] ?? '' ) );

		if ( '' === $default || 'no dice' === strtolower( $default ) ) {
			return array();
		}

		return array( $this->make_part( $default ) );
	}

	/**
	 * Build one value part.
	 *
	 * @param string $text   Raw (unescaped) text, never altered.
	 * @param string $url    Destination URL, '' when the part is not linked.
	 * @param bool   $external Whether the link opens in a new tab.
	 * @param bool   $entity Whether the part is an entity value.
	 * @return array{text:string,url:string,external:bool,entity:bool,image_url:string}
	 */
	private function make_part( string $text, string $url = '', bool $external = false, bool $entity = false ): array {
		return array(
			'text'      => $text,
			'url'       => $url,
			'external'  => $external,
			'entity'    => $entity,
			'image_url' => '',
		);
	}

	/**
	 * Resolve an entity field's value into parts: split on "&" into
	 * independent values (same convention as BFM_Tag_Sync /
	 * BFM_Entity_Repository), attach each one's destination URL when it
	 * has one, and drop the excluded value ("No dice") — it is valid
	 * metadata, but is never treated as an administrable entity anywhere
	 * else in the plugin either.
	 *
	 * @param string               $raw_value      Raw "A & B" stored value.
	 * @param string               $separator      Value separator ("&").
	 * @param string               $excluded_value Excluded value ("no dice", lowercase).
	 * @param array<string,array>  $info_map       value name => [ url, image_id ], from BFM_Entity_Info_Map.
	 * @param array<string,string> $tag_links      value name => TAG archive URL.
	 * @param string               $link_target    'tag' or 'external'.
	 * @param int                  $post_id        Post being rendered; passed to WPAM when signing external links.
	 * @return array<int, array{text:string,url:string,external:bool,entity:bool,image_url:string}>
	 */
	private function resolve_entity_parts( string $raw_value, string $separator, string $excluded_value, array $info_map, array $tag_links, string $link_target = 'tag', int $post_id = 0 ): array {

		$parts = array_filter(
			array_map( 'trim', explode( $separator, $raw_value ) ),
			static function ( $part ) {
				return '' !== $part;
			}
		);

		$resolved = array();

		foreach ( $parts as $part ) {

			if ( $excluded_value === strtolower( $part ) || 'no dice' === strtolower( $part ) ) {
				continue;
			}

			$external_url = isset( $info_map[ $part ]['url'] ) ? (string) $info_map[ $part ]['url'] : '';
			$tag_url      = isset( $tag_links[ $part ] ) ? (string) $tag_links[ $part ] : '';
			$link_url     = '';
			$is_external  = false;

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
			}

			$resolved[] = $this->make_part( $part, $link_url, $is_external, true );
		}

		return $resolved;
	}

	/**
	 * Classic presentation: render one <li> row: prefix element + value element.
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
	 * Classic presentation: render a field's parts as inline text/links,
	 * joined with " & ".
	 *
	 * @param array<int, array{text:string,url:string,external:bool,entity:bool,image_url:string}> $parts Resolved parts.
	 * @return string
	 */
	private function render_parts_inline( array $parts ): string {

		$rendered = array();

		foreach ( $parts as $part ) {
			$text_html = esc_html( $part['text'] );

			$rendered[] = '' !== $part['url'] ? $this->render_link( $part, $text_html ) : $text_html;
		}

		return implode( ' &amp; ', $rendered );
	}

	/**
	 * Render the anchor for a linked part around already-escaped content.
	 * Shared by both presentations so link attributes live in one place.
	 *
	 * @param array{text:string,url:string,external:bool,entity:bool,image_url:string} $part         Resolved part (with a non-empty URL).
	 * @param string                                                                    $content_html Already-escaped content of the anchor.
	 * @return string
	 */
	private function render_link( array $part, string $content_html ): string {

		$link_markup = $part['external']
			? '<a class="bfm-feature-link" href="%1$s" target="_blank" rel="noopener noreferrer"><span>%2$s</span><span class="bfm-feature-link-external-icon" aria-hidden="true">↗</span></a>'
			: '<a class="bfm-feature-link" href="%1$s">%2$s</a>';

		return sprintf( $link_markup, esc_url( $part['url'] ), $content_html );
	}

	/**
	 * Per-request memo (static, never persisted) of what the shared image
	 * services returned, so several blocks in one request (e.g. a query
	 * loop of posts) do not repeat the same resolution:
	 *   - effective image IDs per entity meta key;
	 *   - resolved thumbnail URL per attachment ID ('' = unresolvable).
	 *
	 * @var array<string, array<string, int>>
	 */
	private static array $effective_image_ids_cache = array();

	/** @var array<int, string> */
	private static array $logo_url_cache = array();

	/**
	 * Empty the per-request image memo (e.g. after image settings change
	 * within the same request).
	 *
	 * @return void
	 */
	public static function flush_request_cache(): void {
		self::$effective_image_ids_cache = array();
		self::$logo_url_cache            = array();
	}

	/**
	 * Cards presentation: attach the entity image URL to every entity part
	 * that has one. Only fields with the "logo" capability are considered
	 * (same gate as the "Entidades destacadas" block). Which attachment is
	 * used per value — manual pick, or automatic related-post pick — is
	 * decided by the shared BFM_Entity_Info_Map::get_effective_image_ids(),
	 * and every thumbnail URL is then resolved in a single batched
	 * BFM_Entity_Info_Map::get_logo_urls() call. No image logic lives here.
	 *
	 * @param array<int, array<string,mixed>> $items   Resolved items, modified in place.
	 * @param int                             $post_id Post being rendered.
	 * @return void
	 */
	private function attach_card_images( array &$items, int $post_id ): void {

		$ids_by_part = array();

		foreach ( $items as $item_index => $item ) {

			$meta_key = $item['meta_key'];

			if ( ! BFM_Meta_Fields::has_capability( $meta_key, 'logo' ) ) {
				continue;
			}

			$effective_ids = null;

			foreach ( $item['parts'] as $part_index => $part ) {

				if ( empty( $part['entity'] ) ) {
					continue;
				}

				if ( null === $effective_ids ) {
					if ( ! isset( self::$effective_image_ids_cache[ $meta_key ] ) ) {
						self::$effective_image_ids_cache[ $meta_key ] = BFM_Entity_Info_Map::get_effective_image_ids(
							$meta_key,
							BFM_Meta_Fields::image_source_for_meta_key( $meta_key )
						);
					}

					$effective_ids = self::$effective_image_ids_cache[ $meta_key ];
				}

				$image_id = absint( $effective_ids[ $this->image_lookup_key( $meta_key, $part['text'], $post_id ) ] ?? 0 );

				if ( $image_id > 0 ) {
					$ids_by_part[ $item_index ][ $part_index ] = $image_id;
				}
			}
		}

		if ( empty( $ids_by_part ) ) {
			return;
		}

		$all_ids = array();

		foreach ( $ids_by_part as $part_ids ) {
			foreach ( $part_ids as $image_id ) {
				$all_ids[] = $image_id;
			}
		}

		// Only attachments not already resolved in this request go to the shared batched service.
		$missing = array_values( array_diff( array_unique( $all_ids ), array_keys( self::$logo_url_cache ) ) );

		if ( ! empty( $missing ) ) {
			$urls = BFM_Entity_Info_Map::get_logo_urls( $missing );

			foreach ( $missing as $image_id ) {
				self::$logo_url_cache[ $image_id ] = $urls[ $image_id ] ?? '';
			}
		}

		foreach ( $ids_by_part as $item_index => $part_ids ) {
			foreach ( $part_ids as $part_index => $image_id ) {
				if ( '' !== self::$logo_url_cache[ $image_id ] ) {
					$items[ $item_index ]['parts'][ $part_index ]['image_url'] = self::$logo_url_cache[ $image_id ];
				}
			}
		}
	}

	/**
	 * Key under which an entity value's image is stored/resolved. Identical
	 * to the value name for every field except Personaje, whose images use
	 * the composite identity key (BFM_Character_Identity, the single place
	 * that builds it) from this same post's Series / Illustrator.
	 *
	 * @param string $meta_key Entity meta key.
	 * @param string $name     Value name as stored in the post.
	 * @param int    $post_id  Post being rendered.
	 * @return string
	 */
	private function image_lookup_key( string $meta_key, string $name, int $post_id ): string {

		if ( BFM_Character_Identity::CHARACTER_META_KEY !== $meta_key ) {
			return $name;
		}

		return BFM_Character_Identity::build_identity_key(
			$name,
			trim( (string) get_post_meta( $post_id, BFM_Character_Identity::SERIES_META_KEY, true ) ),
			trim( (string) get_post_meta( $post_id, BFM_Character_Identity::ILLUSTRATOR_META_KEY, true ) )
		);
	}

	/**
	 * Cards presentation: render the card(s) of one field.
	 *
	 * One value, or several values none of which has an image or a valid
	 * link → a single card (several plain values stay grouped, joined with
	 * " & " exactly like the classic list). Several values of which at
	 * least one has an image or a valid link → one independent card per
	 * value, so each card keeps only its own image and its own destination
	 * and a card can be one whole <a> without ever nesting links. Every
	 * card shows the field name at the bottom.
	 *
	 * Image and link of a value come from the same resolved part (found by
	 * the value's name, never by position), so they cannot cross values.
	 *
	 * @param array<string,mixed> $item Resolved item (meta_key, settings, parts).
	 * @return string
	 */
	private function render_cards_for_item( array $item ): string {

		$parts = array();

		foreach ( $item['parts'] as $part ) {
			// Links are validated before use: anything esc_url() rejects is not linked.
			if ( '' !== $part['url'] && '' === esc_url( $part['url'] ) ) {
				$part['url'] = '';
			}
			$parts[] = $part;
		}

		$groups = array( $parts );

		if ( count( $parts ) > 1 ) {
			foreach ( $parts as $part ) {
				if ( '' !== $part['image_url'] || '' !== $part['url'] ) {
					$groups = array_map(
						static function ( $single ) {
							return array( $single );
						},
						$parts
					);
					break;
				}
			}
		}

		$html = '';

		foreach ( $groups as $group ) {
			$html .= $this->render_card( $item['settings'], $group );
		}

		return $html;
	}

	/**
	 * Cards presentation: render one card (inside its <li>) for one value,
	 * or for a group of plain values (no image, no link).
	 *
	 * @param array<string,mixed>                                                                 $settings Effective field settings.
	 * @param array<int, array{text:string,url:string,external:bool,entity:bool,image_url:string}> $parts    One part, or several plain parts.
	 * @return string
	 */
	private function render_card( array $settings, array $parts ): string {

		$link = ( 1 === count( $parts ) && '' !== $parts[0]['url'] ) ? $parts[0] : null;

		if ( 1 === count( $parts ) ) {
			$content = $this->render_card_content( $parts[0] );
		} else {
			$content = '<span class="bfm-card-text">' . implode( ' &amp; ', array_map( 'esc_html', array_column( $parts, 'text' ) ) ) . '</span>';
		}

		$inner = sprintf(
			'<span class="bfm-card-value%1$s"><span class="bfm-card-part">%2$s</span></span><span class="bfm-card-label%3$s">%4$s</span>',
			$settings['valueClass'] ? ' ' . esc_attr( $settings['valueClass'] ) : '',
			$content,
			$settings['prefixClass'] ? ' ' . esc_attr( $settings['prefixClass'] ) : '',
			esc_html( $this->card_label( $settings['prefix'] ) )
		);

		if ( null === $link ) {
			return '<li class="bfm-features-card-item"><div class="bfm-card">' . $inner . '</div></li>';
		}

		if ( $link['external'] ) {
			return sprintf(
				'<li class="bfm-features-card-item"><a class="bfm-card bfm-card--link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="bfm-card-external-icon" aria-hidden="true">↗</span></a></li>',
				esc_url( $link['url'] ),
				$inner
			);
		}

		return sprintf(
			'<li class="bfm-features-card-item"><a class="bfm-card bfm-card--link" href="%1$s">%2$s</a></li>',
			esc_url( $link['url'] ),
			$inner
		);
	}

	/**
	 * Cards presentation: content of one value. With an image, the image
	 * plus the value's name as visible text (the text is never replaced by
	 * the image; the image is decorative, alt="", because the adjacent
	 * text already names it). Without an image, just the escaped text,
	 * never altered.
	 *
	 * @param array{text:string,url:string,external:bool,entity:bool,image_url:string} $part Resolved part.
	 * @return string
	 */
	private function render_card_content( array $part ): string {

		$text_html = esc_html( $part['text'] );

		if ( '' !== $part['image_url'] ) {
			return sprintf(
				'<img class="bfm-card-image" src="%1$s" alt="" loading="lazy" decoding="async" /><span class="bfm-card-text bfm-card-text--caption">%2$s</span>',
				esc_url( $part['image_url'] ),
				$text_html
			);
		}

		return '<span class="bfm-card-text">' . $text_html . '</span>';
	}

	/**
	 * Cards presentation: the field name shown at the bottom of the card.
	 * It is the same configured prefix the classic list uses, minus its
	 * trailing ":" separator (which only makes sense before an inline
	 * value). Falls back to the untouched prefix if nothing would remain.
	 *
	 * @param string $prefix Configured prefix, e.g. "📐 Escala:".
	 * @return string
	 */
	private function card_label( string $prefix ): string {

		$label = trim( (string) preg_replace( '/[\s:：]+$/u', '', $prefix ) );

		return '' !== $label ? $label : $prefix;
	}

	/**
	 * Restrict the presentation to the supported set, falling back to the
	 * classic one (the behavior of blocks saved before this option existed).
	 *
	 * @param mixed $layout Requested presentation.
	 * @return string
	 */
	public static function sanitize_layout( $layout ): string {

		$layout = (string) $layout;

		return in_array( $layout, self::LAYOUTS, true ) ? $layout : self::DEFAULT_LAYOUT;
	}

	/**
	 * Clamp the target card width (px) to the allowed range; a missing or
	 * non-numeric value falls back to the default. The returned integer is
	 * the only thing ever written into the inline style.
	 *
	 * @param mixed $width Requested width in pixels.
	 * @return int
	 */
	public static function sanitize_card_min_width( $width ): int {

		$width = is_numeric( $width ) ? absint( $width ) : 0;

		if ( 0 === $width ) {
			$width = self::DEFAULT_CARD_MIN_WIDTH;
		}

		return max( self::MIN_CARD_MIN_WIDTH, min( self::MAX_CARD_MIN_WIDTH, $width ) );
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
