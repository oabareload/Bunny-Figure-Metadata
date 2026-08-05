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
 *   array<int,string>    $logo_previews Attachment id => thumbnail URL.
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
							<th style="width:20%;"><?php esc_html_e( 'TAG asociado', 'bunny-figure-metadata' ); ?></th>
							<th style="width:22%;"><?php esc_html_e( 'URL', 'bunny-figure-metadata' ); ?></th>
							<th style="width:14%;"><?php esc_html_e( 'Logo', 'bunny-figure-metadata' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $values as $name => $count ) : ?>
							<?php
							$term_id  = absint( $tag_map[ $name ] ?? 0 );
							$tag_name = ( $term_id && isset( $tag_names[ $term_id ] ) ) ? $tag_names[ $term_id ] : '';

							$info     = $info_map[ $name ] ?? array(
								'url'      => '',
								'image_id' => 0,
							);
							$image_id = absint( $info['image_id'] );
							$logo_url = ( $image_id && isset( $logo_previews[ $image_id ] ) ) ? $logo_previews[ $image_id ] : '';
							?>
							<tr>
								<td><?php echo esc_html( $name ); ?></td>
								<td style="text-align:right;font-variant-numeric:tabular-nums;">
									<a
										class="bfm-posts-count-link"
										href="<?php echo esc_url( add_query_arg( array( 'page' => BFM_Admin::page_slug( $slug ), 'value' => $name ), admin_url( 'admin.php' ) ) ); ?>"
									>
										<?php echo esc_html( number_format_i18n( $count ) ); ?>
									</a>
								</td>
								<td>
									<span
										class="bfm-tag-cell"
										data-entity-slug="<?php echo esc_attr( $slug ); ?>"
										data-value="<?php echo esc_attr( $name ); ?>"
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
								</td>
								<td>
									<span
										class="bfm-url-cell"
										data-entity-slug="<?php echo esc_attr( $slug ); ?>"
										data-value="<?php echo esc_attr( $name ); ?>"
										data-saved-value="<?php echo esc_attr( $info['url'] ); ?>"
									>
										<input
											type="url"
											class="bfm-url-input"
											value="<?php echo esc_attr( $info['url'] ); ?>"
											placeholder="https://"
										>
									</span>
								</td>
								<td>
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
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>

</div>
