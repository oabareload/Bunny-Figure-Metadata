<?php
/**
 * Shared admin view partial — entity value list.
 *
 * Renders the header/tabs plus a table of unique metadata values and
 * their post counts. Included by each entity view file (manufacturers.php,
 * illustrators.php, series.php, characters.php) so the markup exists in
 * exactly one place.
 *
 * Variables available (set by BFM_Admin::render_entity_page()):
 *   string               $slug   Entity slug.
 *   array<string,string> $entity Entity definition (label, meta_key).
 *   array<string,int>    $values Unique values found in metadata => post count.
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
							<th><?php echo esc_html( $entity['label'] ); ?></th>
							<th style="width:15%;text-align:right;"><?php esc_html_e( 'Posts', 'bunny-figure-metadata' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $values as $name => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $name ); ?></td>
								<td style="text-align:right;font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>

</div>
