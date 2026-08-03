<?php
/**
 * BFM_Admin
 *
 * Registers the plugin admin menu (one top-level page plus one submenu
 * per administrable entity) and enqueues admin assets only on this
 * plugin's own screens.
 *
 * Page slugs: bfm-manufacturers, bfm-illustrators, bfm-series, bfm-characters.
 * The first entity's page also serves as the top-level menu page, the
 * same pattern used by Bunny Magazine's dashboard/submenu structure.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Admin {

	/**
	 * Prefix used to build each entity's admin page slug.
	 */
	const PAGE_PREFIX = 'bfm-';

	/**
	 * Page slug of the top-level menu entry (first entity: manufacturers).
	 */
	const MENU_SLUG = self::PAGE_PREFIX . 'manufacturers';

	/**
	 * Build the admin page slug for a given entity slug.
	 *
	 * @param string $entity_slug Entity slug (manufacturers, illustrators, series, characters).
	 * @return string
	 */
	public static function page_slug( string $entity_slug ): string {
		return self::PAGE_PREFIX . $entity_slug;
	}

	/**
	 * Register the top-level menu page and one submenu page per entity.
	 *
	 * @return void
	 */
	public function register_menu(): void {

		$entities = BFM_Meta_Fields::entities();

		$first_slug  = array_key_first( $entities );
		$first_label = $entities[ $first_slug ]['label'];

		add_menu_page(
			__( 'Bunny Figure Metadata', 'bunny-figure-metadata' ),
			__( 'Figure Metadata', 'bunny-figure-metadata' ),
			'manage_options',
			self::page_slug( $first_slug ),
			$this->render_callback_for( $first_slug ),
			'dashicons-tag',
			57
		);

		foreach ( $entities as $slug => $entity ) {
			add_submenu_page(
				self::page_slug( $first_slug ),
				$entity['label'],
				$entity['label'],
				'manage_options',
				self::page_slug( $slug ),
				$this->render_callback_for( $slug )
			);
		}
	}

	/**
	 * Build a render callback bound to a specific entity slug.
	 *
	 * @param string $slug Entity slug.
	 * @return callable
	 */
	private function render_callback_for( string $slug ): callable {
		return function () use ( $slug ) {
			$this->render_entity_page( $slug );
		};
	}

	/**
	 * Render an entity admin page: capability check, fetch values via
	 * the repository (single aggregated query), include the view.
	 *
	 * @param string $slug Entity slug.
	 * @return void
	 */
	private function render_entity_page( string $slug ): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bunny-figure-metadata' ) );
		}

		$entities = BFM_Meta_Fields::entities();

		if ( ! isset( $entities[ $slug ] ) ) {
			wp_die( esc_html__( 'Unknown entity.', 'bunny-figure-metadata' ) );
		}

		$entity = $entities[ $slug ];
		$values = BFM_Entity_Repository::get_values_with_counts( $entity['meta_key'] );

		$view_file = BFM_PATH . 'admin/views/' . $slug . '.php';

		if ( ! file_exists( $view_file ) ) {
			wp_die( esc_html__( 'View not found.', 'bunny-figure-metadata' ) );
		}

		require $view_file;
	}

	/**
	 * Enqueue admin CSS only on this plugin's own screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {

		if ( false === strpos( $hook, self::PAGE_PREFIX ) ) {
			return;
		}

		wp_enqueue_style(
			'bfm-bunny-admin',
			BFM_URL . 'assets/css/bunny-admin.css',
			array(),
			BFM_VERSION
		);

		wp_enqueue_style(
			'bfm-admin',
			BFM_URL . 'assets/css/admin.css',
			array( 'bfm-bunny-admin' ),
			BFM_VERSION
		);
	}
}
