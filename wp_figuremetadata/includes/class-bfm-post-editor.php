<?php
/**
 * BFM_Post_Editor
 *
 * Registers, renders, and saves structured figure metadata in the
 * classic WordPress post editor.
 *
 * Migrated from the bunny-figure-metadata-editor.php production snippet
 * without changing behavior: same fields, same metabox, same nonce,
 * same autocomplete-on-"&"-separated-values UX.
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

			register_post_meta(
				'post',
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
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
		$tag_fields = BFM_Meta_Fields::tag_sources();

		echo '<div class="bfm-figure-meta-grid">';

		foreach ( $fields as $meta_key => $field ) {

			$value = get_post_meta(
				$post->ID,
				$meta_key,
				true
			);

			$is_tag_source = in_array(
				$meta_key,
				$tag_fields,
				true
			);

			printf(
				'<div class="bfm-figure-meta-field">
					<label for="%1$s"><strong>%2$s</strong></label>
					<input
						type="text"
						id="%1$s"
						name="%1$s"
						value="%3$s"
						class="widefat%4$s"
						autocomplete="off"
						%5$s
					>
					%6$s
				</div>',
				esc_attr( $meta_key ),
				esc_html( $field['label'] ),
				esc_attr( $value ),
				$is_tag_source ? ' bfm-figure-tag-autocomplete' : '',
				$is_tag_source ? 'data-bfm-tag-autocomplete="1"' : '',
				$is_tag_source
					? '<p class="description">Puedes usar <code>&amp;</code> para agregar varios valores.</p>'
					: ''
			);
		}

		echo '</div>';

		echo '<p class="description">';
		echo esc_html(
			'Estos datos se guardan como metadata estructurada de la figura.'
		);
		echo '</p>';
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

			$value = sanitize_text_field(
				wp_unslash(
					$_POST[ $meta_key ]
				)
			);

			if ( '' === $value ) {

				delete_post_meta(
					$post_id,
					$meta_key
				);

			} else {

				update_post_meta(
					$post_id,
					$meta_key,
					$value
				);
			}
		}
	}

	/**
	 * Load autocomplete assets only on the post editor.
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

		wp_enqueue_script( 'jquery-ui-autocomplete' );

		$ajax_url = admin_url( 'admin-ajax.php' );

		$script = '
		(function($) {
			"use strict";

			$(function() {

				var $fields = $(".bfm-figure-tag-autocomplete");

				if (!$fields.length) {
					return;
				}

				$fields.each(function() {

					var $input = $(this);

					$input.autocomplete({
						minLength: 2,

						source: function(request, response) {

							/*
							 * Only search the value currently being typed.
							 *
							 * Example:
							 * Good Smile Company & Max
							 *
							 * The autocomplete searches only:
							 * Max
							 */
							var value = request.term || "";
							var parts = value.split("&");
							var current = $.trim(parts.pop() || "");

							if (current.length < 2) {
								response([]);
								return;
							}

							$.ajax({
								url: ' . wp_json_encode( $ajax_url ) . ',
								dataType: "text",
								data: {
									action: "ajax-tag-search",
									tax: "post_tag",
									q: current,
									number: 20
								}
							})
							.done(function(data) {

								var results = $.trim(data);

								if (!results) {
									response([]);
									return;
								}

								var names = results.split(/\\r?\\n/);

								response(
									$.map(names, function(name) {

										name = $.trim(name);

										if (!name) {
											return null;
										}

										return {
											label: name,
											value: name
										};
									})
								);
							})
							.fail(function() {
								response([]);
							});
						},

						select: function(event, ui) {

							var value = $input.val() || "";
							var parts = value.split("&");

							/*
							 * Remove the text currently being typed.
							 */
							parts.pop();

							/*
							 * Clean previous values.
							 */
							parts = $.map(parts, function(part) {
								part = $.trim(part);
								return part ? part : null;
							});

							/*
							 * Add the selected TAG.
							 */
							parts.push(ui.item.value);

							/*
							 * Keep the separator ready for another value.
							 */
							$input.val(parts.join(" & ") + " & ");

							return false;
						}
					});

				});

			});

		})(jQuery);
	';

		wp_add_inline_script(
			'jquery-ui-autocomplete',
			$script
		);
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

			.bfm-figure-meta-field .description {
				margin-top: 5px;
			}

			.bfm-figure-tag-autocomplete {
				position: relative;
			}

			.ui-autocomplete {
				z-index: 100000 !important;
				max-height: 240px;
				overflow-y: auto;
				overflow-x: hidden;
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
