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
	var RangeControl = components.RangeControl;
	var __ = i18n.__;

	var LAYOUT_OPTIONS = [
		{ label: __( 'Clásica', 'bunny-figure-metadata' ), value: 'classic' },
		{ label: __( 'Tarjetas', 'bunny-figure-metadata' ), value: 'cards' }
	];

	// Keep in sync with BFM_Features_Block::*_CARD_MIN_WIDTH (the server re-validates anyway).
	var CARD_MIN_WIDTH_DEFAULT = 140;
	var CARD_MIN_WIDTH_MIN = 100;
	var CARD_MIN_WIDTH_MAX = 250;

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

			var isCards = 'cards' === attributes.layout;

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Presentación', 'bunny-figure-metadata' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Presentación', 'bunny-figure-metadata' ),
						value: isCards ? 'cards' : 'classic',
						options: LAYOUT_OPTIONS,
						onChange: function ( value ) { setAttributes( { layout: value } ); }
					} ),
					isCards ? el( RangeControl, {
						label: __( 'Ancho de tarjeta (px)', 'bunny-figure-metadata' ),
						help: __( 'Ancho de referencia: las tarjetas se ajustan al espacio disponible.', 'bunny-figure-metadata' ),
						value: attributes.cardMinWidth || CARD_MIN_WIDTH_DEFAULT,
						min: CARD_MIN_WIDTH_MIN,
						max: CARD_MIN_WIDTH_MAX,
						step: 5,
						initialPosition: CARD_MIN_WIDTH_DEFAULT,
						allowReset: true,
						resetFallbackValue: CARD_MIN_WIDTH_DEFAULT,
						onChange: function ( value ) {
							setAttributes( { cardMinWidth: undefined === value ? CARD_MIN_WIDTH_DEFAULT : value } );
						}
					} ) : null
				),
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
