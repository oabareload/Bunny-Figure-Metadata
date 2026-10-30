<?php
/**
 * BFM_Post_Editor
 *
 * Registers, renders, and saves structured figure metadata in the
 * classic WordPress post editor.
 *
 * The four entity fields (Manufacturer, Illustrator, Series, Character)
 * are edited as removable chips, with autocomplete sourced from existing
 * administered entity values (BFM_Entity_Repository) rather than from
 * WordPress tags. Internally, the value is still stored and submitted as
 * a single "A & B" plain-text string in a hidden input, exactly as
 * before — only the editing UI changed, not the storage format.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Post_Editor {

	/**
	 * Register all hooks owned by this class.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'init', array( $this, 'enable_custom_fields' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_metadata' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'print_admin_css' ) );
	}

	/**
	 * Register metadata with WordPress REST API / Block Editor.
	 *
	 * @return void
	 */
	public function register_meta(): void {

		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {
			$is_textarea = 'textarea' === ( $field['type'] ?? 'text' );
			register_post_meta(
				'post',
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $is_textarea ? 'sanitize_textarea_field' : 'sanitize_text_field',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id, $user_id ) {
						return user_can( $user_id, 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Ensure normal posts support custom fields.
	 *
	 * @return void
	 */
	public function enable_custom_fields(): void {
		add_post_type_support( 'post', 'custom-fields' );
	}

	/**
	 * Add classic metabox to the post editor.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {

		add_meta_box(
			'bfm_figure_metadata',
			'🐰 Datos de la figura',
			array( $this, 'render_meta_box' ),
			'post',
			'normal',
			'high'
		);
	}

	/**
	 * Render metadata fields.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ): void {

		wp_nonce_field(
			'bfm_save_figure_metadata',
			'bfm_figure_metadata_nonce'
		);

		$fields     = BFM_Meta_Fields::all();
		$tag_fields = BFM_Meta_Fields::entity_sources();

		echo '<div class="bfm-figure-meta-grid">';

		foreach ( $fields as $meta_key => $field ) {

			$value = get_post_meta(
				$post->ID,
				$meta_key,
				true
			);

			if ( 'no dice' === strtolower( trim( (string) $value ) ) ) {
				$value = '';
			}

			$is_tag_source = in_array(
				$meta_key,
				$tag_fields,
				true
			);

			$is_textarea = 'textarea' === ( $field['type'] ?? 'text' );
			$field_classes = 'bfm-figure-meta-field' . ( $is_textarea ? ' bfm-figure-meta-field--textarea' : '' );

			echo '<div class="' . esc_attr( $field_classes ) . '">';

			printf(
				'<label for="%1$s"><strong>%2$s</strong></label>',
				esc_attr( $meta_key ),
				esc_html( $field['label'] )
			);

			if ( $is_tag_source ) {
				$this->render_chip_field( $meta_key, $value );
			} elseif ( $is_textarea ) {
				printf(
					'<textarea id="%1$s" name="%1$s" class="widefat bfm-figure-meta-textarea" rows="6">%2$s</textarea>',
					esc_attr( $meta_key ),
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat" autocomplete="off">',
					esc_attr( $meta_key ),
					esc_attr( $value )
				);
			}

			echo '</div>';
		}

		echo '</div>';

		echo '<p class="description">';
		echo esc_html(
			'Estos datos se guardan como metadata estructurada de la figura.'
		);
		echo '</p>';
	}

	/**
	 * Render a chip-based field for one of the four entity meta keys
	 * (Manufacturer, Illustrator, Series, Character).
	 *
	 * The actual form field submitted (and read by save_metadata()) is a
	 * hidden input holding the same "A & B" plain-text format as before;
	 * BFM_Post_Editor_Chips (JS) renders/edits it as chips and keeps the
	 * hidden input in sync. No change to how the value is stored.
	 *
	 * @param string $meta_key Meta key (e.g. _bunny_figure_manufacturer).
	 * @param string $value    Current raw "A & B" stored value.
	 * @return void
	 */
	private function render_chip_field( string $meta_key, string $value ): void {

		printf(
			'<div class="bfm-figure-chip-field" data-meta-key="%1$s">
				<input type="hidden" id="%1$s" name="%1$s" value="%2$s">
				<div class="bfm-figure-chip-control">
					<span class="bfm-figure-chip-list"></span>
					<input type="text" class="bfm-figure-chip-input" placeholder="%3$s" autocomplete="off">
				</div>
			</div>
			<p class="description">%4$s</p>',
			esc_attr( $meta_key ),
			esc_attr( $value ),
			esc_attr__( 'Agregar...', 'bunny-figure-metadata' ),
			esc_html__( 'Escribe para buscar entre los valores de entidad existentes, o presiona Enter para agregar uno nuevo.', 'bunny-figure-metadata' )
		);
	}

	/**
	 * Save metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_metadata( int $post_id ): void {

		/*
		 * Nonce.
		 */
		if (
			! isset( $_POST['bfm_figure_metadata_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash(
						$_POST['bfm_figure_metadata_nonce']
					)
				),
				'bfm_save_figure_metadata'
			)
		) {
			return;
		}

		/*
		 * Autosave.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		/*
		 * Revision.
		 */
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		/*
		 * Permission.
		 */
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( BFM_Meta_Fields::all() as $meta_key => $field ) {

			if ( ! isset( $_POST[ $meta_key ] ) ) {
				continue;
			}

			self::save_meta_value_for_post( $post_id, $meta_key, $_POST[ $meta_key ] );
		}
	}

	/**
	 * Sanitize one metadata value using the same logic already used by the
	 * post editor, while preserving the plugin's multi-value format for
	 * entity fields.
	 *
	 * @param string $meta_key Source meta key.
	 * @param mixed  $raw_value Raw submitted value.
	 * @return string
	 */
	public static function sanitize_meta_value_for_storage( string $meta_key, $raw_value ): string {
		$field = BFM_Meta_Fields::all()[ $meta_key ] ?? null;
		if ( ! is_array( $field ) ) {
			return '';
		}

		$value = is_scalar( $raw_value ) ? (string) $raw_value : '';
		$value = wp_unslash( $value );

		if ( ! empty( $field['entity'] ) ) {
			$parts = array();
			foreach ( preg_split( '/\s*&\s*/', $value ) as $part ) {
				$clean = trim( sanitize_text_field( $part ) );
				if ( '' !== $clean && 'no dice' !== strtolower( $clean ) ) {
					$parts[] = $clean;
				}
			}
			return implode( ' & ', $parts );
		}

		if ( 'textarea' === ( $field['type'] ?? 'text' ) ) {
			$clean = trim( sanitize_textarea_field( $value ) );
		} else {
			$clean = trim( sanitize_text_field( $value ) );
		}

		if ( 'no dice' === strtolower( $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Save one meta key through the plugin's existing storage path, without
	 * bypassing its sanitization rules.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $raw_value Raw value.
	 * @return bool
	 */
	public static function save_meta_value_for_post( int $post_id, string $meta_key, $raw_value ): bool {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$value = self::sanitize_meta_value_for_storage( $meta_key, $raw_value );

		if ( '' === $value ) {
			return delete_post_meta( $post_id, $meta_key );
		}

		return (bool) update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Load chip-field assets only on the post editor for the 'post' type.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {

		if ( ! in_array(
			$hook_suffix,
			array(
				'post.php',
				'post-new.php',
			),
			true
		) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'bfm-post-editor-chips',
			BFM_URL . 'assets/js/post-editor-chips.js',
			array( 'jquery' ),
			BFM_VERSION,
			true
		);

		wp_localize_script(
			'bfm-post-editor-chips',
			'BFM_POST_EDITOR_CHIPS',
			array(
				'suggestions' => self::get_entity_suggestions(),
				'i18n'        => array(
					'remove' => __( 'Quitar', 'bunny-figure-metadata' ),
				),
			)
		);
	}

	/**
	 * Preload the existing entity values for each of the configured chip fields,
	 * so the editor's duplicate detection is based on administered entity values
	 * (BFM_Entity_Repository, the same source used by the admin screens),
	 * not on WordPress tags. The existing aggregated query is reused once per
	 * configured entity field; there are no per-keystroke AJAX calls or N+1
	 * queries.
	 *
	 * @return array<string, array<int, string>> meta_key => value names.
	 */
	public static function get_entity_suggestions(): array {

		$suggestions = array();

		foreach ( BFM_Meta_Fields::entity_sources() as $meta_key ) {

			$keys = array_keys( BFM_Entity_Repository::get_values_with_counts( $meta_key ) );

			// For character, get_values_with_counts() groups by the internal
			// identity key (BFM_Character_Identity) so two same-named
			// characters aren't merged; the chip editor only ever needs the
			// plain name for autocomplete/duplicate-detection, so reduce back
			// to unique display names via the same shared helper.
			if ( BFM_Character_Identity::CHARACTER_META_KEY === $meta_key ) {
				$keys = array_values( array_unique( array_map( array( 'BFM_Character_Identity', 'display_name' ), $keys ) ) );
			}

			$suggestions[ $meta_key ] = $keys;
		}

		return $suggestions;
	}

	/**
	 * Print admin styling for the metabox.
	 *
	 * @return void
	 */
	public function print_admin_css(): void {

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.bfm-figure-meta-grid {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 16px;
				margin: 4px 0 12px;
			}

			.bfm-figure-meta-field label {
				display: block;
				margin-bottom: 6px;
			}

			.bfm-figure-meta-field--textarea {
				grid-column: 1 / -1;
			}

			.bfm-figure-meta-textarea {
				min-height: 140px;
				resize: vertical;
				line-height: 1.5;
				font-family: inherit;
				box-sizing: border-box;
			}

			.bfm-figure-meta-field .description {
				margin-top: 5px;
			}

			.bfm-figure-chip-field {
				position: relative;
			}

			.bfm-figure-chip-control {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 6px;
				width: 100%;
				box-sizing: border-box;
				padding: 4px 6px;
				border: 1px solid #8c8f94;
				border-radius: 4px;
				background: #fff;
			}

			.bfm-figure-chip-control:focus-within {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
			}

			.bfm-figure-chip-list {
				display: contents;
			}

			.bfm-figure-chip {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				background: #f0f0f0;
				border: 1px solid #c3c4c7;
				color: #1d2327;
				font-size: 12px;
				font-weight: 600;
				padding: 4px 6px 4px 10px;
				border-radius: 20px;
				white-space: nowrap;
			}

			.bfm-figure-chip-existing {
				background: #edfaef;
				border-color: #68b879;
				color: #155724;
			}

			.bfm-figure-chip-possible {
				background: #fff7e6;
				border-color: #dba617;
				color: #7a4b00;
			}

			.bfm-figure-chip-new {
				background: #fff0f0;
				border-color: #d63638;
				color: #8a2424;
			}

			.bfm-figure-chip-remove {
				background: none;
				border: none;
				color: inherit;
				cursor: pointer;
				font-size: 14px;
				line-height: 1;
				padding: 0 2px;
				opacity: 0.7;
			}

			.bfm-figure-chip-remove:hover {
				opacity: 1;
			}

			.bfm-figure-chip-action {
				background: none;
				border: none;
				color: inherit;
				cursor: pointer;
				font-size: 13px;
				font-weight: 700;
				line-height: 1;
				padding: 0 1px;
			}

			.bfm-figure-chip-action:hover {
				text-decoration: underline;
			}

			.bfm-figure-chip-input {
				flex: 1 1 120px;
				min-width: 120px;
				border: none;
				outline: none;
				box-shadow: none;
				padding: 2px 4px;
				margin: 0;
			}

			@media (max-width: 782px) {
				.bfm-figure-meta-grid {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}
}
