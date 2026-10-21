/**
 * Menu Button: the editor control.
 *
 * A "Menu button" panel on the Navigation block: a label for the button that
 * opens the collapsed menu, one of five WordPress icons, and the theme's
 * button style, with a preview of the result. The front end changes core's
 * button at render (loader/navigation-toggle/); nothing is added to the
 * saved markup, so the block is valid with or without this plugin.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	PanelBody,
	TextControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Icon, category, grid, layout, list, menu } from '@wordpress/icons';

import schema from './attributes.json';

const GROUP = schema.groups[ 0 ];
const BLOCKS = GROUP.blocks;
/** Mirror of blocklane_pro_navigation_toggle_settings()'s label cut. */
const LABEL_MAX = 60;

const ICONS = [
	{ value: 'menu', icon: menu, label: __( 'Menu', 'blocklane' ) },
	{ value: 'layout', icon: layout, label: __( 'Layout', 'blocklane' ) },
	{ value: 'category', icon: category, label: __( 'Category', 'blocklane' ) },
	{ value: 'grid', icon: grid, label: __( 'Grid', 'blocklane' ) },
	{ value: 'list', icon: list, label: __( 'List', 'blocklane' ) },
];

const isEnabled =
	window.blocklaneProExtensions?.enabled?.[ 'navigation-toggle' ] ?? true;

addFilter(
	'blocks.registerBlockType',
	'blocklane-pro/navigation-toggle/attributes',
	( settings, name ) =>
		BLOCKS.includes( name )
			? {
					...settings,
					attributes: {
						...settings.attributes,
						...GROUP.attributes,
					},
				}
			: settings
);

/**
 * What the front end will show: the chosen icon (or a menu icon for
 * core's), the label, and an outline for the button look.
 *
 * @param {Object}  props        Component props.
 * @param {string}  props.label  The label.
 * @param {string}  props.icon   The icon's name, or ''.
 * @param {boolean} props.button Whether the button look is on.
 * @return {Element} The preview.
 */
function Preview( { label, icon, button } ) {
	const chosen = ICONS.find( ( i ) => i.value === icon );
	return (
		<div
			className="blocklane-navigation-toggle-preview"
			aria-hidden="true"
			style={ {
				display: 'inline-flex',
				alignItems: 'center',
				gap: '0.5em',
				minHeight: '36px',
				padding: button ? '0 12px' : 0,
				border: button ? '1px solid currentColor' : 0,
			} }
		>
			<Icon icon={ chosen ? chosen.icon : menu } size={ 24 } />
			{ label && <span>{ label.slice( 0, LABEL_MAX ) }</span> }
		</div>
	);
}

function MenuButtonPanel( { attributes, setAttributes } ) {
	const label =
		'string' === typeof attributes.blocklaneToggleLabel
			? attributes.blocklaneToggleLabel
			: '';
	const icon = ICONS.some(
		( i ) => i.value === attributes.blocklaneToggleIcon
	)
		? attributes.blocklaneToggleIcon
		: '';
	const button = 'button' === attributes.blocklaneToggleStyle;
	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Menu button', 'blocklane' ) }
				initialOpen={ !! ( label || icon || button ) }
			>
				<p className="components-base-control__help">
					{ __(
						'The button that opens this menu when it collapses.',
						'blocklane'
					) }
				</p>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Label', 'blocklane' ) }
					help={ __(
						'Shown beside the icon, and the name screen readers announce for the button and its menu. Leave it empty for the icon alone.',
						'blocklane'
					) }
					value={ label }
					maxLength={ LABEL_MAX }
					onChange={ ( next ) =>
						setAttributes( { blocklaneToggleLabel: next } )
					}
				/>
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					isDeselectable
					label={ __( 'Icon', 'blocklane' ) }
					help={ __(
						'None chosen keeps the theme’s icon.',
						'blocklane'
					) }
					value={ icon || undefined }
					onChange={ ( next ) =>
						setAttributes( { blocklaneToggleIcon: next || '' } )
					}
				>
					{ ICONS.map( ( i ) => (
						<ToggleGroupControlOptionIcon
							key={ i.value }
							value={ i.value }
							icon={ i.icon }
							label={ i.label }
						/>
					) ) }
				</ToggleGroupControl>
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					isBlock
					label={ __( 'Style', 'blocklane' ) }
					value={ button ? 'button' : 'default' }
					onChange={ ( next ) =>
						setAttributes( {
							blocklaneToggleStyle:
								'button' === next ? 'button' : '',
						} )
					}
				>
					<ToggleGroupControlOption
						value="default"
						label={ __( 'Default', 'blocklane' ) }
					/>
					<ToggleGroupControlOption
						value="button"
						label={ __( 'Button', 'blocklane' ) }
					/>
				</ToggleGroupControl>
				<BaseControl
					__nextHasNoMarginBottom
					id="blocklane-navigation-toggle-preview"
					label={ __( 'Preview', 'blocklane' ) }
				>
					<Preview label={ label } icon={ icon } button={ button } />
				</BaseControl>
			</PanelBody>
		</InspectorControls>
	);
}

if ( isEnabled ) {
	addFilter(
		'editor.BlockEdit',
		'blocklane-pro/navigation-toggle/controls',
		createHigherOrderComponent(
			( BlockEdit ) => ( props ) =>
				BLOCKS.includes( props.name ) && props.isSelected ? (
					<>
						<BlockEdit { ...props } />
						<MenuButtonPanel { ...props } />
					</>
				) : (
					<BlockEdit { ...props } />
				),
			'withMenuButtonControls'
		)
	);
}
