<?php
/**
 * BFM_Core
 *
 * Plugin bootstrap singleton. Loads dependencies and registers hooks.
 *
 * Responsibilities:
 * - Load all class files (no autoloader, to keep dependencies at zero).
 * - Register action/filter hooks.
 * - Enqueue admin assets only on this plugin's own screens.
 *
 * What it does NOT do:
 * - Business logic (that lives in the feature classes).
 * - Direct database queries (that's BFM_Entity_Repository).
 * - Admin UI rendering (that's BFM_Admin and the view partials).
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BFM_Core {

	// ─────────────────────────────────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────────────────────────────────

	/** @var BFM_Core|null */
	private static ?BFM_Core $instance = null;

	/**
	 * Returns the single instance, creating it on first call.
	 *
	 * @return BFM_Core
	 */
	public static function instance(): BFM_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use ::instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	// No cloning or unserialization.
	private function __clone() {}
	public function __wakeup(): void {
		_doing_it_wrong( __METHOD__, 'Unserializing BFM_Core is not allowed.', BFM_VERSION );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Dependencies
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Require all class files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {

		require_once BFM_PATH . 'includes/class-bfm-meta-fields.php';
		require_once BFM_PATH . 'includes/class-bfm-post-editor.php';
		require_once BFM_PATH . 'includes/class-bfm-tag-sync.php';
		require_once BFM_PATH . 'includes/class-bfm-entity-repository.php';
		require_once BFM_PATH . 'includes/class-bfm-entity-tag-map.php';
		require_once BFM_PATH . 'includes/class-bfm-entity-info-map.php';

		// Admin (and its AJAX endpoints) is only needed in wp-admin, to avoid
		// loading it on the front end. is_admin() is also true during
		// admin-ajax.php requests, so this correctly covers both cases.
		if ( is_admin() ) {
			require_once BFM_PATH . 'admin/class-bfm-admin-header.php';
			require_once BFM_PATH . 'admin/class-bfm-admin.php';
			require_once BFM_PATH . 'admin/class-bfm-admin-ajax.php';
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Hooks
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Register all hooks for the plugin.
	 *
	 * @return void
	 */
	private function register_hooks(): void {

		// ── Post editor metadata (metabox, register_post_meta, save) ──────────
		if ( class_exists( 'BFM_Post_Editor' ) ) {
			$post_editor = new BFM_Post_Editor();
			$post_editor->register_hooks();
		}

		// ── Metadata → tag synchronization ─────────────────────────────────────
		if ( class_exists( 'BFM_Tag_Sync' ) ) {
			$tag_sync = new BFM_Tag_Sync();
			$tag_sync->register_hooks();
		}

		// ── Admin ─────────────────────────────────────────────────────────────
		if ( is_admin() && class_exists( 'BFM_Admin' ) ) {
			$admin = new BFM_Admin();
			add_action( 'admin_menu', [ $admin, 'register_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_assets' ] );
		}

		// ── Admin AJAX (entity → TAG association) ─────────────────────────────
		if ( is_admin() && class_exists( 'BFM_Admin_Ajax' ) ) {
			$admin_ajax = new BFM_Admin_Ajax();
			$admin_ajax->register_hooks();
		}

		// ── Plugin action links (Manufacturers link in plugin list) ───────────
		add_filter(
			'plugin_action_links_' . BFM_BASENAME,
			[ $this, 'plugin_action_links' ]
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Plugin list links
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Add a settings/entities link to the plugin list table row.
	 *
	 * @param  array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . BFM_Admin::MENU_SLUG ) ),
			esc_html__( 'Fabricantes', 'bunny-figure-metadata' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
