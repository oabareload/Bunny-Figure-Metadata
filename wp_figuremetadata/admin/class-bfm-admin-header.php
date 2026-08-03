<?php
/**
 * BFM_Admin_Header
 *
 * Renders the shared Bunny admin chrome (header + tabs) using the same
 * bunny-* markup and CSS classes as Bunny Reactions and Bunny Magazine,
 * so Bunny Figure Metadata is visually consistent with the family
 * without depending on those plugins.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Admin_Header {

	/**
	 * Render the header block and tab navigation.
	 *
	 * @param string $current_slug Slug of the currently active admin page.
	 * @param string $current_label Label of the currently active page, shown as subtitle.
	 * @return void
	 */
	public static function render( string $current_slug, string $current_label ): void {
		?>
		<div class="bunny-header">
			<div class="bunny-header-inner">
				<span class="bunny-logo">🐰</span>
				<div class="bunny-title-stack">
					<h1 class="bunny-plugin-name"><?php esc_html_e( 'Bunny Figure Metadata', 'bunny-figure-metadata' ); ?></h1>
					<span class="bunny-page-subtitle"><?php echo esc_html( $current_label ); ?></span>
				</div>
				<span class="bunny-version-badge">v<?php echo esc_html( BFM_VERSION ); ?></span>
			</div>
			<nav class="bunny-nav">
				<?php foreach ( BFM_Meta_Fields::entities() as $slug => $entity ) : ?>
					<a
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . BFM_Admin::page_slug( $slug ) ) ); ?>"
						class="bunny-nav-item<?php echo $slug === $current_slug ? ' bunny-nav-active' : ''; ?>"
					>
						<?php echo esc_html( $entity['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
}
