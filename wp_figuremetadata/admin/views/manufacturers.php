<?php
/**
 * Admin view — Fabricantes.
 *
 * Variables available (set by BFM_Admin::render_entity_page()):
 *   string               $slug   Entity slug ('manufacturers').
 *   array<string,string> $entity Entity definition (label, meta_key).
 *   array<string,int>    $values Unique values found in metadata => post count.
 *
 * @package BunnyFigureMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require BFM_PATH . 'admin/views/partial-entity-list.php';
