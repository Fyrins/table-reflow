/**
 * Table Reflow - editor integration.
 *
 * Adds two attributes to the core table block and the controls that drive them.
 *
 * Nothing is written into the saved markup. The attributes have no `source`, so
 * they live in the block comment, which block validation ignores. Adding a class
 * through `blocks.getSaveContent.extraProps` would be safe on activation, since
 * an absent attribute falls back to its default and produces identical markup,
 * but it would invalidate every styled table on deactivation: the saved markup
 * would carry a class the regenerated markup no longer has. The classes are
 * therefore added on the server, in the `render_block` filter.
 *
 * Written as plain ES5 against the `wp` global on purpose: the plugin ships no
 * build step, so what is published is what is reviewed.
 *
 * @package TableReflow
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;

	var TARGET_BLOCK = 'core/table';
	var settings = window.tableReflowEditor || {};
	var breakpoints = Array.isArray( settings.breakpoints ) ? settings.breakpoints : [];
	var defaultBreakpoint = settings.defaultBreakpoint || '600';

	/**
	 * Declares the two attributes on the table block.
	 *
	 * Both have a default, so a block that was saved before the plugin was
	 * installed keeps producing byte-identical markup.
	 *
	 * @param {Object} blockSettings Block settings being registered.
	 * @param {string} name          Block name.
	 * @return {Object} Block settings, with the plugin attributes added.
	 */
	function addAttributes( blockSettings, name ) {
		if ( TARGET_BLOCK !== name ) {
			return blockSettings;
		}

		return Object.assign( {}, blockSettings, {
			attributes: Object.assign( {}, blockSettings.attributes, {
				tableReflow: {
					type: 'boolean',
					default: false,
				},
				tableReflowBreakpoint: {
					type: 'string',
					default: defaultBreakpoint,
				},
			} ),
		} );
	}

	/**
	 * Adds the inspector panel to the table block.
	 *
	 * Uses the standard components, each with an explicit label and help text,
	 * so the controls inherit the editor's own accessibility behaviour.
	 */
	var withTableReflowControls = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( TARGET_BLOCK !== props.name || ! props.isSelected ) {
				return el( BlockEdit, props );
			}

			var attributes = props.attributes;
			var isEnabled = !! attributes.tableReflow;

			var toggle = el( ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Stack on small screens', 'table-reflow' ),
				checked: isEnabled,
				help: __(
					'Below the chosen width, each row is shown as a card and every cell is preceded by its column name, instead of the table scrolling sideways. Needs a header row and no merged cells; a table that does not qualify keeps horizontal scrolling.',
					'table-reflow'
				),
				onChange: function ( value ) {
					props.setAttributes( { tableReflow: value } );
				},
			} );

			var select =
				isEnabled && breakpoints.length
					? el( SelectControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Stack below', 'table-reflow' ),
							value: attributes.tableReflowBreakpoint || defaultBreakpoint,
							options: breakpoints,
							help: __(
								'Screen width under which the table switches to the stacked layout.',
								'table-reflow'
							),
							onChange: function ( value ) {
								props.setAttributes( { tableReflowBreakpoint: value } );
							},
					  } )
					: null;

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Small screen display', 'table-reflow' ),
							initialOpen: false,
						},
						toggle,
						select
					)
				)
			);
		};
	}, 'withTableReflowControls' );

	addFilter( 'blocks.registerBlockType', 'table-reflow/attributes', addAttributes );
	addFilter( 'editor.BlockEdit', 'table-reflow/controls', withTableReflowControls );
} )( window.wp );
