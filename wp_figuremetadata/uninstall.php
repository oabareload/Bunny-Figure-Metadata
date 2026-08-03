<?php
/**
 * Uninstall handler.
 *
 * This plugin does not create custom database tables, options, or
 * transients of its own, so there is nothing to clean up beyond what
 * WordPress already removes automatically (the plugin files themselves).
 *
 * Post meta (_bunny_figure_*) and post tags created through this plugin
 * are left untouched on uninstall: they are the site's structured figure
 * data, not plugin configuration, and removing them would destroy content.
 *
 * @package BunnyFigureMetadata
 */

// Prevent direct file access; only run when WordPress is actually uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
