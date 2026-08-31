<?php
/**
 * Shared admin view partial — entity value list.
 *
 * Renders the header/tabs plus a sortable table of unique metadata
 * values, their post counts, and their associated TAG (if any).
 * Included by each entity view file (manufacturers.php, illustrators.php,
 * series.php, characters.php) so the markup exists in exactly one place.
 *
 * Variables available (set by BFM_Admin::render_entity_page()):
 *   string               $slug          Entity slug.
 *   array<string,string> $entity        Entity definition (label, meta_key).
 *   array<string,int>    $values        Unique values found in metadata => post count (already sorted).
 *   string                $orderby       Current sort column ('name'|'posts').
 *   string                $order         Current sort direction ('asc'|'desc').
 *   array<string,int>    $tag_map       Value name => associated term_id (0/absent = none).
 *   array<int,string>    $tag_names     Term_id => current tag name.
 *   array<string,array{url:string,image_id:int}> $info_map      Value name => complementary info (URL + logo attachment id).
 *                        For character, image_id is stored/looked up by the composite identity
 *                        key (BFM_Character_Identity) while url stays keyed by the plain character
 *                        name; every other entity uses the plain name for both.
 *   array<int,string>    $logo_previews Attachment id => thumbnail URL.
 *   array<string,int>    $effective_image_ids Value name => image id actually displayed (manual override, or the
 *                        auto-resolved related-post image when $entity['image_source'] is 'related').
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bfm_sortable_column_header' ) ) :
	/**
	 * Build a sortable column header link with a direction indicator.
	 *
	 * @param string $column          'name' or 'posts'.
	 * @param string $label           Visible column label.
	 * @param string $slug            Entity slug (for the page URL).
	 * @param string $current_orderby Current sort column.
	 * @param string $current_order   Current sort direction.
	 * @return string
	 */
	function bfm_sortable_column_header( string $column, string $label, string $slug, string $current_orderby, string $current_order ): string {

		$is_current = ( $column === $current_orderby );
		$next_order = ( $is_current && 'asc' === $current_order ) ? 'desc' : 'asc';
		$indicator  = '';

		if ( $is_current ) {
			$indicator = 'asc' === $current_order ? ' ▲' : ' ▼';
		}

		$url = add_query_arg(
			array(
				'page'    => BFM_Admin::page_slug( $slug ),
				'orderby' => $column,
				'order'   => $next_order,
			),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<a href="%s" class="bfm-sort-link%s">%s%s</a>',
			esc_url( $url ),
			$is_current ? ' bfm-sort-active' : '',
			esc_html( $label ),
			esc_html( $indicator )
		);
	}
endif;
?>
<div class="wrap bunny-wrap bfm-admin-wrap">

	<?php BFM_Admin_Header::render( $slug, $entity['label'] ); ?>

	<div class="bunny-page-content">

		<div class="bfm-box">
			<h2 class="bfm-box-title">
				<?php echo esc_html( $entity['label'] ); ?>
				<span class="bfm-box-subtitle">
					<?php
					printf(
						/* translators: %s: number of unique values found. */
						esc_html__( '%s valores encontrados en la metadata', 'bunny-figure-metadata' ),
						esc_html( number_format_i18n( count( $values ) ) )
					);
					?>
				</span>
			</h2>

			<?php if ( empty( $values ) ) : ?>
				<p><?php esc_html_e( 'No se encontraron valores para esta entidad en la metadata de los posts.', 'bunny-figure-metadata' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo wp_kses_post( bfm_sortable_column_header( 'name', $entity['label'], $slug, $orderby, $order ) ); ?></th>
							<th style="width:10%;text-align:right;"><?php echo wp_kses_post( bfm_sortable_column_header( 'posts', __( 'Posts', 'bunny-figure-metadata' ), $slug, $orderby, $order ) ); ?></th>
							<?php if ( ! empty( $entity['tag'] ) ) : ?><th style="width:20%;"><?php esc_html_e( 'TAG asociado', 'bunny-figure-metadata' ); ?></th><?php endif; ?>
							<?php if ( ! empty( $entity['url'] ) ) : ?><th style="width:22%;"><?php esc_html_e( 'URL', 'bunny-figure-metadata' ); ?></th><?php endif; ?>
							<?php if ( ! empty( $entity['logo'] ) ) : ?><th style="width:14%;"><?php esc_html_e( 'Logo', 'bunny-figure-metadata' ); ?></th><?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $values as $name => $count ) : ?>
							<?php
							// For character, $name is the composite identity key (BFM_Character_Identity);
							// $display_name is the plain character name the user should always see, and
							// is what TAG/URL (unaffected by this feature) are keyed by. For every other
							// entity, both are identical.
							$display_name = ( BFM_Character_Identity::CHARACTER_META_KEY === $entity['meta_key'] )
								? BFM_Character_Identity::display_name( $name )
								: $name;

							$term_id  = absint( $tag_map[ $display_name ] ?? 0 );
							$tag_name = ( $term_id && isset( $tag_names[ $term_id ] ) ) ? $tag_names[ $term_id ] : '';

							$url_value = (string) ( $info_map[ $display_name ]['url'] ?? '' );

							$info     = $info_map[ $name ] ?? array(
								'url'      => '',
								'image_id' => 0,
							);
							$image_id = absint( $info['image_id'] );
							$logo_url = ( $image_id && isset( $logo_previews[ $image_id ] ) ) ? $logo_previews[ $image_id ] : '';
							?>
							<tr>
								<td><?php echo esc_html( $display_name ); ?></td>
								<td style="text-align:right;font-variant-numeric:tabular-nums;">
									<a
										class="bfm-posts-count-link"
										href="<?php echo esc_url( add_query_arg( array( 'page' => BFM_Admin::page_slug( $slug ), 'value' => $name ), admin_url( 'admin.php' ) ) ); ?>"
									>
										<?php echo esc_html( number_format_i18n( $count ) ); ?>
									</a>
								</td>
								<?php if ( ! empty( $entity['tag'] ) ) : ?><td>
									<span
										class="bfm-tag-cell"
										data-entity-slug="<?php echo esc_attr( $slug ); ?>"
										data-value="<?php echo esc_attr( $display_name ); ?>"
									>
										<?php if ( '' !== $tag_name ) : ?>
											<span class="bfm-tag-chip" data-term-id="<?php echo esc_attr( $term_id ); ?>">
												<span class="bfm-tag-chip-name"><?php echo esc_html( $tag_name ); ?></span>
												<button type="button" class="bfm-tag-remove" aria-label="<?php esc_attr_e( 'Quitar TAG', 'bunny-figure-metadata' ); ?>">&times;</button>
											</span>
										<?php else : ?>
											<button type="button" class="button-link bfm-tag-select-trigger"><?php esc_html_e( 'Seleccionar TAG...', 'bunny-figure-metadata' ); ?></button>
										<?php endif; ?>
									</span>
								</td><?php endif; ?>
								<?php if ( ! empty( $entity['url'] ) ) : ?><td>
									<span
										class="bfm-url-cell"
										data-entity-slug="<?php echo esc_attr( $slug ); ?>"
										data-value="<?php echo esc_attr( $display_name ); ?>"
										data-saved-value="<?php echo esc_attr( $url_value ); ?>"
									>
										<input
											type="url"
											class="bfm-url-input"
											value="<?php echo esc_attr( $url_value ); ?>"
											placeholder="https://"
										>
									</span>
								</td><?php endif; ?>
								<?php if ( ! empty( $entity['logo'] ) ) : ?><td>
									<?php if ( 'related' === ( $entity['image_source'] ?? 'custom' ) ) : ?>
										<?php
										$effective_id   = absint( $effective_image_ids[ $name ] ?? 0 );
										$is_override    = $image_id > 0;
										$effective_html = $effective_id ? BFM_Entity_Info_Map::get_display_image_html( $effective_id, array( 'alt' => '' ) ) : '';
										?>
										<span
											class="bfm-logo-cell bfm-related-image-cell"
											data-entity-slug="<?php echo esc_attr( $slug ); ?>"
											data-value="<?php echo esc_attr( $name ); ?>"
											data-display-name="<?php echo esc_attr( $display_name ); ?>"
										>
											<?php if ( '' !== $effective_html ) : ?>
												<span class="bfm-logo-preview">
													<?php echo wp_kses_post( $effective_html ); ?>
													<span class="bfm-logo-actions">
														<button type="button" class="button-link bfm-related-image-replace"><?php esc_html_e( 'Cambiar', 'bunny-figure-metadata' ); ?></button>
														<?php if ( $is_override ) : ?>
															<button type="button" class="button-link bfm-related-image-reset"><?php esc_html_e( 'Usar automática', 'bunny-figure-metadata' ); ?></button>
														<?php else : ?>
															<span class="bfm-related-image-auto-label"><?php esc_html_e( 'Automática', 'bunny-figure-metadata' ); ?></span>
														<?php endif; ?>
													</span>
												</span>
											<?php else : ?>
												<button type="button" class="button bfm-related-image-replace"><?php esc_html_e( 'Elegir imagen', 'bunny-figure-metadata' ); ?></button>
											<?php endif; ?>
										</span>
									<?php else : ?>
										<span
											class="bfm-logo-cell"
											data-entity-slug="<?php echo esc_attr( $slug ); ?>"
											data-value="<?php echo esc_attr( $name ); ?>"
										>
											<?php if ( $image_id && '' !== $logo_url ) : ?>
												<span class="bfm-logo-preview">
													<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
													<span class="bfm-logo-actions">
														<button type="button" class="button-link bfm-logo-replace"><?php esc_html_e( 'Reemplazar', 'bunny-figure-metadata' ); ?></button>
														<button type="button" class="button-link bfm-logo-remove"><?php esc_html_e( 'Eliminar', 'bunny-figure-metadata' ); ?></button>
													</span>
												</span>
											<?php else : ?>
												<button type="button" class="button bfm-logo-select"><?php esc_html_e( 'Subir imagen', 'bunny-figure-metadata' ); ?></button>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</td><?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>

</div>
