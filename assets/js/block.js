/**
 * Municipal Phonebook block (editor). No build step required.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var PanelColorSettings = wp.blockEditor.PanelColorSettings;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'municipal-phonebook/directory', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'municipal-phonebook' ) },
						el( ToggleControl, {
							label: __( 'Show search', 'municipal-phonebook' ),
							checked: !! a.showSearch,
							onChange: function ( v ) {
								props.setAttributes( { showSearch: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show categories', 'municipal-phonebook' ),
							checked: !! a.showToc,
							onChange: function ( v ) {
								props.setAttributes( { showToc: v } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Switch to cards below', 'municipal-phonebook' ),
							value: a.breakpoint,
							options: [
								{ label: __( 'Never', 'municipal-phonebook' ), value: 'never' },
								{ label: '480px', value: '480' },
								{ label: '720px', value: '720' },
								{ label: '960px', value: '960' },
								{ label: __( 'Always cards', 'municipal-phonebook' ), value: 'always' }
							],
							onChange: function ( v ) {
								props.setAttributes( { breakpoint: v } );
							}
						} ),
						el( TextControl, {
							label: __( 'Only these departments (slugs, comma separated)', 'municipal-phonebook' ),
							value: a.departments,
							onChange: function ( v ) {
								props.setAttributes( { departments: v } );
							}
						} )
					),
					el( PanelColorSettings, {
						title: __( 'Row colors', 'municipal-phonebook' ),
						colorSettings: [
							{
								value: a.rowOdd || undefined,
								label: __( 'Odd row color', 'municipal-phonebook' ),
								onChange: function ( v ) {
									props.setAttributes( { rowOdd: v || '' } );
								}
							},
							{
								value: a.rowEven || undefined,
								label: __( 'Even row color', 'municipal-phonebook' ),
								onChange: function ( v ) {
									props.setAttributes( { rowEven: v || '' } );
								}
							}
						]
					} )
				),
				el( ServerSideRender, { block: 'municipal-phonebook/directory', attributes: a } )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
