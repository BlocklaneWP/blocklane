/**
 * Popups — editor sidebar: the settings for the blocklane_popup CPT as
 * top-level ToolsPanel sections (Popup, Trigger, Animation, Display Rules),
 * matching the block inspector's section anatomy: the ToolsPanel provides
 * the header (label + menu) and grid spacing; the wrapping document panel's
 * own chrome is suppressed (editor.css). Only Position and Show-when render
 * by default — everything else is opt-in through each section's menu, and
 * deselecting an item resets its slice. The block editor IS the popup
 * designer (content is just blocks); everything behavioral lives in one
 * registered meta object, allowlisted again server-side (runtime.php).
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	PluginPostStatusInfo,
	PluginPreviewMenuItem,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { external } from '@wordpress/icons';
import {
	BaseControl,
	Button,
	FormTokenField,
	RangeControl,
	SelectControl,
	ToggleControl,
	AlignmentMatrixControl as StableAlignmentMatrixControl,
	__experimentalAlignmentMatrixControl as ExperimentalAlignmentMatrixControl,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	__experimentalText as Text,
	__experimentalUnitControl as UnitControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalVStack as VStack,
} from '@wordpress/components';

import useToolsPanelDropdownMenuProps from '../../shared/use-tools-panel-dropdown-menu-props';
import { ANIMATION_OPTIONS } from '../../shared/animation-options';
import { makeOptionHelpers } from './options';

import './editor.css';

// Stabilized in newer component versions; fall back to the experimental name.
const AlignmentMatrixControl =
	StableAlignmentMatrixControl || ExperimentalAlignmentMatrixControl;

const POST_TYPE = 'blocklane_popup';

/**
 * Matrix value ('top left') → stored slug ('top-left'; plain 'center').
 *
 * @param {string} value AlignmentMatrixControl value.
 * @return {string} Position slug.
 */
const matrixToSlug = ( value ) =>
	'center center' === value ? 'center' : value.replace( ' ', '-' );
const slugToMatrix = ( slug ) =>
	'center' === slug
		? 'center center'
		: ( slug || 'center' ).replace( '-', ' ' );

/*
 * The option rows THIS EDITION offers, localized by the runtime before this
 * script (blocklane_pro_popups_editor_options() — the third registry of the
 * popups vocabularies, edition-manifest rule 6): `trigger` and `condition`
 * rows plus `qualifier`, the one label map for the qualifier vocabulary.
 * Labels arrive translated. The helpers are a pure module so they can be
 * tested (inc/popups/src/options.js, options.test.js); neither file quotes a
 * vocabulary value of its own, so the free bundle names no Pro option.
 */
const {
	triggerOptions,
	conditionOptions,
	qualifierOptions,
	defaultQualifier,
	triggerRow,
	isValueless,
	triggerHelp,
} = makeOptionHelpers( window.blocklaneProPopupsOptions );

/**
 * CSS-length units for the width controls. Viewport units are excluded:
 * circular for a viewport threshold, pointless for the card.
 */
const LENGTH_UNITS = [
	{ value: 'px', label: 'px', default: 0 },
	{ value: 'em', label: 'em', default: 0 },
	{ value: 'rem', label: 'rem', default: 0 },
];

/** Slider ceiling per unit — 2000px ≈ 125em/rem at a 16px root. */
const LENGTH_MAX = { px: 2000, em: 125, rem: 125 };

/**
 * Split a CSS length into [number, unit] for the slider sync.
 *
 * @param {string} value CSS length ('768px').
 * @return {Array} [number, unit].
 */
const parseLength = ( value ) => {
	const match = /^([\d.]+)(px|em|rem)$/.exec( value || '' );
	return match ? [ parseFloat( match[ 1 ] ), match[ 2 ] ] : [ 0, 'px' ];
};

/**
 * A CSS-length field: unit input beside a synced slider — core's
 * HeightControl anatomy (BaseControl label/help around a Flex with two
 * isBlock halves), restricted to px/em/rem.
 *
 * @param {Object}                  props          Component props.
 * @param {string}                  props.label    Control label.
 * @param {string}                  props.help     Guidance under the control.
 * @param {string}                  props.value    Current CSS length ('' = unset).
 * @param {(value: string) => void} props.onChange Receives the next CSS length ('' to clear).
 */
function LengthControl( { label, help, value, onChange } ) {
	const [ number, unit ] = parseLength( value );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			id={ `blocklane-popup-length-${ label }` }
		>
			<Flex>
				<FlexItem isBlock>
					<UnitControl
						__next40pxDefaultSize
						label={ label }
						hideLabelFromVision
						value={ value || '0px' }
						units={ LENGTH_UNITS }
						min={ 0 }
						onChange={ ( next ) => {
							const [ n, u ] = parseLength( next );
							onChange( n > 0 ? `${ n }${ u }` : '' );
						} }
					/>
				</FlexItem>
				<FlexItem isBlock>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						aria-label={ label }
						withInputField={ false }
						min={ 0 }
						max={ LENGTH_MAX[ unit ] || 2000 }
						value={ number }
						onChange={ ( next ) =>
							onChange( next > 0 ? `${ next }${ unit }` : '' )
						}
					/>
				</FlexItem>
			</Flex>
		</BaseControl>
	);
}

/** Per-slice defaults, for ToolsPanelItem resets. */
const SLICE_DEFAULTS = {
	trigger: { type: 'time', value: 0 },
	frequency: { seen: 0, dismissed: 7 },
	animation: { type: '', duration: 0.4, delay: 0 },
};

function usePopupSettings() {
	const [ meta, setMeta ] = useEntityProp( 'postType', POST_TYPE, 'meta' );
	const settings = meta?.blocklane_popup_settings || {};
	const update = ( patch ) =>
		setMeta( {
			...meta,
			blocklane_popup_settings: { ...settings, ...patch },
		} );
	return [ settings, update ];
}

/**
 * A document-sidebar section whose visible chrome is the ToolsPanel inside
 * it — the wrapping panel's own header is display:none'd (editor.css), so
 * the anatomy matches the block inspector's top-level ToolsPanels.
 *
 * @param {Object}                    props          Component props.
 * @param {string}                    props.name     Panel machine name.
 * @param {string}                    props.title    Panel title (preferences list).
 * @param {import('react').ReactNode} props.children Section content.
 */
function Section( { name, title, children } ) {
	// The wrapper panel's own header is hidden, so it can never be toggled by
	// hand — keep it open through the editor store (the ToolsPanel inside is
	// the section's visible chrome and manages its own disclosure).
	const panelId = `blocklane-pro-popups/${ name }`;
	const isOpened = useSelect(
		( select ) => select( 'core/editor' ).isEditorPanelOpened( panelId ),
		[ panelId ]
	);
	const { toggleEditorPanelOpened } = useDispatch( 'core/editor' );
	useEffect( () => {
		if ( ! isOpened ) {
			toggleEditorPanelOpened( panelId );
		}
	}, [ isOpened, panelId, toggleEditorPanelOpened ] );

	return (
		<PluginDocumentSettingPanel
			name={ name }
			title={ title }
			className="blocklane-popup-section"
		>
			{ children }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Mirror the popup's max width onto the editor canvas: override the global
 * content/wide size vars so blocks lay out at popup width — what you design
 * is what opens (the front dialog carries the same cap). The value is capped
 * at the theme's own content size, matching the front's center-modal sizing.
 * The canvas is usually an iframe and can remount (device preview, canvas
 * rebuilds), so re-apply on DOM mutations; the guard makes re-runs free.
 *
 * @param {string} maxWidth CSS length ('' = no cap).
 */
const CANVAS_STYLE_ID = 'blocklane-popup-max-width';

function useCanvasMaxWidth( maxWidth ) {
	useEffect( () => {
		// Validate through parseLength so the accepted grammar has one source
		// of truth (add a unit there and the canvas mirror follows).
		const valid = parseLength( maxWidth )[ 0 ] > 0 ? maxWidth : '';

		const apply = () => {
			const iframe = document.querySelector(
				'iframe[name="editor-canvas"]'
			);
			const doc = iframe?.contentDocument || document;
			const wrapper = doc?.querySelector?.( '.editor-styles-wrapper' );
			if ( ! doc?.head || ! wrapper ) {
				return;
			}
			let styleEl = doc.getElementById( CANVAS_STYLE_ID );
			if ( ! valid ) {
				if ( styleEl ) {
					styleEl.remove();
				}
				return;
			}
			if ( styleEl?.isConnected && styleEl.dataset.value === valid ) {
				return;
			}
			// Snapshot the theme's own sizes before overriding them.
			if ( ! styleEl ) {
				const themeStyles = doc.defaultView.getComputedStyle( wrapper );
				styleEl = doc.createElement( 'style' );
				styleEl.id = CANVAS_STYLE_ID;
				styleEl.dataset.content =
					themeStyles
						.getPropertyValue( '--wp--style--global--content-size' )
						.trim() || '9999px';
				styleEl.dataset.wide =
					themeStyles
						.getPropertyValue( '--wp--style--global--wide-size' )
						.trim() || '9999px';
				doc.head.appendChild( styleEl );
			}
			styleEl.dataset.value = valid;
			// Two layers: the vars cover nested constrained containers (their
			// generated rules reference the globals), but the ROOT container's
			// generated layout rules embed the theme's content size as a
			// literal — cap its children directly. Alignments are capped too:
			// the front dialog caps everything, so the canvas must as well.
			const cap = `min(${ valid }, ${ styleEl.dataset.content })`;
			styleEl.textContent =
				`.editor-styles-wrapper{` +
				`--wp--style--global--content-size:${ cap };` +
				`--wp--style--global--wide-size:min(${ valid }, ${ styleEl.dataset.wide });}` +
				`.editor-styles-wrapper .is-root-container > *{` +
				`max-width:${ cap } !important;` +
				`margin-left:auto !important;margin-right:auto !important;}`;
		};

		apply();
		// The observer catches canvas remounts in the PARENT document, but
		// the wrapper materializes inside the iframe after the parent DOM
		// settles — the interval is the reliable retry; the connected+value
		// guard makes every no-op tick free.
		const observer = new window.MutationObserver( apply );
		observer.observe( document.body, { childList: true, subtree: true } );
		const interval = window.setInterval( apply, 700 );
		return () => {
			observer.disconnect();
			window.clearInterval( interval );
			const iframe = document.querySelector(
				'iframe[name="editor-canvas"]'
			);
			const doc = iframe?.contentDocument || document;
			doc?.getElementById?.( CANVAS_STYLE_ID )?.remove();
		};
	}, [ maxWidth ] );
}

function PopupSection() {
	const [ settings, update ] = usePopupSettings();
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	useCanvasMaxWidth( settings.maxWidth || '' );

	return (
		<Section
			name="blocklane-popup-main"
			title={ __( 'Popup', 'blocklane' ) }
		>
			<ToolsPanel
				label={ __( 'Popup', 'blocklane' ) }
				dropdownMenuProps={ dropdownMenuProps }
				resetAll={ () =>
					update( {
						position: 'center',
						maxWidth: '',
						minWidth: '',
						closable: true,
						showCloseButton: true,
					} )
				}
				panelId="blocklane-popup-main"
			>
				<ToolsPanelItem
					hasValue={ () =>
						( settings.position || 'center' ) !== 'center'
					}
					label={ __( 'Position', 'blocklane' ) }
					onDeselect={ () => update( { position: 'center' } ) }
					isShownByDefault
					panelId="blocklane-popup-main"
				>
					<BaseControl
						__nextHasNoMarginBottom
						label={ __( 'Position', 'blocklane' ) }
						help={ __(
							'Center opens as a modal; every other position is a floating slide-in.',
							'blocklane'
						) }
						id="blocklane-popup-position"
					>
						<AlignmentMatrixControl
							label={ __( 'Position', 'blocklane' ) }
							value={ slugToMatrix( settings.position ) }
							onChange={ ( next ) =>
								update( { position: matrixToSlug( next ) } )
							}
						/>
					</BaseControl>
				</ToolsPanelItem>

				<ToolsPanelItem
					hasValue={ () => !! settings.maxWidth }
					label={ __( 'Max width', 'blocklane' ) }
					onDeselect={ () => update( { maxWidth: '' } ) }
					isShownByDefault
					panelId="blocklane-popup-main"
				>
					<LengthControl
						label={ __( 'Max width', 'blocklane' ) }
						help={ __(
							'Caps the popup card’s width — the canvas follows, so you design at popup size. Empty: content width when centered, fit-content in corners.',
							'blocklane'
						) }
						value={ settings.maxWidth || '' }
						onChange={ ( maxWidth ) => update( { maxWidth } ) }
					/>
				</ToolsPanelItem>

				<ToolsPanelItem
					hasValue={ () => !! settings.minWidth }
					label={ __( 'Minimum screen width', 'blocklane' ) }
					onDeselect={ () => update( { minWidth: '' } ) }
					panelId="blocklane-popup-main"
				>
					<LengthControl
						label={ __( 'Minimum screen width', 'blocklane' ) }
						help={ __(
							'Only show on screens at least this wide. 0 shows everywhere.',
							'blocklane'
						) }
						value={ settings.minWidth || '' }
						onChange={ ( minWidth ) => update( { minWidth } ) }
					/>
				</ToolsPanelItem>

				<ToolsPanelItem
					hasValue={ () =>
						! ( settings.closable ?? true ) ||
						! ( settings.showCloseButton ?? true )
					}
					label={ __( 'Closing', 'blocklane' ) }
					onDeselect={ () =>
						update( { closable: true, showCloseButton: true } )
					}
					panelId="blocklane-popup-main"
				>
					<VStack spacing={ 4 }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Visitors can close it', 'blocklane' ) }
							checked={ settings.closable ?? true }
							onChange={ () =>
								update( {
									closable: ! ( settings.closable ?? true ),
								} )
							}
						/>
						{ ( settings.closable ?? true ) && (
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Show close button', 'blocklane' ) }
								checked={ settings.showCloseButton ?? true }
								onChange={ () =>
									update( {
										showCloseButton: ! (
											settings.showCloseButton ?? true
										),
									} )
								}
							/>
						) }
					</VStack>
				</ToolsPanelItem>
			</ToolsPanel>
		</Section>
	);
}

function TriggerSection() {
	const [ settings, update ] = usePopupSettings();
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const trigger = settings.trigger || SLICE_DEFAULTS.trigger;
	const frequency = settings.frequency || SLICE_DEFAULTS.frequency;

	return (
		<Section
			name="blocklane-popup-trigger"
			title={ __( 'Trigger', 'blocklane' ) }
		>
			<ToolsPanel
				label={ __( 'Trigger', 'blocklane' ) }
				dropdownMenuProps={ dropdownMenuProps }
				resetAll={ () =>
					update( {
						trigger: { ...SLICE_DEFAULTS.trigger },
						frequency: { ...SLICE_DEFAULTS.frequency },
					} )
				}
				panelId="blocklane-popup-trigger"
			>
				<ToolsPanelItem
					hasValue={ () =>
						trigger.type !== 'time' || trigger.value > 0
					}
					label={ __( 'Show when', 'blocklane' ) }
					onDeselect={ () =>
						update( { trigger: { ...SLICE_DEFAULTS.trigger } } )
					}
					isShownByDefault
					panelId="blocklane-popup-trigger"
				>
					<VStack spacing={ 4 }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Show when', 'blocklane' ) }
							options={ triggerOptions( trigger.type ) }
							value={ trigger.type }
							onChange={ ( type ) =>
								// Write each type's own default value into
								// the store on switch. Carrying the previous
								// type's value across (time's 0 riding into
								// a percentage) stored 0 while the control
								// showed 50 — and the front end fired the
								// popup on page load.
								update( {
									trigger: {
										type,
										value: triggerRow( type ).default || 0,
									},
								} )
							}
							help={ triggerHelp( trigger.type ) }
						/>
						{ 'seconds' === triggerRow( trigger.type ).control && (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Seconds on page', 'blocklane' ) }
								min={ 0 }
								max={ 120 }
								value={ trigger.value ?? 0 }
								onChange={ ( value ) =>
									update( {
										trigger: { ...trigger, value },
									} )
								}
							/>
						) }
						{ 'percent' === triggerRow( trigger.type ).control && (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Page scrolled (%)', 'blocklane' ) }
								min={ 1 }
								max={ 100 }
								value={ trigger.value ?? 50 }
								onChange={ ( value ) =>
									update( {
										trigger: { ...trigger, value },
									} )
								}
							/>
						) }
					</VStack>
				</ToolsPanelItem>

				<ToolsPanelItem
					hasValue={ () =>
						( frequency.seen ?? 0 ) !== 0 ||
						( frequency.dismissed ?? 7 ) !== 7
					}
					label={ __( 'Frequency', 'blocklane' ) }
					onDeselect={ () =>
						update( { frequency: { ...SLICE_DEFAULTS.frequency } } )
					}
					panelId="blocklane-popup-trigger"
				>
					<VStack spacing={ 4 }>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Days to hide after seen',
								'blocklane'
							) }
							help={ __(
								'0 shows it on every visit until dismissed.',
								'blocklane'
							) }
							min={ 0 }
							max={ 365 }
							value={ frequency.seen ?? 0 }
							onChange={ ( seen ) =>
								update( {
									frequency: { ...frequency, seen },
								} )
							}
						/>
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Days to hide after dismissed',
								'blocklane'
							) }
							help={ __(
								'0 ignores dismissals entirely.',
								'blocklane'
							) }
							min={ 0 }
							max={ 365 }
							value={ frequency.dismissed ?? 7 }
							onChange={ ( dismissed ) =>
								update( {
									frequency: { ...frequency, dismissed },
								} )
							}
						/>
					</VStack>
				</ToolsPanelItem>
			</ToolsPanel>
		</Section>
	);
}

function AnimationSection() {
	const [ settings, update ] = usePopupSettings();
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const animation = settings.animation || SLICE_DEFAULTS.animation;

	return (
		<Section
			name="blocklane-popup-animation"
			title={ __( 'Animation', 'blocklane' ) }
		>
			<ToolsPanel
				label={ __( 'Animation', 'blocklane' ) }
				dropdownMenuProps={ dropdownMenuProps }
				resetAll={ () =>
					update( { animation: { ...SLICE_DEFAULTS.animation } } )
				}
				panelId="blocklane-popup-animation"
			>
				<ToolsPanelItem
					hasValue={ () => !! animation.type }
					label={ __( 'Open animation', 'blocklane' ) }
					onDeselect={ () =>
						update( { animation: { ...SLICE_DEFAULTS.animation } } )
					}
					panelId="blocklane-popup-animation"
				>
					<VStack spacing={ 4 }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Open animation', 'blocklane' ) }
							options={ ANIMATION_OPTIONS }
							value={ animation.type || '' }
							onChange={ ( type ) =>
								update( { animation: { ...animation, type } } )
							}
						/>
						{ !! animation.type && (
							<>
								<RangeControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Duration', 'blocklane' ) }
									help={ __( 'Seconds', 'blocklane' ) }
									min={ 0.1 }
									max={ 5 }
									step={ 0.1 }
									value={ animation.duration ?? 0.4 }
									onChange={ ( duration ) =>
										update( {
											animation: {
												...animation,
												duration,
											},
										} )
									}
								/>
								<RangeControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Delay', 'blocklane' ) }
									help={ __( 'Seconds', 'blocklane' ) }
									min={ 0 }
									max={ 5 }
									step={ 0.1 }
									value={ animation.delay ?? 0 }
									onChange={ ( delay ) =>
										update( {
											animation: {
												...animation,
												delay,
											},
										} )
									}
								/>
							</>
						) }
					</VStack>
				</ToolsPanelItem>
			</ToolsPanel>
		</Section>
	);
}

function RulesSection() {
	const [ settings, update ] = usePopupSettings();
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const rules = settings.rules || { match: 'any', items: [] };
	const items = rules.items || [];

	const setItem = ( index, patch ) => {
		const next = items.map( ( item, i ) =>
			i === index ? { ...item, ...patch } : item
		);
		update( { rules: { ...rules, items: next } } );
	};

	return (
		<Section
			name="blocklane-popup-rules"
			title={ __( 'Display Rules', 'blocklane' ) }
		>
			<ToolsPanel
				label={ __( 'Display Rules', 'blocklane' ) }
				dropdownMenuProps={ dropdownMenuProps }
				resetAll={ () =>
					update( {
						rules: { match: 'any', items: [] },
						showOnLockScreen: false,
					} )
				}
				panelId="blocklane-popup-rules"
			>
				<ToolsPanelItem
					hasValue={ () => items.length > 0 }
					label={ __( 'Rules', 'blocklane' ) }
					onDeselect={ () =>
						update( { rules: { match: 'any', items: [] } } )
					}
					panelId="blocklane-popup-rules"
				>
					<VStack spacing={ 4 }>
						{ items.length > 1 && (
							<ToggleGroupControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								isBlock
								label={ __( 'Show when', 'blocklane' ) }
								value={ rules.match || 'any' }
								onChange={ ( match ) =>
									update( { rules: { ...rules, match } } )
								}
							>
								<ToggleGroupControlOption
									value="any"
									label={ __( 'Any rule', 'blocklane' ) }
								/>
								<ToggleGroupControlOption
									value="all"
									label={ __( 'All rules', 'blocklane' ) }
								/>
							</ToggleGroupControl>
						) }
						{ items.map( ( item, index ) => (
							<VStack
								key={ index }
								spacing={ 2 }
								className="blocklane-popup-rule"
							>
								<HStack>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Condition', 'blocklane' ) }
										hideLabelFromVision
										options={ conditionOptions(
											item.condition || 'everywhere'
										) }
										value={ item.condition || 'everywhere' }
										onChange={ ( condition ) =>
											setItem( index, {
												condition,
												qualifier:
													defaultQualifier(
														condition
													) || 'is',
												value: '',
											} )
										}
									/>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Qualifier', 'blocklane' ) }
										hideLabelFromVision
										options={ qualifierOptions(
											item.condition,
											item.qualifier || 'is'
										) }
										value={ item.qualifier || 'is' }
										onChange={ ( qualifier ) =>
											setItem( index, { qualifier } )
										}
									/>
									<Button
										size="small"
										icon="no-alt"
										label={ __(
											'Remove rule',
											'blocklane'
										) }
										onClick={ () =>
											update( {
												rules: {
													...rules,
													items: items.filter(
														( _, i ) => i !== index
													),
												},
											} )
										}
									/>
								</HStack>
								{ isValueless( item.condition ) ? (
									<Text variant="muted" size={ 12 } as="p">
										{ __(
											'This condition needs no value — it matches on its own.',
											'blocklane'
										) }
									</Text>
								) : (
									<FormTokenField
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'IDs, slugs, or paths',
											'blocklane'
										) }
										value={
											item.value
												? item.value
														.split( ',' )
														.map( ( v ) =>
															v.trim()
														)
														.filter( Boolean )
												: []
										}
										onChange={ ( tokens ) =>
											setItem( index, {
												value: tokens.join( ',' ),
											} )
										}
									/>
								) }
							</VStack>
						) ) }
						<Button
							variant="secondary"
							onClick={ () =>
								update( {
									rules: {
										...rules,
										items: [
											...items,
											{
												condition: 'page',
												qualifier: 'is',
												value: '',
											},
										],
									},
								} )
							}
						>
							{ __( 'Add rule', 'blocklane' ) }
						</Button>
						{ ! items.length && (
							<Text variant="muted" size={ 12 } as="p">
								{ __(
									'No rules — the popup shows everywhere.',
									'blocklane'
								) }
							</Text>
						) }
					</VStack>
				</ToolsPanelItem>

				<ToolsPanelItem
					hasValue={ () => !! settings.showOnLockScreen }
					label={ __( 'Coming Soon & Maintenance', 'blocklane' ) }
					onDeselect={ () => update( { showOnLockScreen: false } ) }
					panelId="blocklane-popup-rules"
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show while the site is locked',
							'blocklane'
						) }
						help={ __(
							'By default, popups never appear on the Coming Soon or Maintenance screen. Enable to allow this popup there — display rules still apply.',
							'blocklane'
						) }
						checked={ !! settings.showOnLockScreen }
						onChange={ () =>
							update( {
								showOnLockScreen: ! settings.showOnLockScreen,
							} )
						}
					/>
				</ToolsPanelItem>
			</ToolsPanel>
		</Section>
	);
}

/**
 * A Preview button in the Summary area: core hides its own Preview UI for
 * non-viewable post types, so this is the popup's way to the front —
 * saves, then force-opens via the capability-gated preview param
 * (bypasses triggers, rules, cookies, and draft status).
 */
function usePopupPreview() {
	const { postId, homeUrl, isSaving } = useSelect( ( select ) => {
		const { getCurrentPostId, isSavingPost } = select( editorStore );
		return {
			postId: getCurrentPostId(),
			homeUrl: select( 'core' ).getEntityRecord(
				'root',
				'__unstableBase'
			)?.home,
			isSaving: isSavingPost(),
		};
	}, [] );
	const { savePost } = useDispatch( editorStore );

	if ( ! postId || ! homeUrl ) {
		return null;
	}

	return {
		isSaving,
		open: async () => {
			// Open the window SYNCHRONOUSLY (inside the click's user
			// activation) and navigate it once the save resolves — a slow
			// save exhausts the activation and the popup blocker would eat
			// the preview tab. Close the placeholder if the save fails.
			const previewWindow = window.open( '', '_blank' );
			try {
				await savePost();
			} catch ( e ) {
				previewWindow?.close();
				return;
			}
			const url = `${ homeUrl }/?blocklane_popup_preview=${ postId }`;
			if ( previewWindow ) {
				previewWindow.location = url;
			} else {
				// Blocked even synchronously — fall back to the old path.
				window.open( url, '_blank' );
			}
		},
	};
}

/**
 * Fill the native Preview dropdown (header) where the slot exists — core
 * hides that dropdown for non-viewable post types on some versions, so the
 * Summary button below stays the guaranteed path.
 */
function PreviewMenuItem() {
	const preview = usePopupPreview();
	if ( ! preview || ! PluginPreviewMenuItem ) {
		return null;
	}
	return (
		<PluginPreviewMenuItem icon={ external } onClick={ preview.open }>
			{
				// Core's exact string (default domain) so the item reads —
				// and translates — identically to Pages.

				__( 'Preview in new tab' )
			}
		</PluginPreviewMenuItem>
	);
}

function PreviewButton() {
	const preview = usePopupPreview();
	if ( ! preview ) {
		return null;
	}

	return (
		<PluginPostStatusInfo>
			<Button
				variant="secondary"
				icon={ external }
				iconPosition="right"
				isBusy={ preview.isSaving }
				style={ { width: '100%', justifyContent: 'center' } }
				onClick={ preview.open }
			>
				{ __( 'Preview in new tab' ) }
			</Button>
		</PluginPostStatusInfo>
	);
}

function PopupSettings() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	if ( POST_TYPE !== postType ) {
		return null;
	}
	return (
		<>
			<PreviewMenuItem />
			<PreviewButton />
			<PopupSection />
			<TriggerSection />
			<AnimationSection />
			<RulesSection />
		</>
	);
}

registerPlugin( 'blocklane-pro-popups', { render: PopupSettings } );
