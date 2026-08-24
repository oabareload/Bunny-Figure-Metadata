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

	if ( typeof BFM_FEATURES_BLOCK === 'undefined' ) {
		return;
	}

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

	/**
	 * Resolve the effective (already-defaulted) settings for one field,
	 * mirroring BFM_Features_Block::effective_field_settings() on the
	 * PHP side, so the Inspector always shows what will actually render.
	 */
	function getFieldSettings( attributes, field ) {
		var features = attributes.features || {};
		var overrides = features[ field.metaKey ] || {};

		return {
			show: overrides.show !== undefined ? overrides.show : true,
			prefix: overrides.prefix !== undefined && '' !== overrides.prefix ? overrides.prefix : field.label + ':',
			prefixTag: overrides.prefixTag || 'span',
			prefixClass: overrides.prefixClass || '',
			valueTag: overrides.valueTag || 'span',
			valueClass: overrides.valueClass || ''
		};
	}

	function updateFieldSetting( attributes, setAttributes, metaKey, key, value ) {
		var features = Object.assign( {}, attributes.features || {} );
		var current = Object.assign( {}, features[ metaKey ] || {} );

		current[ key ] = value;
		features[ metaKey ] = current;

		setAttributes( { features: features } );
	}

	function renderFieldPanel( attributes, setAttributes, field ) {
		var settings = getFieldSettings( attributes, field );

		return el(
			PanelBody,
			{ title: field.label, initialOpen: false, key: field.metaKey },
			el( ToggleControl, {
				label: __( 'Mostrar', 'bunny-figure-metadata' ),
				checked: settings.show,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'show', value );
				}
			} ),
			el( TextControl, {
				label: __( 'Prefijo', 'bunny-figure-metadata' ),
				value: settings.prefix,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'prefix', value );
				}
			} ),
			el( SelectControl, {
				label: __( 'Elemento HTML del prefijo', 'bunny-figure-metadata' ),
				value: settings.prefixTag,
				options: HTML_TAG_OPTIONS,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'prefixTag', value );
				}
			} ),
			el( TextControl, {
				label: __( 'Clase CSS del prefijo', 'bunny-figure-metadata' ),
				value: settings.prefixClass,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'prefixClass', value );
				}
			} ),
			el( SelectControl, {
				label: __( 'Elemento HTML del valor', 'bunny-figure-metadata' ),
				value: settings.valueTag,
				options: HTML_TAG_OPTIONS,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'valueTag', value );
				}
			} ),
			el( TextControl, {
				label: __( 'Clase CSS del valor', 'bunny-figure-metadata' ),
				value: settings.valueClass,
				onChange: function ( value ) {
					updateFieldSetting( attributes, setAttributes, field.metaKey, 'valueClass', value );
				}
			} )
		);
	}

	registerBlockType( 'bunny-figure-metadata/features', {

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var fieldPanels = BFM_FEATURES_BLOCK.fields.map( function ( field ) {
				return renderFieldPanel( attributes, setAttributes, field );
			} );

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
				),
				el(
					PanelBody,
					{ title: __( 'Valor por defecto', 'bunny-figure-metadata' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Texto para metadatos vacíos', 'bunny-figure-metadata' ),
						help: __( 'Solo una representación visual: nunca se guarda en la metadata ni genera una entidad o TAG. Déjalo vacío para no mostrar ningún texto.', 'bunny-figure-metadata' ),
						value: attributes.defaultValueText,
						onChange: function ( value ) { setAttributes( { defaultValueText: value } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Características individuales', 'bunny-figure-metadata' ), initialOpen: false },
					fieldPanels
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
