/**
 * Bunny Figure Metadata — "Entidades destacadas" block (editor)
 *
 * Dynamic/server-side block: edit() only renders Inspector controls plus
 * a live preview via <ServerSideRender>, which calls the same PHP
 * render callback (BFM_Featured_Entities_Block::render()) used on the
 * front end. save() returns null — no block HTML is ever stored in
 * post_content.
 *
 * Written as plain ES5-ish JS against the wp.* globals (no JSX, no build
 * step), consistent with the rest of this plugin's admin scripts.
 *
 * Expects a localized `BFM_FEATURED_ENTITIES_BLOCK` object with the
 * available entities ({ value, label }), sourced from
 * BFM_Meta_Fields::entities() — the single source of truth already used
 * everywhere else in the plugin.
 *
 * @package BunnyFigureMetadata
 */

( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	if ( typeof BFM_FEATURED_ENTITIES_BLOCK === 'undefined' ) {
		return;
	}

	var el = element.createElement;
	var Fragment = element.Fragment;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var __ = i18n.__;

	var TITLE_TAG_OPTIONS = [
		{ label: 'H2', value: 'h2' },
		{ label: 'H3', value: 'h3' },
		{ label: 'H4', value: 'h4' },
		{ label: 'H5', value: 'h5' },
		{ label: 'H6', value: 'h6' }
	];

	var ORDER_OPTIONS = [
		{ label: __( 'Posts ↓ (más primero)', 'bunny-figure-metadata' ), value: 'posts_desc' },
		{ label: __( 'Posts ↑ (menos primero)', 'bunny-figure-metadata' ), value: 'posts_asc' },
		{ label: __( 'Nombre A-Z', 'bunny-figure-metadata' ), value: 'name_asc' },
		{ label: __( 'Nombre Z-A', 'bunny-figure-metadata' ), value: 'name_desc' },
		{ label: __( 'Aleatorio', 'bunny-figure-metadata' ), value: 'random' }
	];

	var ENTITY_OPTIONS = BFM_FEATURED_ENTITIES_BLOCK.entities.map( function ( entity ) {
		return { label: entity.label, value: entity.value };
	} );

	registerBlockType( 'bunny-figure-metadata/featured-entities', {

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Contenido', 'bunny-figure-metadata' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Metadata', 'bunny-figure-metadata' ),
						value: attributes.metaKey,
						options: ENTITY_OPTIONS,
						onChange: function ( value ) { setAttributes( { metaKey: value } ); }
					} ),
					el( RangeControl, {
						label: __( 'Cantidad a mostrar', 'bunny-figure-metadata' ),
						value: attributes.count,
						onChange: function ( value ) { setAttributes( { count: value } ); },
						min: 1,
						max: 50
					} ),
					el( SelectControl, {
						label: __( 'Orden', 'bunny-figure-metadata' ),
						value: attributes.orderBy,
						options: ORDER_OPTIONS,
						onChange: function ( value ) { setAttributes( { orderBy: value } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Visualización', 'bunny-figure-metadata' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Mostrar logo', 'bunny-figure-metadata' ),
						checked: attributes.showLogo,
						onChange: function ( value ) { setAttributes( { showLogo: value } ); }
					} ),
					el( RangeControl, {
						label: __( 'Tamaño del logo (px)', 'bunny-figure-metadata' ),
						help: __( 'El logo siempre se muestra en proporción 1:1 (ancho = alto).', 'bunny-figure-metadata' ),
						value: attributes.logoSize,
						onChange: function ( value ) { setAttributes( { logoSize: value } ); },
						min: 12,
						max: 96,
						disabled: ! attributes.showLogo
					} ),
					el( ToggleControl, {
						label: __( 'Mostrar cantidad de posts', 'bunny-figure-metadata' ),
						checked: attributes.showCount,
						onChange: function ( value ) { setAttributes( { showCount: value } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Enlazar al TAG asociado', 'bunny-figure-metadata' ),
						checked: attributes.linkToTag,
						onChange: function ( value ) { setAttributes( { linkToTag: value } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Título', 'bunny-figure-metadata' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Título personalizado', 'bunny-figure-metadata' ),
						help: __( 'Déjalo vacío para usar el título por defecto (por ejemplo, "Fabricantes destacados").', 'bunny-figure-metadata' ),
						value: attributes.title,
						onChange: function ( value ) { setAttributes( { title: value } ); }
					} ),
					el( SelectControl, {
						label: __( 'Elemento HTML del título', 'bunny-figure-metadata' ),
						value: attributes.titleTag,
						options: TITLE_TAG_OPTIONS,
						onChange: function ( value ) { setAttributes( { titleTag: value } ); }
					} ),
					el( TextControl, {
						label: __( 'Clase CSS del título', 'bunny-figure-metadata' ),
						value: attributes.titleClassName,
						onChange: function ( value ) { setAttributes( { titleClassName: value } ); }
					} )
				)
			);

			var blockProps = useBlockProps();

			return el(
				Fragment,
				{},
				inspector,
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'bunny-figure-metadata/featured-entities',
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
