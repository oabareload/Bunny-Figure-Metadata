<?php
/**
 * BFM_Admin_Ajax
 *
 * AJAX endpoints for the complementary entity data managed on the
 * admin screens (manufacturer, illustrator, series, character):
 *   - a single associated WordPress TAG (term_id);
 *   - an official/external URL;
 *   - a logo image (Media Library attachment).
 *
 * The tag *search-as-you-type* itself is a dedicated endpoint (rather
 * than reusing WordPress core's `ajax-tag-search`, which only returns
 * plain names) because the TAG association must be saved and validated
 * by term_id.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Admin_Ajax {

	/**
	 * Nonce action shared by every endpoint in this class.
	 */
	const NONCE_ACTION = 'bfm_entity_ajax_nonce';

	/**
	 * Register AJAX hooks (logged-in users only; there is no legitimate
	 * public/anonymous use case for this action).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_bfm_search_tags', array( $this, 'handle_search_tags' ) );
		add_action( 'wp_ajax_bfm_set_entity_tag', array( $this, 'handle_set_tag' ) );
		add_action( 'wp_ajax_bfm_remove_entity_tag', array( $this, 'handle_remove_tag' ) );
		add_action( 'wp_ajax_bfm_set_entity_url', array( $this, 'handle_set_url' ) );
		add_action( 'wp_ajax_bfm_set_entity_image', array( $this, 'handle_set_image' ) );
		add_action( 'wp_ajax_bfm_remove_entity_image', array( $this, 'handle_remove_image' ) );
	}

	/**
	 * Search existing post_tag terms by name, returning term_id + name.
	 *
	 * A dedicated endpoint (rather than reusing WordPress core's
	 * ajax-tag-search) because the association must be saved and
	 * validated by term_id, and core's action only returns plain tag
	 * names with no id. Single indexed term query per request.
	 *
	 * @return void
	 */
	public function handle_search_tags(): void {

		$this->guard();

		$search = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'search'     => $search,
				'number'     => 20,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			wp_send_json_success( array() );
		}

		$results = array();

		foreach ( $terms as $term ) {
			$results[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Associate an entity value with a tag term_id.
	 *
	 * @return void
	 */
	public function handle_set_tag(): void {

		$this->guard();

		$entity = $this->validated_entity();
		$value  = $this->validated_value();
		$term   = $this->validated_term();

		if ( null === $entity || empty( $entity['tag'] ) || '' === $value || null === $term ) {
			wp_send_json_error(
				array( 'message' => __( 'Datos inválidos.', 'bunny-figure-metadata' ) )
			);
		}

		BFM_Entity_Tag_Map::set( $entity['meta_key'], $value, $term->term_id );

		wp_send_json_success(
			array(
				'term_id' => $term->term_id,
				'name'    => $term->name,
			)
		);
	}

	/**
	 * Remove the tag association for an entity value.
	 *
	 * @return void
	 */
	public function handle_remove_tag(): void {

		$this->guard();

		$entity = $this->validated_entity();
		$value  = $this->validated_value();

		if ( null === $entity || empty( $entity['tag'] ) || '' === $value ) {
			wp_send_json_error(
				array( 'message' => __( 'Datos inválidos.', 'bunny-figure-metadata' ) )
			);
		}

		BFM_Entity_Tag_Map::delete( $entity['meta_key'], $value );

		wp_send_json_success();
	}

	/**
	 * Set (or clear) the official/external URL for an entity value.
	 *
	 * @return void
	 */
	public function handle_set_url(): void {

		$this->guard();

		$entity = $this->validated_entity();
		$value  = $this->validated_value();

		if ( null === $entity || empty( $entity['url'] ) || '' === $value ) {
			wp_send_json_error(
				array( 'message' => __( 'Datos inválidos.', 'bunny-figure-metadata' ) )
			);
		}

		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$url     = '' === $raw_url ? '' : esc_url_raw( $raw_url );

		if ( '' !== $raw_url && ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La URL no es válida.', 'bunny-figure-metadata' ) )
			);
		}

		BFM_Entity_Info_Map::set_url( $entity['meta_key'], $value, $url );

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Associate an entity value with a Media Library image (logo).
	 *
	 * @return void
	 */
	public function handle_set_image(): void {

		$this->guard();

		$entity      = $this->validated_entity();
		$value       = $this->validated_value();
		$attachment  = $this->validated_image_attachment();

		if ( null === $entity || empty( $entity['logo'] ) || '' === $value || null === $attachment ) {
			wp_send_json_error(
				array( 'message' => __( 'Datos inválidos.', 'bunny-figure-metadata' ) )
			);
		}

		BFM_Entity_Info_Map::set_image( $entity['meta_key'], $value, $attachment );

		$thumbnail_urls = BFM_Entity_Info_Map::get_logo_urls( array( $attachment ) );

		wp_send_json_success(
			array(
				'image_id'  => $attachment,
				'thumbnail' => $thumbnail_urls[ $attachment ] ?? '',
			)
		);
	}

	/**
	 * Remove the logo for an entity value.
	 *
	 * @return void
	 */
	public function handle_remove_image(): void {

		$this->guard();

		$entity = $this->validated_entity();
		$value  = $this->validated_value();

		if ( null === $entity || empty( $entity['logo'] ) || '' === $value ) {
			wp_send_json_error(
				array( 'message' => __( 'Datos inválidos.', 'bunny-figure-metadata' ) )
			);
		}

		BFM_Entity_Info_Map::remove_image( $entity['meta_key'], $value );

		wp_send_json_success();
	}

	/**
	 * Capability + nonce guard shared by every endpoint.
	 *
	 * @return void
	 */
	private function guard(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tienes permiso para realizar esta acción.', 'bunny-figure-metadata' ) )
			);
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Solicitud no válida. Recarga la página e inténtalo de nuevo.', 'bunny-figure-metadata' ) )
			);
		}
	}

	/**
	 * Validate and resolve the posted entity slug to its definition.
	 *
	 * @return array<string, string>|null
	 */
	private function validated_entity(): ?array {

		$slug = isset( $_POST['entity_slug'] )
			? sanitize_key( wp_unslash( $_POST['entity_slug'] ) )
			: '';

		$entities = BFM_Meta_Fields::entities();

		return $entities[ $slug ] ?? null;
	}

	/**
	 * Validate and sanitize the posted entity value name.
	 *
	 * @return string
	 */
	private function validated_value(): string {

		return isset( $_POST['value'] )
			? sanitize_text_field( wp_unslash( $_POST['value'] ) )
			: '';
	}

	/**
	 * Validate the posted term_id: must be a positive integer and must
	 * resolve to an existing post_tag term.
	 *
	 * @return WP_Term|null
	 */
	private function validated_term(): ?WP_Term {

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

		if ( $term_id <= 0 ) {
			return null;
		}

		$term = get_term( $term_id, 'post_tag' );

		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		return $term;
	}

	/**
	 * Validate the posted attachment_id: must be a positive integer and
	 * must resolve to an existing Media Library attachment whose mime
	 * type is an image.
	 *
	 * @return int|null Attachment ID, or null if invalid.
	 */
	private function validated_image_attachment(): ?int {

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( $attachment_id <= 0 ) {
			return null;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		return $attachment_id;
	}
}
