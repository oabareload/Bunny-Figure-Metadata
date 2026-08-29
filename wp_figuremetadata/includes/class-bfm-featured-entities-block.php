<?php
/**
 * BFM_Featured_Entities_Block
 *
 * Registers the "Bunny Figure: Entidades destacadas" Gutenberg block: a
 * dynamic/server-side block that renders a compact ranking of the top
 * values of one entity (Fabricante, Ilustrador, Serie or Personaje),
 * intended for placement in a theme's footer widget area (so it can be
 * added once and appear on every page).
 *
 * Data source: reuses the plugin's existing entity infrastructure —
 *   - BFM_Entity_Repository   for the value => post-count map and the
 *                             five supported orders.
 *   - BFM_Entity_Tag_Map      for the optional "link to TAG" URL.
 *   - BFM_Entity_Info_Map     for the optional logo.
 * No new metadata source, entity concept, or database table is
 * introduced. The block never writes to metadata, entities, tags, or
 * URLs — it is read-only display, exactly like the "Características"
 * block.
 *
 * Caching (required from this first version):
 * The ranked, ready-to-render entity list for a given block
 * configuration is stored in a WordPress transient (persists in
 * wp_options when no persistent object cache is present, and in the
 * object cache when one is), keyed by every attribute that affects the
 * computed result (metadata field, count, order, and whether logos/TAG
 * links need resolving). Deterministic orders (post count, name) get a
 * long TTL; "random" gets a short one so it can actually rotate.
 *
 * Invalidation is lazy and version-based, not enumeration-based: a
 * single small option (`bfm_featured_entities_cache_version`) holds one
 * version counter per entity meta key, included in every cache key.
 * Publishing/scheduling a post bumps the counters for the four entity
 * meta keys — a cheap single get_option()/update_option() pair, no
 * recalculation. Existing transients under the old version are simply
 * no longer looked up; they expire on their own via their TTL instead
 * of being hunted down and deleted individually (which would require
 * tracking every distinct block configuration in use).
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Featured_Entities_Block {

	/**
	 * Fully qualified block name.
	 */
	const BLOCK_NAME = 'bunny-figure-metadata/featured-entities';

	/**
	 * HTML tags a user is allowed to choose for the title element.
	 */
	const TITLE_ALLOWED_TAGS = array( 'h2', 'h3', 'h4', 'h5', 'h6' );

	/**
	 * Orders a user is allowed to choose.
	 */
	const ORDER_OPTIONS = array( 'posts_desc', 'posts_asc', 'name_asc', 'name_desc', 'random' );

	/**
	 * Default logo size in pixels, used when the attribute is missing.
	 */
	const DEFAULT_LOGO_SIZE = 24;

	/**
	 * Allowed logo size range in pixels, so a block attribute can never
	 * blow up the footer layout with an absurd value.
	 */
	const MIN_LOGO_SIZE = 12;
	const MAX_LOGO_SIZE = 96;

	/**
	 * Option name used to store the per-meta-key cache version counters.
	 */
	const CACHE_VERSION_OPTION = 'bfm_featured_entities_cache_version';

	/**
	 * Cache lifetime for deterministic orders (post count, name).
	 */
	const DETERMINISTIC_CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Cache lifetime for the "random" order, so it actually rotates.
	 */
	const RANDOM_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Register all hooks owned by this class.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'save_post_post', array( $this, 'maybe_invalidate_cache' ), 20 );
	}

	/**
	 * Register the block's editor script/style and the block type itself.
	 *
	 * Same no-build-step pattern as the "Características" block: the
	 * editor script is registered manually instead of relying on
	 * block.json's file-based "editorScript".
	 *
	 * @return void
	 */
	public function register_block(): void {

		$block_dir = BFM_PATH . 'blocks/featured-entities';

		wp_register_script(
			'bfm-featured-entities-block-editor',
			BFM_URL . 'blocks/featured-entities/index.js',
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
			'bfm-featured-entities-block-editor',
			'BFM_FEATURED_ENTITIES_BLOCK',
			array( 'entities' => $this->entity_options_for_js() )
		);

		wp_register_style(
			'bfm-featured-entities-block-style',
			BFM_URL . 'blocks/featured-entities/style.css',
			array(),
			BFM_VERSION
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => 'bfm-featured-entities-block-editor',
				'style'           => 'bfm-featured-entities-block-style',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Build the { value, label } list the editor script uses to populate
	 * the "Metadata" select, sourced from BFM_Meta_Fields::entities() —
	 * the single source of truth already used across the plugin.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function entity_options_for_js(): array {

		$options = array();

		foreach ( BFM_Meta_Fields::entities() as $entity ) {
			$options[] = array(
				'value' => $entity['meta_key'],
				'label' => $entity['label'],
			);
		}

		return $options;
	}

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ): string {

		$meta_key = $this->sanitize_meta_key( $attributes['metaKey'] ?? '' );

		if ( '' === $meta_key ) {
			return '';
		}

		$count       = isset( $attributes['count'] ) ? max( 1, min( 50, absint( $attributes['count'] ) ) ) : 5;
		$order_by    = $this->sanitize_order_by( $attributes['orderBy'] ?? 'posts_desc' );
		$show_logo   = ! isset( $attributes['showLogo'] ) || (bool) $attributes['showLogo'];
		$logo_size   = $this->sanitize_logo_size( $attributes['logoSize'] ?? self::DEFAULT_LOGO_SIZE );
		$show_count  = ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'];
		$link_to_tag = ! isset( $attributes['linkToTag'] ) || (bool) $attributes['linkToTag'];
		$link_to_tag = $link_to_tag && BFM_Meta_Fields::has_capability( $meta_key, 'tag' );
		$show_logo   = $show_logo && BFM_Meta_Fields::has_capability( $meta_key, 'logo' );

		$title       = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';
		$title_tag   = $this->sanitize_title_tag( $attributes['titleTag'] ?? 'h2' );
		$title_class = isset( $attributes['titleClassName'] ) ? sanitize_html_class( (string) $attributes['titleClassName'] ) : '';

		$entities = $this->get_ranked_entities( $meta_key, $count, $order_by, $show_logo, $link_to_tag );

		if ( empty( $entities ) ) {
			return '';
		}

		if ( '' === $title ) {
			$title = $this->default_title_for_meta_key( $meta_key );
		}

		$title_html = sprintf(
			'<%1$s class="bfm-featured-entities-title%2$s">%3$s</%1$s>',
			$title_tag,
			$title_class ? ' ' . esc_attr( $title_class ) : '',
			esc_html( $title )
		);

		$rows = array();

		foreach ( $entities as $entity ) {
			$rows[] = $this->render_row( $entity, $show_logo, $show_count, $logo_size );
		}

		$wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'class' => 'bfm-featured-entities-block' ) )
			: 'class="bfm-featured-entities-block"';

		return sprintf(
			'<div %1$s>%2$s<ul class="bfm-featured-entities-list">%3$s</ul></div>',
			$wrapper_attributes,
			$title_html,
			implode( '', $rows )
		);
	}

	/**
	 * Render a single "<li>" row: optional logo (rendered at the
	 * configured, always-square size), name (linked to its TAG archive
	 * when configured and available, plain text otherwise), and optional
	 * post count.
	 *
	 * @param array{name:string,count:int,term_id:int,tag_url:string,logo_url:string} $entity     Resolved entity data.
	 * @param bool                                                                    $show_logo  Whether to render the logo.
	 * @param bool                                                                    $show_count $Whether to render the post count.
	 * @param int                                                                     $logo_size  Logo width/height in pixels (already sanitized).
	 * @return string
	 */
	private function render_row( array $entity, bool $show_logo, bool $show_count, int $logo_size ): string {

		$logo_html = '';

		if ( $show_logo && '' !== $entity['logo_url'] ) {
			// width/height/style all come from the same sanitized integer, so
			// the logo is always rendered square (1:1) regardless of the
			// source image's own aspect ratio; object-fit: contain (in
			// style.css) prevents any stretching/deformation within that box.
			$logo_html = sprintf(
				'<img class="bfm-featured-entity-logo" src="%1$s" alt="" width="%2$d" height="%2$d" style="width:%2$dpx;height:%2$dpx;" loading="lazy" />',
				esc_url( $entity['logo_url'] ),
				$logo_size
			);
		}

		if ( $entity['term_id'] > 0 && '' !== $entity['tag_url'] ) {
			$name_html = sprintf(
				'<a class="bfm-featured-entity-name" href="%s">%s</a>',
				esc_url( $entity['tag_url'] ),
				esc_html( $entity['name'] )
			);
		} else {
			$name_html = sprintf(
				'<span class="bfm-featured-entity-name">%s</span>',
				esc_html( $entity['name'] )
			);
		}

		$count_html = '';

		if ( $show_count ) {
			$count_html = sprintf(
				'<span class="bfm-featured-entity-count">%s</span>',
				esc_html( number_format_i18n( $entity['count'] ) )
			);
		}

		return sprintf( '<li class="bfm-featured-entity">%s%s%s</li>', $logo_html, $name_html, $count_html );
	}

	/**
	 * Get the ranked, ready-to-render entity list for a block
	 * configuration, from the persistent cache when available, or
	 * computed and cached otherwise.
	 *
	 * @param string $meta_key    Entity meta key.
	 * @param int    $count       Number of entities to return.
	 * @param string $order_by    One of ORDER_OPTIONS.
	 * @param bool   $show_logo   Whether logos need resolving.
	 * @param bool   $link_to_tag Whether TAG URLs need resolving.
	 * @return array<int, array{name:string,count:int,term_id:int,tag_url:string,logo_url:string}>
	 */
	private function get_ranked_entities( string $meta_key, int $count, string $order_by, bool $show_logo, bool $link_to_tag ): array {

		$cache_key = $this->build_cache_key( $meta_key, $count, $order_by, $show_logo, $link_to_tag );

		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$values = BFM_Entity_Repository::get_values_with_counts( $meta_key );
		$values = BFM_Entity_Repository::sort_values_for_order( $values, $order_by );

		$top_names = array_slice( array_keys( $values ), 0, $count );

		$entities = array();

		foreach ( $top_names as $name ) {
			$entities[ $name ] = array(
				'name'     => $name,
				'count'    => $values[ $name ],
				'term_id'  => 0,
				'tag_url'  => '',
				'logo_url' => '',
			);
		}

		if ( $link_to_tag && ! empty( $entities ) ) {
			$this->attach_tag_urls( $entities, $meta_key );
		}

		if ( $show_logo && ! empty( $entities ) ) {
			$this->attach_logo_urls( $entities, $meta_key );
		}

		$entities = array_values( $entities );

		$ttl = 'random' === $order_by ? self::RANDOM_CACHE_TTL : self::DETERMINISTIC_CACHE_TTL;

		set_transient( $cache_key, $entities, $ttl );

		return $entities;
	}

	/**
	 * Attach the associated TAG term_id + archive URL to each entity that
	 * has one, resolving every term in a single batched get_terms() call
	 * (never one get_term()/get_term_link() query per row).
	 *
	 * @param array<string, array<string, mixed>> $entities Map of name => entity data, modified in place.
	 * @param string                               $meta_key Entity meta key.
	 * @return void
	 */
	private function attach_tag_urls( array &$entities, string $meta_key ): void {

		$tag_map = BFM_Entity_Tag_Map::get_for_meta_key( $meta_key );

		$term_ids = array();

		foreach ( $entities as $name => $entity ) {
			$term_id = isset( $tag_map[ $name ] ) ? absint( $tag_map[ $name ] ) : 0;
			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}

		$term_ids = array_values( array_unique( $term_ids ) );

		if ( empty( $term_ids ) ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		$terms_by_id = array();

		foreach ( $terms as $term ) {
			$terms_by_id[ $term->term_id ] = $term;
		}

		foreach ( $entities as $name => &$entity ) {

			$term_id = isset( $tag_map[ $name ] ) ? absint( $tag_map[ $name ] ) : 0;

			if ( ! $term_id || ! isset( $terms_by_id[ $term_id ] ) ) {
				continue;
			}

			$link = get_term_link( $terms_by_id[ $term_id ] );

			if ( ! is_wp_error( $link ) ) {
				$entity['term_id'] = $term_id;
				$entity['tag_url'] = $link;
			}
		}
		unset( $entity );
	}

	/**
	 * Attach the logo URL to each entity that has one. The attachment ID
	 * actually used per value — a manual pick, or the automatic
	 * related-post pick when the field's image source is 'related' — is
	 * resolved by the single shared service
	 * (BFM_Entity_Info_Map::get_effective_image_ids()), the same one the
	 * admin entity list uses; this method never decides that itself.
	 * Thumbnail URLs for the resolved IDs are then resolved in one batched
	 * call via BFM_Entity_Info_Map::get_logo_urls() (no duplicated logic).
	 *
	 * @param array<string, array<string, mixed>> $entities Map of name => entity data, modified in place.
	 * @param string                               $meta_key Entity meta key.
	 * @return void
	 */
	private function attach_logo_urls( array &$entities, string $meta_key ): void {

		$image_source = BFM_Meta_Fields::image_source_for_meta_key( $meta_key );
		$effective_image_ids = BFM_Entity_Info_Map::get_effective_image_ids( $meta_key, $image_source );

		$image_ids = array();

		foreach ( $entities as $name => $entity ) {
			$image_id = absint( $effective_image_ids[ $name ] ?? 0 );
			if ( $image_id > 0 ) {
				$image_ids[] = $image_id;
			}
		}

		if ( empty( $image_ids ) ) {
			return;
		}

		$logo_urls = BFM_Entity_Info_Map::get_logo_urls( $image_ids );

		foreach ( $entities as $name => &$entity ) {

			$image_id = absint( $effective_image_ids[ $name ] ?? 0 );

			if ( $image_id && isset( $logo_urls[ $image_id ] ) ) {
				$entity['logo_url'] = $logo_urls[ $image_id ];
			}
		}
		unset( $entity );
	}

	/**
	 * Build the transient cache key for a block configuration, folding
	 * in the entity's current cache version so a version bump makes
	 * previously cached keys unreachable without deleting them.
	 *
	 * @param string $meta_key    Entity meta key.
	 * @param int    $count       Number of entities.
	 * @param string $order_by    Requested order.
	 * @param bool   $show_logo   Whether logos are resolved.
	 * @param bool   $link_to_tag Whether TAG URLs are resolved.
	 * @return string
	 */
	private function build_cache_key( string $meta_key, int $count, string $order_by, bool $show_logo, bool $link_to_tag ): string {

		$version = self::get_cache_version( $meta_key );

		$raw = implode(
			'|',
			array(
				$meta_key,
				$count,
				$order_by,
				$show_logo ? '1' : '0',
				$link_to_tag ? '1' : '0',
				$version,
			)
		);

		return 'bfm_fe_' . md5( $raw );
	}

	/**
	 * Read the current cache version counter for an entity meta key.
	 *
	 * @param string $meta_key Entity meta key.
	 * @return int
	 */
	private static function get_cache_version( string $meta_key ): int {

		$versions = get_option( self::CACHE_VERSION_OPTION, array() );

		return isset( $versions[ $meta_key ] ) ? absint( $versions[ $meta_key ] ) : 0;
	}

	/**
	 * Bump the cache version counter for one or more entity meta keys in
	 * a single read/write, making every previously built cache key for
	 * those meta keys unreachable (they simply expire later via their
	 * own TTL instead of being individually deleted).
	 *
	 * @param array<int, string> $meta_keys Entity meta keys to bump.
	 * @return void
	 */
	private static function bump_cache_versions( array $meta_keys ): void {

		$versions = get_option( self::CACHE_VERSION_OPTION, array() );

		if ( ! is_array( $versions ) ) {
			$versions = array();
		}

		foreach ( $meta_keys as $meta_key ) {
			$versions[ $meta_key ] = ( isset( $versions[ $meta_key ] ) ? absint( $versions[ $meta_key ] ) : 0 ) + 1;
		}

		update_option( self::CACHE_VERSION_OPTION, $versions, false );
	}

	/**
	 * Lazily invalidate the featured-entities cache when a post's entity
	 * metadata could have changed the ranking. Mirrors BFM_Tag_Sync's own
	 * status guard (publish/future only, never drafts) so both classes
	 * agree on when metadata is "live", but is kept as its own hook
	 * rather than folded into BFM_Tag_Sync, so this block's caching
	 * concern stays decoupled from tag synchronization.
	 *
	 * All four entity meta keys are bumped unconditionally on every
	 * qualifying save (rather than only the ones with a current
	 * non-empty value) so that clearing a field also invalidates its
	 * ranking correctly, without needing to compare against the
	 * previous value. This never recalculates anything immediately —
	 * it only increments a counter; the next visitor regenerates and
	 * re-caches the result.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function maybe_invalidate_cache( int $post_id ): void {

		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		$status = get_post_status( $post_id );

		if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::bump_cache_versions( BFM_Meta_Fields::tag_sources() );
	}

	/**
	 * Build the default title for a meta key ("Fabricantes destacados",
	 * etc.) from BFM_Meta_Fields::entities(), used when no custom title
	 * is configured on the block.
	 *
	 * @param string $meta_key Entity meta key.
	 * @return string
	 */
	private function default_title_for_meta_key( string $meta_key ): string {

		foreach ( BFM_Meta_Fields::entities() as $entity ) {
			if ( $entity['meta_key'] === $meta_key ) {
				/* translators: %s: entity label, e.g. "Fabricantes". */
				return sprintf( __( '%s destacados', 'bunny-figure-metadata' ), $entity['label'] );
			}
		}

		return __( 'Entidades destacadas', 'bunny-figure-metadata' );
	}

	/**
	 * Restrict the configured metadata field to one of the four
	 * tag-source entity meta keys.
	 *
	 * @param mixed $meta_key Requested meta key.
	 * @return string Valid meta key, or '' if invalid.
	 */
	private function sanitize_meta_key( $meta_key ): string {

		$meta_key = (string) $meta_key;

		return in_array( $meta_key, BFM_Meta_Fields::tag_sources(), true ) ? $meta_key : '';
	}

	/**
	 * Restrict the configured order to the supported set.
	 *
	 * @param mixed $order_by Requested order.
	 * @return string
	 */
	private function sanitize_order_by( $order_by ): string {

		$order_by = (string) $order_by;

		return in_array( $order_by, self::ORDER_OPTIONS, true ) ? $order_by : 'posts_desc';
	}

	/**
	 * Restrict a user-configured title HTML tag to a small allow-list
	 * (H2-H6), so a block attribute can never inject an unexpected tag.
	 *
	 * @param mixed $tag Requested tag.
	 * @return string
	 */
	private function sanitize_title_tag( $tag ): string {

		$tag = strtolower( (string) $tag );

		return in_array( $tag, self::TITLE_ALLOWED_TAGS, true ) ? $tag : 'h2';
	}

	/**
	 * Clamp the configured logo size to a sane pixel range, falling back
	 * to the default when the attribute is missing or resolves to 0 (e.g.
	 * a non-numeric attribute value). Does not affect the cache: the logo
	 * size is applied at render time to the already-cached entity list,
	 * not baked into the cached data itself.
	 *
	 * @param mixed $size Requested logo size (px).
	 * @return int
	 */
	private function sanitize_logo_size( $size ): int {

		$size = absint( $size );

		if ( 0 === $size ) {
			$size = self::DEFAULT_LOGO_SIZE;
		}

		return max( self::MIN_LOGO_SIZE, min( self::MAX_LOGO_SIZE, $size ) );
	}
}
