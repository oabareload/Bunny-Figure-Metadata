/**
 * Bunny Figure Metadata — "Metadata Field" block (editor)
 *
 * Dynamic/server-side block: edit() renders Inspector controls plus
 * a live preview via <ServerSideRender>.
 * save() returns null — no block HTML is stored in post_content.
 *
 * Written as plain ES5 JS against the wp.* globals (no JSX, no build step).
 *
 * @package BunnyFigureMetadata
 */

( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var Placeholder = components.Placeholder;
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
		{ label: 'span', value: 'span' }
	];

	var fieldsConfig = ( typeof BFM_METADATA_FIELD_BLOCK !== 'undefined' && Array.isArray( BFM_METADATA_FIELD_BLOCK.fields ) )
		? BFM_METADATA_FIELD_BLOCK.fields
		: [];

	var FIELD_OPTIONS = [
		{ label: __( '— Selecciona un metadato —', 'bunny-figure-metadata' ), value: '' }
	];

	for ( var i = 0; i < fieldsConfig.length; i++ ) {
		var f = fieldsConfig[ i ];
		FIELD_OPTIONS.push( {
			label: f.label + ' (' + f.value + ')',
			value: f.value
		} );
	}

	function findField( key ) {
		for ( var j = 0; j < fieldsConfig.length; j++ ) {
			if ( fieldsConfig[ j ].value === key ) {
				return fieldsConfig[ j ];
			}
		}
		return null;
	}

	registerBlockType( 'bunny-figure-metadata/metadata-field', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var currentField = findField( attributes.metaKey );
			var defaultLabelPlaceholder = currentField ? currentField.label : __( 'Etiqueta original', 'bunny-figure-metadata' );

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Metadato', 'bunny-figure-metadata' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Metadato a mostrar', 'bunny-figure-metadata' ),
						value: attributes.metaKey,
						options: FIELD_OPTIONS,
						onChange: function ( val ) {
							setAttributes( { metaKey: val } );
						}
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Configuración de la etiqueta', 'bunny-figure-metadata' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Mostrar etiqueta', 'bunny-figure-metadata' ),
						checked: !!attributes.showLabel,
						onChange: function ( val ) {
							setAttributes( { showLabel: val } );
						}
					} ),
					attributes.showLabel ? el( TextControl, {
						label: __( 'Texto visible personalizado', 'bunny-figure-metadata' ),
						value: attributes.customLabel || '',
						placeholder: defaultLabelPlaceholder,
						help: __( 'Si se deja vacío, se utilizará la etiqueta administrativa del metadato.', 'bunny-figure-metadata' ),
						onChange: function ( val ) {
							setAttributes( { customLabel: val } );
						}
					} ) : null,
					attributes.showLabel ? el( SelectControl, {
						label: __( 'Etiqueta HTML de la etiqueta', 'bunny-figure-metadata' ),
						value: attributes.labelTag || 'h2',
						options: HTML_TAG_OPTIONS,
						onChange: function ( val ) {
							setAttributes( { labelTag: val } );
						}
					} ) : null
				),
				el(
					PanelBody,
					{ title: __( 'Configuración del valor', 'bunny-figure-metadata' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Etiqueta HTML del valor', 'bunny-figure-metadata' ),
						value: attributes.valueTag || 'div',
						options: HTML_TAG_OPTIONS,
						onChange: function ( val ) {
							setAttributes( { valueTag: val } );
						}
					} ),
					el( TextControl, {
						label: __( 'Clases CSS adicionales para el valor', 'bunny-figure-metadata' ),
						value: attributes.valueClassName || '',
						onChange: function ( val ) {
							setAttributes( { valueClassName: val } );
						}
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Condición de visibilidad', 'bunny-figure-metadata' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Mostrar únicamente si el metadato tiene contenido', 'bunny-figure-metadata' ),
						checked: !!attributes.hideIfEmpty,
						help: __( 'No genera ningún HTML si el valor está vacío, null o solo contiene espacios en blanco.', 'bunny-figure-metadata' ),
						onChange: function ( val ) {
							setAttributes( { hideIfEmpty: val } );
						}
					} )
				)
			);

			if ( ! attributes.metaKey ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						Placeholder,
						{
							icon: 'editor-alignleft',
							label: __( 'Bunny Figure: Metadata Field', 'bunny-figure-metadata' ),
							instructions: __( 'Selecciona un metadato en la barra lateral para mostrarlo.', 'bunny-figure-metadata' )
						}
					)
				);
			}

			var previewContent = null;
			if ( ServerSideRender ) {
				previewContent = el( ServerSideRender, {
					block: 'bunny-figure-metadata/metadata-field',
					attributes: attributes
				} );
			} else {
				previewContent = el(
					'div',
					{ className: 'bfm-metadata-field-placeholder' },
					__( 'Vista previa de metadata field:', 'bunny-figure-metadata' ) + ' ' + ( currentField ? currentField.label : attributes.metaKey )
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				previewContent
			);
		},

		save: function () {
			return null;
		}
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
