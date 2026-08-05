<?php
/**
 * Generic admin view — posts associated with a single entity value.
 *
 * Reached by clicking a "Posts" count on any of the four entity list
 * screens. Generic across all four entities: only the entity definition
 * and the value being filtered are used, no per-entity branching.
 *
 * Variables available (set by BFM_Admin::render_entity_posts_page()):
 *   string                $slug        Entity slug.
 *   array<string,string>  $entity      Entity definition (label, meta_key).
 *   string                $value       Exact entity value being filtered by.
 *   array<int,array>      $rows        Display-ready post rows (see BFM_Admin::build_post_row()).
 *   int                   $total       Total matching posts.
 *   int                   $total_pages Total number of pages.
 *   int                   $paged       Current page number.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bunny-wrap bfm-admin-wrap">

	<?php BFM_Admin_Header::render( $slug, $entity['label'] ); ?>

	<div class="bunny-page-content">

		<p class="bfm-back-link">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . BFM_Admin::page_slug( $slug ) ) ); ?>">
				&larr; <?php echo esc_html( sprintf( /* translators: %s: entity label, e.g. "Fabricantes" */ __( 'Volver a %s', 'bunny-figure-metadata' ), $entity['label'] ) ); ?>
			</a>
		</p>

		<div class="bfm-box">
			<h2 class="bfm-box-title">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: entity label, e.g. "Fabricante". 2: entity value, e.g. "Good Smile Company". */
						__( '%1$s: %2$s', 'bunny-figure-metadata' ),
						$entity['label'],
						$value
					)
				);
				?>
				<span class="bfm-box-subtitle">
					<?php
					printf(
						/* translators: %s: number of matching posts. */
						esc_html__( '%s posts encontrados', 'bunny-figure-metadata' ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</span>
			</h2>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No se encontraron posts para este valor.', 'bunny-figure-metadata' ); ?></p>
			<?php else : ?>
				<table class="widefat striped bfm-posts-table">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e( 'Miniatura', 'bunny-figure-metadata' ); ?></th>
							<th><?php esc_html_e( 'Título', 'bunny-figure-metadata' ); ?></th>
							<th><?php esc_html_e( 'Categoría', 'bunny-figure-metadata' ); ?></th>
							<th><?php esc_html_e( 'Etiquetas', 'bunny-figure-metadata' ); ?></th>
							<th><?php esc_html_e( 'Publicado', 'bunny-figure-metadata' ); ?></th>
							<th><?php esc_html_e( 'Modificado', 'bunny-figure-metadata' ); ?></th>
							<th style="width:130px;"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td>
									<?php if ( '' !== $row['thumbnail'] ) : ?>
										<img class="bfm-post-thumb" src="<?php echo esc_url( $row['thumbnail'] ); ?>" alt="">
									<?php else : ?>
										<span class="bfm-post-thumb bfm-post-thumb-placeholder" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['title'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', $row['categories'] ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', $row['tags'] ) ); ?></td>
								<td><?php echo esc_html( $row['date'] ); ?></td>
								<td><?php echo esc_html( $row['modified'] ); ?></td>
								<td class="bfm-posts-actions">
									<?php if ( '' !== $row['edit_url'] ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php esc_html_e( 'Editar', 'bunny-figure-metadata' ); ?></a>
									<?php endif; ?>
									<a class="button button-small" href="<?php echo esc_url( $row['view_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver', 'bunny-figure-metadata' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="bfm-pagination">
						<?php if ( $paged > 1 ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => BFM_Admin::page_slug( $slug ), 'value' => $value, 'paged' => $paged - 1 ), admin_url( 'admin.php' ) ) ); ?>">
								&laquo; <?php esc_html_e( 'Anterior', 'bunny-figure-metadata' ); ?>
							</a>
						<?php endif; ?>

						<span class="bfm-pagination-status">
							<?php
							printf(
								/* translators: 1: current page. 2: total pages. */
								esc_html__( 'Página %1$s de %2$s', 'bunny-figure-metadata' ),
								esc_html( number_format_i18n( $paged ) ),
								esc_html( number_format_i18n( $total_pages ) )
							);
							?>
						</span>

						<?php if ( $paged < $total_pages ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => BFM_Admin::page_slug( $slug ), 'value' => $value, 'paged' => $paged + 1 ), admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Siguiente', 'bunny-figure-metadata' ); ?> &raquo;
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	</div>

</div>
