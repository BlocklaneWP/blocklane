/**
 * The dimension control: one size field for the block editor's sidebar.
 *
 * An author sets one size with it, either a length with a CSS unit (such as
 * `24px` or `1.5rem`) or a plain number (such as a column count). It renders
 * a fieldset whose legend is the label, a text field beside a slider, and an
 * optional help sentence under them. In unit mode the text field is core's
 * unit control; in number mode it is core's number control.
 *
 * Callers:
 * - the transparent-header extension's top offset (free and Pro), with the
 *   units `px`, `em` and `rem`, a help sentence, and `'0px'` when nothing is
 *   set;
 * - in Pro only: an icon's size and spacing, a grid's minimum column width
 *   and column count (number mode), and a sticky top offset (with `max`
 *   500).
 *
 * The unit conversion, the per-unit slider table and the legend-and-two-inputs
 * layout follow WordPress core's HeightControl (GPL-2.0-or-later).
 *
 * Props:
 * - `onChange`  {Function}           Required. Called with each new value.
 * - `label`     {string}             The visible label. Default: none, shown
 *                                    as "Dimension".
 * - `help`      {string}             One sentence shown under the inputs.
 *                                    Default: none.
 * - `units`     {string[]}           The units this caller allows (unit
 *                                    mode). Default: none.
 * - `value`     {string|number}      The current value. Default: none.
 * - `type`      {'unit'|'number'}    Which mode to render. Default: 'unit'.
 * - `min`       {number}             Lower bound. Default: 0.
 * - `max`       {number}             Upper bound. Default: 100, which in unit
 *                                    mode means "use the unit's slider limit".
 * - `step`      {number}             Slider step. Default: 1, which in unit
 *                                    mode means "use the unit's slider step".
 * - `className` {string}             Extra classes for the root. Default:
 *                                    none.
 *
 * @package
 */

import {
	BaseControl,
	Flex,
	FlexItem,
	RangeControl,
	__experimentalNumberControl as NumberControl,
	__experimentalUnitControl as UnitControl,
	__experimentalUseCustomUnits as useCustomUnits,
	__experimentalParseQuantityAndUnitFromRawValue as parseQuantityAndUnitFromRawValue,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { useSettings } from '@wordpress/block-editor';

/**
 * The defaults for the props that have one. A prop that is missing or
 * `undefined` takes the value here.
 */
export const DIMENSION_CONTROL_DEFAULTS = Object.freeze( {
	type: 'unit',
	min: 0,
	max: 100,
	step: 1,
} );

/**
 * The units offered when neither the caller nor the theme gives a list.
 */
const FALLBACK_UNITS = Object.freeze( [ '%', 'px', 'em', 'rem', 'vh', 'vw' ] );

/**
 * Units measured against the font size.
 */
const FONT_RELATIVE_UNITS = Object.freeze( [ 'em', 'rem' ] );

/**
 * Percent and the viewport units. A switch to one of these caps a quantity
 * above 100, and they share one slider range.
 */
const PERCENT_AND_VIEWPORT_UNITS = Object.freeze( [
	'%',
	'vw',
	'vh',
	'vi',
	'vb',
	'vmin',
	'vmax',
	'svw',
	'svh',
	'svi',
	'svb',
	'svmin',
	'svmax',
	'lvw',
	'lvh',
	'lvi',
	'lvb',
	'lvmin',
	'lvmax',
	'dvw',
	'dvh',
	'dvi',
	'dvb',
	'dvmin',
	'dvmax',
] );

/**
 * The slider limits, one entry per group of units that share them.
 */
const DIMENSION_CONTROL_SLIDER_GROUPS = Object.freeze( [
	Object.freeze( { units: FONT_RELATIVE_UNITS, max: 50, step: 0.1 } ),
	Object.freeze( { units: PERCENT_AND_VIEWPORT_UNITS, max: 100, step: 1 } ),
	Object.freeze( { units: Object.freeze( [ 'px' ] ), max: 1000, step: 1 } ),
] );

/**
 * The slider limits for a unit that no group lists.
 */
const SLIDER_FALLBACK = Object.freeze( { max: 100, step: 0.1 } );

/**
 * Returns the slider's maximum and step for one unit.
 *
 * @param {string} unit The selected unit.
 * @return {{max: number, step: number}} The limits of the group that lists
 *                                       the unit, or the fallback.
 */
function sliderLimitsFor( unit ) {
	const group = DIMENSION_CONTROL_SLIDER_GROUPS.find( ( entry ) =>
		entry.units.includes( unit )
	);
	return group ? { max: group.max, step: group.step } : SLIDER_FALLBACK;
}

/**
 * Builds the settings object: each default, replaced by its prop when the
 * prop is given (an explicit `undefined` keeps the default).
 *
 * @param {Object} props The component's props.
 * @return {{type: string, min: number, max: number, step: number}} Settings.
 */
function settingsFrom( props ) {
	return Object.fromEntries(
		Object.entries( DIMENSION_CONTROL_DEFAULTS ).map(
			( [ key, fallback ] ) => [
				key,
				props[ key ] === undefined ? fallback : props[ key ],
			]
		)
	);
}

/**
 * Chooses the unit values to offer from the caller's list and the theme's.
 * A list counts as present whenever it is an array, even an empty one.
 *
 * @param {string[]|undefined} callerUnits The caller's `units` prop.
 * @param {string[]|undefined} themeUnits  The theme's `spacing.units` setting.
 * @return {string[]} The unit values to hand to core's custom-units hook.
 */
function chooseUnitValues( callerUnits, themeUnits ) {
	const hasCaller = Array.isArray( callerUnits );
	const hasTheme = Array.isArray( themeUnits );
	if ( hasCaller && hasTheme ) {
		return callerUnits.filter( ( unit ) => themeUnits.includes( unit ) );
	}
	if ( hasCaller ) {
		return callerUnits;
	}
	if ( hasTheme ) {
		return themeUnits;
	}
	return FALLBACK_UNITS;
}

/**
 * Returns the value to emit when the author switches from one unit to
 * another, or `undefined` when nothing is emitted.
 *
 * @param {string|number|undefined} value  The current value.
 * @param {string}                  toUnit The unit the author picked.
 * @return {string|undefined} The converted value, if any.
 */
function convertForUnitSwitch( value, toUnit ) {
	const parsed = parseQuantityAndUnitFromRawValue( value );
	const quantity = parsed[ 0 ];
	if ( ! Number.isFinite( quantity ) ) {
		return undefined;
	}
	const fromUnit = parsed[ 1 ];
	if ( fromUnit === 'px' && FONT_RELATIVE_UNITS.includes( toUnit ) ) {
		return `${ ( quantity / 16 ).toFixed( 2 ) }${ toUnit }`;
	}
	if ( FONT_RELATIVE_UNITS.includes( fromUnit ) && toUnit === 'px' ) {
		return `${ Math.round( quantity * 16 ) }px`;
	}
	if ( PERCENT_AND_VIEWPORT_UNITS.includes( toUnit ) && quantity > 100 ) {
		return `100${ toUnit }`;
	}
	return undefined;
}

export default function DimensionControl( props ) {
	const settings = settingsFrom( props );

	// Every hook runs on every render, in both modes.
	const [ themeUnits ] = useSettings( 'spacing.units' );
	const units = useCustomUnits( {
		availableUnits: chooseUnitValues( props.units, themeUnits ),
	} );
	const helpId = useInstanceId(
		DimensionControl,
		'components-dimension-control__help'
	);

	const label = props.label || __( 'Dimension', 'blocklane' );
	const hasHelp = typeof props.help === 'string' && props.help !== '';
	const [ quantity, valueUnit ] = parseQuantityAndUnitFromRawValue(
		props.value
	);
	const rootClass = [ 'components-dimension-control', props.className ]
		.filter( Boolean )
		.join( ' ' );

	let inputs;
	if ( settings.type === 'number' ) {
		// The number field emits anything but an empty value; zero included.
		const onNumberChange = ( next ) => {
			if ( next === undefined || next === '' ) {
				return;
			}
			props.onChange( next );
		};
		// The slider emits the number itself.
		const onSlide = ( next ) => {
			if ( typeof next === 'number' ) {
				props.onChange( next );
			}
		};
		inputs = (
			<>
				<FlexItem isBlock>
					<NumberControl
						__next40pxDefaultSize
						label={ label }
						hideLabelFromVision
						value={ props.value }
						min={ settings.min }
						max={ settings.max }
						step={ settings.step }
						onChange={ onNumberChange }
					/>
				</FlexItem>
				<FlexItem isBlock>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ label }
						hideLabelFromVision
						withInputField={ false }
						value={ quantity }
						min={ settings.min }
						max={ settings.max }
						step={ settings.step }
						onChange={ onSlide }
					/>
				</FlexItem>
			</>
		);
	} else {
		// The value's own unit, else the first offered unit, else px.
		const selectedUnit = valueUnit ?? units[ 0 ]?.value ?? 'px';
		const limits = sliderLimitsFor( selectedUnit );
		// A max of 100 or a step of 1 reads as "not given".
		const max = settings.max !== 100 ? settings.max : limits.max;
		const step = settings.step !== 1 ? settings.step : limits.step;

		// Typing emits the raw value only when it holds a nonzero number.
		const onTyped = ( next ) => {
			const [ typedQuantity ] = parseQuantityAndUnitFromRawValue( next );
			if ( Number.isFinite( typedQuantity ) && typedQuantity !== 0 ) {
				props.onChange( next );
			}
		};
		const onUnitChange = ( nextUnit ) => {
			const converted = convertForUnitSwitch( props.value, nextUnit );
			if ( converted !== undefined ) {
				props.onChange( converted );
			}
		};
		// The slider emits its number joined to the selected unit.
		const onSlide = ( next ) => {
			if ( typeof next === 'number' ) {
				props.onChange( `${ next }${ selectedUnit }` );
			}
		};
		inputs = (
			<>
				<FlexItem isBlock>
					<UnitControl
						__next40pxDefaultSize
						label={ label }
						hideLabelFromVision
						value={ props.value }
						units={ units }
						min={ settings.min }
						max={ max }
						onChange={ onTyped }
						onUnitChange={ onUnitChange }
					/>
				</FlexItem>
				<FlexItem isBlock>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ label }
						hideLabelFromVision
						withInputField={ false }
						value={ quantity }
						min={ settings.min }
						max={ max }
						step={ step }
						onChange={ onSlide }
					/>
				</FlexItem>
			</>
		);
	}

	return (
		<fieldset
			className={ rootClass }
			aria-describedby={ hasHelp ? helpId : undefined }
		>
			<BaseControl.VisualLabel as="legend">
				{ label }
			</BaseControl.VisualLabel>
			<Flex>{ inputs }</Flex>
			{ hasHelp && (
				<p id={ helpId } className="components-base-control__help">
					{ props.help }
				</p>
			) }
		</fieldset>
	);
}
