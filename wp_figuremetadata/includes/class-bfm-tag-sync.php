<?php
/**
 * BFM_Tag_Sync
 *
 * Synchronizes metadata-derived tags.
 *
 * Only tags generated from the configured tag-source metadata fields
 * are managed here. Manually assigned tags remain untouched.
 *
 * Sync only runs when the post's resulting status is 'publish' or
 * 'future' (scheduled) — never while a post is a draft, pending review,
 * or otherwise unpublished. This is a plain status check inside
 * sync_tags() rather than a dedicated transition_post_status hook,
 * specifically because transition_post_status fires *before* save_post
 * within wp_insert_post(), i.e. before this plugin's own metadata save
 * (BFM_Post_Editor, also hooked on save_post_post) has written the
 * freshly submitted chip values. Hooking on save_post_post (as before)
 * and simply checking the resulting status keeps sync reading the
 * metadata that was just saved in the same request, for both a normal
 * editor publish and WordPress's own scheduled-post auto-publish
 * (wp_publish_post() re-fires save_post_post as well), while never
 * touching tags for drafts.
 *
 * For each source value:
 *   1. An existing Metadata -> TAG association (BFM_Entity_Tag_Map) is
 *      used when present and it still points to a real post_tag term.
 *   2. Otherwise, a same-named tag is reused if one exists, or created
 *      if not — and either way, that resolved term_id is written back
 *      into BFM_Entity_Tag_Map, so future syncs (and the entity admin
 *      screens) see the association directly via step 1.
 *
 * This process is idempotent: re-running it (e.g. when a scheduled post
 * later auto-publishes) always converges to the same desired tag set.
 *
 * Migrated from the bunny-figure-metadata-editor.php production snippet
 * without changing its original (name-based) fallback behavior.
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

		/*
		 * Never create or associate tags for unpublished content. Sync only
		 * runs once the post's resulting status is publish or scheduled.
		 */
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$desired_names = array();

		/*
		 * Remember which meta key each name first came from, so we can
		 * look up its Metadata -> TAG association (BFM_Entity_Tag_Map is
		 * keyed by meta_key + value name) when resolving term IDs below.
		 */
		$name_sources = array();

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

				if ( ! isset( $name_sources[ $name ] ) ) {
					$name_sources[ $name ] = $meta_key;
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
		 *
		 * For each name, the Metadata -> TAG association configured on the
		 * entity admin screens (BFM_Entity_Tag_Map) is checked first. If an
		 * association exists and still points to a real post_tag term, it is
		 * used as-is. Otherwise, the existing behavior applies unchanged:
		 * reuse a same-named tag if one exists, or create one.
		 */
		$desired_ids = array();

		foreach ( $desired_names as $name ) {

			$term_id = 0;

			$source_meta_key = $name_sources[ $name ] ?? '';

			if ( '' !== $source_meta_key ) {

				$associated_id = BFM_Entity_Tag_Map::get( $source_meta_key, $name );

				if ( $associated_id > 0 ) {

					$associated_term = get_term( $associated_id, 'post_tag' );

					if ( $associated_term && ! is_wp_error( $associated_term ) ) {
						$term_id = absint( $associated_id );
					}
				}
			}

			if ( ! $term_id ) {

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

				/*
				 * Persist this newly resolved tag (found by name, or just
				 * created) as the entity's association, so future syncs and
				 * the entity admin screens reuse it directly via step 1/2
				 * above instead of re-resolving by name every time.
				 */
				if ( $term_id && '' !== $source_meta_key ) {
					BFM_Entity_Tag_Map::set( $source_meta_key, $name, $term_id );
				}
			}

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
