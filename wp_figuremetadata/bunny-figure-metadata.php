<?php
/**
 * Plugin Name:       Bunny Figure Metadata
 * Plugin URI:        https://bunnychase.net/bunny-figure-metadata
 * Description:       Administra la metadata estructurada de figuras (fabricante, ilustrador, serie, personaje y demás campos) y las entidades derivadas de esa metadata. Parte de la familia de plugins BunnyChase.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            BunnyChase
 * Author URI:        https://bunnychase.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bunny-figure-metadata
 * Domain Path:       /languages
 *
 * @package BunnyFigureMetadata
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

define( 'BFM_VERSION',  '1.0.0' );
define( 'BFM_PATH',     plugin_dir_path( __FILE__ ) );
define( 'BFM_URL',      plugin_dir_url( __FILE__ ) );
define( 'BFM_BASENAME', plugin_basename( __FILE__ ) );
define( 'BFM_FILE',     __FILE__ );

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────

require_once BFM_PATH . 'includes/class-bfm-core.php';

/**
 * Returns the single instance of BFM_Core.
 *
 * Mirrors the bootstrap pattern used across the Bunny plugin family
 * (Bunny Reactions, Bunny Magazine).
 *
 * @return BFM_Core
 */
function BFM(): BFM_Core { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return BFM_Core::instance();
}

// Fire after all plugins are loaded so that hooks from other plugins are available.
add_action( 'plugins_loaded', 'BFM' );
