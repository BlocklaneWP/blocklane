/**
 * Transparent Header — per-block transparent-state options.
 *
 * The transparent state is N independent properties, one per block inside the
 * header — a navigation has colors, a site title has a color, a logo has a
 * treatment and an alternate image — not one property of the header group.
 * Modeling it centrally forced the old `*:not(...) !important` blanket and its
 * denylist of self-painting containers, which was structurally incompletable
 * (the 0.9.1 mega-panel leak). Here each participating block carries its own
 * options in its own panels; the CSS names exactly the elements it styles and
 * every rule chains to `.is-th-transparent`, so a block that is never named is
 * never touched and everything is inert outside a transparent header.
 *
 * Participating blocks (spec 2026-08-20-transparent-header-block-extensions,
 * ruling D1): core/navigation (text — white default — hover, current),
 * core/site-title (text — white default), core/site-logo (white/black filter
 * treatment + alternate image). Pro's own mega-menu block gets nothing: its
 * panel paints its own background, and under this model that means it is
 * simply never targeted rather than needing an exclusion.
 *
 * The controls expose wherever the block sits in a header-area part; there is
 * deliberately NO transparent-enabled ancestor lookup (ruling D4 — core
 * reserves ancestor gating for parent-dependent blocks). A value set on a
 * block outside a transparent header is inert, not broken: the CSS only fires
 * under the state class.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	useSettings,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	Button,
	SelectControl,
	BaseControl,
	__experimentalHStack as HStack,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import useToolsPanelDropdownMenuProps from '../../../../shared/use-tools-panel-dropdown-menu-props';
import { isInHeaderArea } from '../../components/is-in-header';
import {
	encodePresetColor,
	decodePresetColor,
	presetColorToCss,
} from '../../components/preset-color';
import schema from './attributes.json';

const isEnabled =
	window.blocklaneProExtensions?.enabled?.[ 'transparent-header' ] ?? true;

/**
 * Per-block attribute rosters, keyed by block name from attributes.json (the
 * one source the PHP registrar renders too): every group after the first —
 * group 0 is the header group's own set, registered by ./index.js. Same names
 * as the retired group-level slots where the meaning carries over
 * (blocklaneProTransparentTextColor, blocklaneProTransparentLogo) — accuracy
 * over novelty; they live on different blocks so there is no collision.
 * Prefixed for the same reason the group's are: the unprefixed names are what
 * core would pick for a native version. core/group's one slot here
 * (blocklaneProThSolidBackground) is keyed to the SOLID state, not the
 * transparent one — see the roster note in blocks.php.
 */
const BLOCK_ATTRIBUTES = {};
for ( const { blocks, attributes } of schema.groups.slice( 1 ) ) {
	for ( const name of blocks ) {
		BLOCK_ATTRIBUTES[ name ] = {
			...BLOCK_ATTRIBUTES[ name ],
			...attributes,
		};
	}
}

/**
 * Canvas-mirror roster, matching the PHP roster in loader blocks.php exactly
 * — the canvas must paint what the front end will. `marker` is the always-on
 * chrome marker (blocks whose text sits bare on the stripped background —
 * these default white); slots map attribute => optional per-slot marker +
 * the instance var. Self-branded blocks (button, social icons) have no
 * marker: they participate only when a slot is set (ruling D1 — buttons
 * stay branded by default).
 */
const MIRROR_ROSTER = {
	'core/group': {
		marker: null,
		slots: {
			blocklaneProThSolidBackground: {
				className: 'blocklane-pro-th-row-has-solidbg',
				varName: '--blocklane-pro-th-row-solid-bg',
			},
		},
	},
	'core/navigation': {
		marker: 'blocklane-pro-th-nav',
		slots: {
			blocklaneProTransparentTextColor: {
				className: null,
				varName: '--blocklane-pro-th-nav-text',
			},
			blocklaneProTransparentHoverColor: {
				className: 'blocklane-pro-th-nav-has-hover',
				varName: '--blocklane-pro-th-nav-hover',
			},
			blocklaneProTransparentCurrentColor: {
				className: 'blocklane-pro-th-nav-has-current',
				varName: '--blocklane-pro-th-nav-current',
			},
		},
	},
	'core/site-title': {
		marker: 'blocklane-pro-th-title',
		slots: {
			blocklaneProTransparentTextColor: {
				className: null,
				varName: '--blocklane-pro-th-title-text',
			},
		},
	},
	'core/site-tagline': {
		marker: 'blocklane-pro-th-tagline',
		slots: {
			blocklaneProTransparentTextColor: {
				className: null,
				varName: '--blocklane-pro-th-tagline-text',
			},
		},
	},
	'core/search': {
		marker: 'blocklane-pro-th-search',
		slots: {
			blocklaneProTransparentTextColor: {
				className: null,
				varName: '--blocklane-pro-th-search-text',
			},
		},
	},
	'core/loginout': {
		marker: 'blocklane-pro-th-loginout',
		slots: {
			blocklaneProTransparentTextColor: {
				className: null,
				varName: '--blocklane-pro-th-loginout-text',
			},
		},
	},
	'core/button': {
		marker: null,
		slots: {
			blocklaneProTransparentTextColor: {
				className: 'blocklane-pro-th-btn-has-text',
				varName: '--blocklane-pro-th-btn-text',
			},
			blocklaneProTransparentBackground: {
				className: 'blocklane-pro-th-btn-has-bg',
				varName: '--blocklane-pro-th-btn-bg',
			},
		},
	},
	'core/social-links': {
		marker: null,
		slots: {
			blocklaneProTransparentIconColor: {
				className: 'blocklane-pro-th-social-has-icon',
				varName: '--blocklane-pro-th-social-icon',
			},
			blocklaneProTransparentIconBackground: {
				className: 'blocklane-pro-th-social-has-iconbg',
				varName: '--blocklane-pro-th-social-iconbg',
			},
		},
	},
};

/**
 * Register the attributes. Unconditional so saved content keeps validating
 * when the extension is off (the group extension's exact idiom).
 */
addFilter(
	'blocks.registerBlockType',
	'blocklane-pro/transparent-header/block-attributes',
	( settings ) => {
		const extra = BLOCK_ATTRIBUTES[ settings.name ];
		if ( ! extra ) {
			return settings;
		}
		return {
			...settings,
			attributes: { ...settings.attributes, ...extra },
		};
	}
);

/**
 * Color slots per block, rendered into CORE's Color panel — the hover-color
 * precedent colors-controls.js follows: colors live where core puts colors.
 * The navigation defaults to white text at render (ruling D3, literal
 * #ffffff — Pro cannot assume a theme preset), so its Text slot reads as an
 * override of that default, not a requirement.
 */
const COLOR_SLOTS = {
	'core/group': [
		{
			attribute: 'blocklaneProThSolidBackground',
			label: __( 'Solid Background', 'blocklane' ),
		},
	],
	'core/navigation': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Text', 'blocklane' ),
		},
		{
			attribute: 'blocklaneProTransparentHoverColor',
			label: __( 'Transparent Hover', 'blocklane' ),
		},
		{
			attribute: 'blocklaneProTransparentCurrentColor',
			label: __( 'Transparent Active', 'blocklane' ),
		},
	],
	'core/site-title': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Text', 'blocklane' ),
		},
	],
	'core/site-tagline': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Text', 'blocklane' ),
		},
	],
	'core/search': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Label', 'blocklane' ),
		},
	],
	'core/loginout': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Text', 'blocklane' ),
		},
	],
	'core/button': [
		{
			attribute: 'blocklaneProTransparentTextColor',
			label: __( 'Transparent Text', 'blocklane' ),
		},
		{
			attribute: 'blocklaneProTransparentBackground',
			label: __( 'Transparent Background', 'blocklane' ),
		},
	],
	'core/social-links': [
		{
			attribute: 'blocklaneProTransparentIconColor',
			label: __( 'Transparent Icon', 'blocklane' ),
		},
		{
			attribute: 'blocklaneProTransparentIconBackground',
			label: __( 'Transparent Icon Background', 'blocklane' ),
		},
	],
};

/**
 * The color dropdowns for one block, mirroring colors-controls.js.
 *
 * @param {Object}   props
 * @param {string}   props.clientId      Block client id.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {Object[]} props.slots         COLOR_SLOTS entry for this block.
 */
function TransparentBlockColors( {
	clientId,
	attributes,
	setAttributes,
	slots,
} ) {
	const colorGradientSettings = useMultipleOriginColorsAndGradients();
	const [ customEnabled ] = useSettings( 'color.custom' );

	const allColors = useMemo(
		() =>
			( colorGradientSettings.colors ?? [] ).flatMap(
				( origin ) => origin.colors ?? []
			),
		[ colorGradientSettings.colors ]
	);

	if ( ! colorGradientSettings.hasColorsOrGradients && ! customEnabled ) {
		return null;
	}

	return (
		<InspectorControls group="color">
			{ slots.map( ( { attribute, label } ) => (
				<ColorGradientSettingsDropdown
					key={ attribute }
					__experimentalIsRenderedInSidebar
					settings={ [
						{
							colorValue: decodePresetColor(
								attributes[ attribute ],
								allColors
							),
							label,
							onColorChange: ( value ) =>
								setAttributes( {
									[ attribute ]: encodePresetColor(
										value,
										allColors
									),
								} ),
							resetAllFilter: ( attrs ) => ( {
								...attrs,
								[ attribute ]: undefined,
							} ),
							isShownByDefault: false,
							enableAlpha: true,
							clearable: true,
						},
					] }
					panelId={ clientId }
					{ ...colorGradientSettings }
				/>
			) ) }
		</InspectorControls>
	);
}

/**
 * Site-logo transparent options: the white/black filter treatment (moved here
 * from the header group's panel — it acts on this block) and the alternate
 * image (#135). When an alternate image is set the treatment does not apply:
 * the image IS the transparent look, and filtering a hand-picked logo to a
 * silhouette would defeat the point of picking it.
 *
 * @param {Object}   props
 * @param {string}   props.clientId      Block client id.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 */
function TransparentLogoControls( { clientId, attributes, setAttributes } ) {
	const {
		blocklaneProTransparentLogo: treatment,
		blocklaneProTransparentLogoId: altId,
	} = attributes;
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	const altMedia = useSelect(
		( select ) =>
			altId
				? select( 'core' ).getMedia( altId, { context: 'view' } )
				: null,
		[ altId ]
	);

	return (
		<InspectorControls>
			<ToolsPanel
				label={ __( 'Transparent Header', 'blocklane' ) }
				resetAll={ () =>
					setAttributes( {
						blocklaneProTransparentLogo: undefined,
						blocklaneProTransparentLogoId: undefined,
					} )
				}
				panelId={ clientId }
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					label={ __( 'Transparent logo', 'blocklane' ) }
					hasValue={ () => !! altId }
					onDeselect={ () =>
						setAttributes( {
							blocklaneProTransparentLogoId: undefined,
						} )
					}
					panelId={ clientId }
				>
					<MediaUploadCheck>
						<BaseControl
							__nextHasNoMarginBottom
							id="blocklane-pro-th-alt-logo"
							label={ __( 'Transparent logo', 'blocklane' ) }
							help={ __(
								'Shown instead of the site logo while the header is transparent. Use a version of the logo made for dark or photographic backgrounds.',
								'blocklane'
							) }
						>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								value={ altId }
								onSelect={ ( media ) =>
									setAttributes( {
										blocklaneProTransparentLogoId:
											media?.id || undefined,
									} )
								}
								render={ ( { open } ) =>
									altId ? (
										<HStack
											spacing={ 2 }
											justify="flex-start"
										>
											<Button
												variant="secondary"
												onClick={ open }
											>
												{ altMedia?.title?.rendered ||
													__(
														'Replace image',
														'blocklane'
													) }
											</Button>
											<Button
												variant="tertiary"
												isDestructive
												onClick={ () =>
													setAttributes( {
														blocklaneProTransparentLogoId:
															undefined,
													} )
												}
											>
												{ __( 'Remove', 'blocklane' ) }
											</Button>
										</HStack>
									) : (
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ __(
												'Select image',
												'blocklane'
											) }
										</Button>
									)
								}
							/>
						</BaseControl>
					</MediaUploadCheck>
				</ToolsPanelItem>
				{ ! altId && (
					<ToolsPanelItem
						label={ __( 'Logo treatment', 'blocklane' ) }
						hasValue={ () => !! treatment }
						onDeselect={ () =>
							setAttributes( {
								blocklaneProTransparentLogo: undefined,
							} )
						}
						panelId={ clientId }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Logo treatment', 'blocklane' ) }
							help={ __(
								'Recolor the logo with a filter while the header is transparent. White suits dark heroes; a transparent logo image gives full control.',
								'blocklane'
							) }
							value={ treatment || '' }
							options={ [
								{
									value: '',
									label: __( 'Default', 'blocklane' ),
								},
								{
									value: 'white',
									label: __( 'White', 'blocklane' ),
								},
								{
									value: 'black',
									label: __( 'Black', 'blocklane' ),
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									blocklaneProTransparentLogo:
										value || undefined,
								} )
							}
						/>
					</ToolsPanelItem>
				) }
			</ToolsPanel>
		</InspectorControls>
	);
}

/**
 * Hooks-bearing wrapper so the header-area lookup only runs on the three
 * participating blocks.
 *
 * @param {Object}   props
 * @param {Function} props.BlockEdit The original BlockEdit component.
 */
function TransparentBlockEdit( { BlockEdit, ...props } ) {
	const { name, clientId, attributes, setAttributes } = props;

	// Exposure is the header AREA (the existing shared helper), not a
	// transparent-enabled ancestor — ruling D4. A header part not currently
	// using the overlay still shows the options; their values wait inertly.
	const inHeader = useSelect(
		( select ) => isInHeaderArea( select, clientId ),
		[ clientId ]
	);

	const slots = COLOR_SLOTS[ name ];

	return (
		<>
			<BlockEdit { ...props } />
			{ inHeader && slots && (
				<TransparentBlockColors
					clientId={ clientId }
					attributes={ attributes }
					setAttributes={ setAttributes }
					slots={ slots }
				/>
			) }
			{ inHeader && 'core/site-logo' === name && (
				<TransparentLogoControls
					clientId={ clientId }
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			) }
		</>
	);
}

const withTransparentBlockControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) =>
		BLOCK_ATTRIBUTES[ props.name ] ? (
			<TransparentBlockEdit BlockEdit={ BlockEdit } { ...props } />
		) : (
			<BlockEdit { ...props } />
		),
	'withTransparentBlockControls'
);

/**
 * Canvas mirror: the marker classes and inline vars the server adds at
 * render, so the existing group mirror's `is-th-transparent` lights the same
 * styling the front end will paint. Added unconditionally on the three blocks
 * — without a transparent group ancestor every one of these is inert, which
 * is also why a footer nav carrying a value shows nothing (ruling D4).
 *
 * The alternate-logo swap cannot put a second <img> in the canvas, so the
 * editor-only stylesheet swaps the rendered image via `content:` fed by the
 * var set here (editor.scss); browsers without `content` on img simply keep
 * showing the original — a preview shortfall, never a content one.
 */
const withTransparentBlockClasses = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		const { name, attributes } = props;
		const roster = BLOCK_ATTRIBUTES[ name ];

		const altUrl = useSelect(
			( select ) => {
				if (
					'core/site-logo' !== name ||
					! attributes?.blocklaneProTransparentLogoId
				) {
					return '';
				}
				return (
					select( 'core' ).getMedia(
						attributes.blocklaneProTransparentLogoId,
						{ context: 'view' }
					)?.source_url || ''
				);
			},
			[ name, attributes?.blocklaneProTransparentLogoId ]
		);

		if ( ! roster ) {
			return <BlockListBlock { ...props } />;
		}

		const classes = [];
		const vars = {};

		const mirror = MIRROR_ROSTER[ name ];
		if ( mirror ) {
			if ( mirror.marker ) {
				classes.push( mirror.marker );
			}
			for ( const [ attribute, slot ] of Object.entries(
				mirror.slots
			) ) {
				const value = presetColorToCss( attributes[ attribute ] );
				if ( ! value ) {
					continue;
				}
				if ( slot.className ) {
					classes.push( slot.className );
				}
				vars[ slot.varName ] = value;
			}
		} else if ( 'core/site-logo' === name ) {
			const treatment = attributes.blocklaneProTransparentLogo;
			if ( altUrl ) {
				classes.push( 'blocklane-pro-th-has-alt' );
				vars[ '--blocklane-pro-th-alt-url' ] = `url("${ altUrl }")`;
			} else if ( 'white' === treatment || 'black' === treatment ) {
				classes.push( `blocklane-pro-th-logo-${ treatment }` );
			}
		}

		if ( ! classes.length && ! Object.keys( vars ).length ) {
			return <BlockListBlock { ...props } />;
		}

		const className = [ props.className, ...classes ]
			.filter( Boolean )
			.join( ' ' );
		const wrapperProps = Object.keys( vars ).length
			? {
					...props.wrapperProps,
					style: { ...props.wrapperProps?.style, ...vars },
				}
			: props.wrapperProps;

		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ wrapperProps }
			/>
		);
	},
	'withTransparentBlockClasses'
);

if ( isEnabled ) {
	addFilter(
		'editor.BlockEdit',
		'blocklane-pro/transparent-header/block-controls',
		withTransparentBlockControls
	);
	addFilter(
		'editor.BlockListBlock',
		'blocklane-pro/transparent-header/block-classes',
		withTransparentBlockClasses
	);
}
