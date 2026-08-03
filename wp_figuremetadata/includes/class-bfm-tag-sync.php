<?php
/**
 * BFM_Tag_Sync
 *
 * Synchronizes metadata-derived tags.
 *
 * Only tags generated from the configured tag-source metadata fields
 * are managed here. Manually assigned tags remain untouched.
 *
 * Migrated from the bunny-figure-metadata-editor.php production snippet
 * without changing behavior.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Tag_Sync {

	/**
	 * Post meta key used to remember which tag term IDs are currently
	 * managed (auto-generated) by this synchronization, as opposed to
	 * tags the user assigned manually.
	 */
	const MANAGED_TAG_IDS_META_KEY = '_bunny_figure_managed_tag_ids';

	/**
	 * Register all hooks owned by this class.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'save_post_post', array( $this, 'sync_tags' ), 20 );
	}

	/**
	 * Synchronize metadata-derived tags for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_tags( int $post_id ): void {

		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$desired_names = array();

		foreach ( BFM_Meta_Fields::tag_sources() as $meta_key ) {

			$value = get_post_meta(
				$post_id,
				$meta_key,
				true
			);

			if ( '' === $value ) {
				continue;
			}

			$values = explode( BFM_Meta_Fields::value_separator(), $value );

			foreach ( $values as $name ) {

				$name = trim( $name );

				if ( '' === $name ) {
					continue;
				}

				/*
				 * The configured excluded value is valid metadata but
				 * must never become a TAG.
				 */
				if ( BFM_Meta_Fields::excluded_value() === strtolower( $name ) ) {
					continue;
				}

				$desired_names[] = $name;
			}
		}

		/*
		 * Remove duplicates without changing the original order.
		 */
		$desired_names = array_values(
			array_unique(
				$desired_names
			)
		);

		/*
		 * Current tags.
		 */
		$current_terms = wp_get_post_terms(
			$post_id,
			'post_tag',
			array(
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $current_terms ) ) {
			return;
		}

		/*
		 * We need to know which tags were previously generated
		 * by this metadata system.
		 */
		$previous_ids = get_post_meta(
			$post_id,
			self::MANAGED_TAG_IDS_META_KEY,
			true
		);

		if ( ! is_array( $previous_ids ) ) {
			$previous_ids = array();
		}

		$previous_ids = array_map(
			'absint',
			$previous_ids
		);

		/*
		 * Resolve desired names to term IDs.
		 */
		$desired_ids = array();

		foreach ( $desired_names as $name ) {

			$term = term_exists(
				$name,
				'post_tag'
			);

			if ( ! $term ) {

				$term = wp_insert_term(
					$name,
					'post_tag'
				);
			}

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$term_id = is_array( $term )
				? absint( $term['term_id'] )
				: absint( $term );

			if ( $term_id ) {
				$desired_ids[] = $term_id;
			}
		}

		/*
		 * Remove only previously managed tags that are no longer
		 * present in metadata.
		 */
		foreach ( $previous_ids as $term_id ) {

			if ( ! in_array( $term_id, $desired_ids, true ) ) {

				wp_remove_object_terms(
					$post_id,
					$term_id,
					'post_tag'
				);
			}
		}

		/*
		 * Add desired managed tags without replacing manually
		 * assigned tags.
		 */
		if ( ! empty( $desired_ids ) ) {

			wp_set_post_terms(
				$post_id,
				$desired_ids,
				'post_tag',
				true
			);
		}

		/*
		 * Remember which tags are managed by this metadata system.
		 */
		update_post_meta(
			$post_id,
			self::MANAGED_TAG_IDS_META_KEY,
			array_values(
				array_unique(
					$desired_ids
				)
			)
		);
	}
}
