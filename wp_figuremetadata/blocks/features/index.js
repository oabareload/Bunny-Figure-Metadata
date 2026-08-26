/**
 * Bunny Figure Metadata — "Características" block (editor)
 *
 * Dynamic/server-side block: edit() only renders Inspector controls plus
 * a live preview via <ServerSideRender>, which calls the same PHP
 * render callback (BFM_Features_Block::render()) used on the front end.
 * save() returns null — no block HTML is ever stored in post_content.
 *
 * Written as plain ES5-ish JS against the wp.* globals (no JSX, no build
 * step), consistent with the rest of this plugin's admin scripts.
 *
 * Expects a localized `BFM_FEATURES_BLOCK` object with the 12 field
 * definitions (metaKey + label), sourced from BFM_Meta_Fields — the
 * single source of truth already used everywhere else in the plugin.
 *
 * @package BunnyFigureMetadata
 */

( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var __ = i18n.__;

	var HTML_TAG_OPTIONS = [
		{ label: 'H1', value: 'h1' },
		{ label: 'H2', value: 'h2' },
		{ label: 'H3', value: 'h3' },
		{ label: 'H4', value: 'h4' },
		{ label: 'H5', value: 'h5' },
		{ label: 'H6', value: 'h6' },
		{ label: 'p', value: 'p' },
		{ label: 'div', value: 'div' },
		{ label: 'span', value: 'span' },
		{ label: 'strong', value: 'strong' },
		{ label: 'em', value: 'em' }
	];

	registerBlockType( 'bunny-figure-metadata/features', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Encabezado', 'bunny-figure-metadata' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Mostrar encabezado', 'bunny-figure-metadata' ),
						checked: attributes.showHeading,
						onChange: function ( value ) { setAttributes( { showHeading: value } ); }
					} ),
					el( SelectControl, {
						label: __( 'Elemento HTML', 'bunny-figure-metadata' ),
						value: attributes.headingTag,
						options: HTML_TAG_OPTIONS,
						onChange: function ( value ) { setAttributes( { headingTag: value } ); }
					} ),
					el( TextControl, {
						label: __( 'Clase CSS adicional', 'bunny-figure-metadata' ),
						value: attributes.headingClassName,
						onChange: function ( value ) { setAttributes( { headingClassName: value } ); }
					} )
				)
			);

			return el(
				Fragment,
				{},
				inspector,
				el(
					'div',
					{ className: props.className },
					el( ServerSideRender, {
						block: 'bunny-figure-metadata/features',
						attributes: attributes
					} )
				)
			);
		},

		save: function () {
			return null;
		}
	} );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
