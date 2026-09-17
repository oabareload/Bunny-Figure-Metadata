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
		add_action( 'wp_ajax_bfm_load_post_metadata_rows', array( $this, 'handle_load_post_metadata_rows' ) );
		add_action( 'wp_ajax_bfm_save_post_metadata', array( $this, 'handle_save_post_metadata' ) );
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
	 * Load a paged slice of posts for the maintenance screen, honoring title,
	 * selected metadata, value state, and exact/simple value searches.
	 *
	 * @return void
	 */
	public function handle_load_post_metadata_rows(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para ver esta página.', 'bunny-figure-metadata' ) ) );
		}

		check_ajax_referer( 'bfm_post_metadata', 'nonce' );

		$page = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$page = max( 1, $page );
		$per_page = 20;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$meta_key = isset( $_POST['meta_key'] ) ? sanitize_key( wp_unslash( $_POST['meta_key'] ) ) : '';
		$value_status = isset( $_POST['value_status'] ) ? sanitize_key( wp_unslash( $_POST['value_status'] ) ) : 'all';
		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		$fields = BFM_Meta_Fields::all();
		if ( '' !== $meta_key && ! isset( $fields[ $meta_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'El metadato seleccionado no existe.', 'bunny-figure-metadata' ) ) );
		}

		$args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		if ( '' !== $title ) {
			$args['s'] = $title;
		}

		if ( '' !== $meta_key ) {
			$meta_query = array( 'relation' => 'AND' );
			if ( 'without' === $value_status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => $meta_key,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => $meta_key,
						'value'   => '',
						'compare' => '=',
					),
				);
			} elseif ( 'with' === $value_status ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				);
				if ( '' !== $value ) {
					// WP_Meta_Query wraps LIKE values in '%...%' (and escapes them)
					// itself; adding our own '%' here would double-wrap and never match.
					$meta_query[] = array(
						'key'     => $meta_key,
						'value'   => $value,
						'compare' => 'LIKE',
					);
				}
			} elseif ( '' !== $value ) {
				// 'all' (any value): no existence restriction unless a specific
				// value is being searched for — the LIKE clause below already
				// requires the meta row to exist to match.
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => $value,
					'compare' => 'LIKE',
				);
			}

			if ( count( $meta_query ) > 1 ) {
				$args['meta_query'] = $meta_query;
			}
		}

		$query = new WP_Query( $args );
		$rows  = '';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$rows .= self::build_maintenance_row_for_post( get_post() );
			}
		}

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'rows'     => $rows,
				'page'     => $page,
				'has_more' => $query->max_num_pages > $page,
			)
		);
	}

	/**
	 * Save each metadata field for one post through the plugin's stored
	 * sanitization and save path.
	 *
	 * @return void
	 */
	public function handle_save_post_metadata(): void {
		check_ajax_referer( 'bfm_post_metadata', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'El post no es válido.', 'bunny-figure-metadata' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para editar este post.', 'bunny-figure-metadata' ) ) );
		}

		$payload   = isset( $_POST['meta_values'] ) && is_array( $_POST['meta_values'] ) ? wp_unslash( $_POST['meta_values'] ) : array();
		$processed = 0;

		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {
			if ( ! isset( $payload[ $meta_key ] ) ) {
				continue;
			}
			// The return value only reflects whether the stored value actually
			// changed (update_post_meta()/delete_post_meta() both return false
			// for a no-op — same value already stored, or nothing to delete),
			// not whether the field was valid, so it must not gate the error
			// below: re-saving unchanged data, or clearing an already-empty
			// field, is a legitimate no-op and not a failure.
			BFM_Post_Editor::save_meta_value_for_post( $post_id, $meta_key, $payload[ $meta_key ] );
			++$processed;
		}

		if ( 0 === $processed ) {
			wp_send_json_error( array( 'message' => __( 'No se recibieron metadatos válidos para guardar.', 'bunny-figure-metadata' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cambios guardados.', 'bunny-figure-metadata' ),
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Render the HTML for a single maintenance row.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public static function build_maintenance_row_for_post( WP_Post $post ): string {
		ob_start();
		?>
		<article class="bfm-post-card" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<header class="bfm-card-header">
				<div class="bfm-card-meta-top">
					<span class="bfm-card-id-badge">#<?php echo esc_html( (string) $post->ID ); ?></span>
					<div class="bfm-card-links">
						<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ?: admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ); ?>" class="bfm-card-action-link" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Editar post en el editor', 'bunny-figure-metadata' ); ?>">
							<span class="dashicons dashicons-edit"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Editar post', 'bunny-figure-metadata' ); ?></span>
						</a>
						<?php if ( 'publish' === $post->post_status ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="bfm-card-action-link" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Ver post en sitio', 'bunny-figure-metadata' ); ?>">
								<span class="dashicons dashicons-external"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Ver post', 'bunny-figure-metadata' ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</div>
				<h3 class="bfm-card-title">
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ?: admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( get_the_title( $post ) ?: __( '(Sin título)', 'bunny-figure-metadata' ) ); ?>
					</a>
				</h3>
			</header>

			<div class="bfm-card-preview">
				<?php $thumbnail_url = get_the_post_thumbnail_url( $post, 'thumbnail' ); ?>
				<div class="bfm-card-thumb-wrap">
					<?php if ( $thumbnail_url ) : ?>
						<img class="bfm-card-thumb" src="<?php echo esc_url( $thumbnail_url ); ?>" alt="">
					<?php else : ?>
						<span class="bfm-card-thumb bfm-card-thumb-placeholder" aria-hidden="true">
							<span class="dashicons dashicons-format-image"></span>
						</span>
					<?php endif; ?>
				</div>
				<div class="bfm-card-excerpt-wrap">
					<?php $excerpt = trim( wp_strip_all_tags( $post->post_excerpt ) ); ?>
					<?php if ( '' !== $excerpt ) : ?>
						<p class="bfm-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
					<?php else : ?>
						<p class="bfm-card-excerpt bfm-card-excerpt-empty"><?php esc_html_e( 'Sin excerpt.', 'bunny-figure-metadata' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="bfm-card-fields-grid">
				<?php foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) :
					$value     = (string) get_post_meta( $post->ID, $meta_key, true );
					if ( 'no dice' === strtolower( trim( $value ) ) ) {
						$value = '';
					}
					$is_entity = ! empty( $field['entity'] );
					$field_cls = 'bfm-card-field' . ( $is_entity ? ' bfm-card-field-full' : '' );
				?>
					<div class="<?php echo esc_attr( $field_cls ); ?>" data-meta-key="<?php echo esc_attr( $meta_key ); ?>">
						<label class="bfm-card-field-label">
							<?php echo esc_html( $field['label'] ); ?>
						</label>
						<div class="bfm-card-field-control">
							<?php if ( $is_entity ) : ?>
								<div class="bfm-figure-chip-field" data-meta-key="<?php echo esc_attr( $meta_key ); ?>">
									<input type="hidden" value="<?php echo esc_attr( $value ); ?>" data-meta-key="<?php echo esc_attr( $meta_key ); ?>">
									<div class="bfm-figure-chip-control">
										<span class="bfm-figure-chip-list"></span>
										<input type="text" class="bfm-figure-chip-input" autocomplete="off" aria-label="<?php echo esc_attr( $field['label'] ); ?>" data-meta-key="<?php echo esc_attr( $meta_key ); ?>">
									</div>
								</div>
							<?php else : ?>
								<input type="text" class="bfm-card-input" value="<?php echo esc_attr( $value ); ?>" data-meta-key="<?php echo esc_attr( $meta_key ); ?>" placeholder="<?php echo esc_attr( $field['label'] ); ?>">
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<footer class="bfm-card-footer">
				<span class="bfm-card-save-status" aria-live="polite"></span>
				<button type="button" class="button button-primary bfm-maintenance-save" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
					<?php esc_html_e( 'Guardar', 'bunny-figure-metadata' ); ?>
				</button>
			</footer>
		</article>
		<?php
		return (string) ob_get_clean();
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
