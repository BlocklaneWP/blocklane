/**
 * DimensionControl
 *
 * A unit-or-number value control with a companion slider, modelled on Gutenberg's
 * HeightControl. Shared by advanced-grid and advanced-group.
 *
 * @param {Object}        props
 * @param {Function}      props.onChange    Called with the new value.
 * @param {string}        props.label       Control label.
 * @param {string}        [props.help]      Help text below the control.
 * @param {string[]}      [props.units]     Allowed units (unit type only).
 * @param {string|number} props.value       Current value.
 * @param {string}        [props.type]      'unit' (default) or 'number'.
 * @param {number}        [props.min]       Minimum (default 0).
 * @param {number}        [props.max]       Maximum (default 100).
 * @param {number}        [props.step]      Step (default 1).
 * @param {string}        [props.className] Extra class.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import {
	BaseControl,
	RangeControl,
	Flex,
	FlexItem,
	__experimentalUseCustomUnits as useCustomUnits,
	__experimentalUnitControl as UnitControl,
	__experimentalNumberControl as NumberControl,
	__experimentalParseQuantityAndUnitFromRawValue as parseQuantityAndUnitFromRawValue,
} from '@wordpress/components';
import { useSettings } from '@wordpress/block-editor';

// Sensible slider max/step per unit (used unless overridden via props).
const RANGE_BY_UNIT = {
	px: { max: 1000, step: 1 },
	'%': { max: 100, step: 1 },
	vw: { max: 100, step: 1 },
	vh: { max: 100, step: 1 },
	em: { max: 50, step: 0.1 },
	rem: { max: 50, step: 0.1 },
	deg: { max: 360, step: 1 },
};

export default function DimensionControl( {
	onChange,
	label,
	help,
	units,
	value,
	type = 'unit',
	min = 0,
	max = 100,
	step = 1,
	className,
} ) {
	const instanceId = useInstanceId( DimensionControl );
	const helpId = help ? `dimension-control-help-${ instanceId }` : undefined;
	const rangeValue = parseFloat( value );

	// Hooks run unconditionally (before the number/unit branch below).
	const [ themeUnits ] = useSettings( 'spacing.units' );
	const allowed =
		units && themeUnits
			? units.filter( ( unit ) => themeUnits.includes( unit ) )
			: units || themeUnits;
	const baseUnits = useCustomUnits( {
		availableUnits: allowed || [ '%', 'px', 'em', 'rem', 'vh', 'vw' ],
	} );
	const availableUnits = units?.includes( 'deg' )
		? [
				...baseUnits,
				{
					value: 'deg',
					label: 'deg',
					a11yLabel: __( 'Degrees', 'blocklane' ),
					step: 1,
				},
			]
		: baseUnits;
	const parsed = useMemo(
		() => parseQuantityAndUnitFromRawValue( value ),
		[ value ]
	);
	const selectedUnit = parsed[ 1 ] || availableUnits[ 0 ]?.value || 'px';

	const classes = [ 'components-dimension-control', className ]
		.filter( Boolean )
		.join( ' ' );

	const renderHelp = () =>
		help ? (
			<p id={ helpId } className="components-base-control__help">
				{ help }
			</p>
		) : null;

	if ( type === 'number' ) {
		return (
			<fieldset className={ classes } aria-describedby={ helpId }>
				<BaseControl.VisualLabel as="legend">
					{ label || __( 'Dimension', 'blocklane' ) }
				</BaseControl.VisualLabel>
				<Flex>
					<FlexItem isBlock>
						<NumberControl
							value={ value }
							onChange={ ( next ) => {
								if ( next !== undefined && next !== '' ) {
									onChange( next );
								}
							} }
							min={ min }
							max={ max }
							step={ step }
							size="__unstable-large"
						/>
					</FlexItem>
					<FlexItem isBlock>
						<RangeControl
							__next40pxDefaultSize
							value={ rangeValue }
							min={ min }
							max={ max }
							step={ step }
							withInputField={ false }
							onChange={ onChange }
							__nextHasNoMarginBottom
						/>
					</FlexItem>
				</Flex>
				{ renderHelp() }
			</fieldset>
		);
	}

	// Only emit a unit value once there's an actual quantity.
	const onUnitValueChange = ( raw ) => {
		const [ quantity ] = parseQuantityAndUnitFromRawValue( raw );
		if ( quantity ) {
			onChange( raw );
		}
	};

	// Smooth the number when switching unit types (assume a 16px root).
	const onUnitChange = ( newUnit ) => {
		const [ current, currentUnit ] =
			parseQuantityAndUnitFromRawValue( value );
		if ( [ 'em', 'rem' ].includes( newUnit ) && currentUnit === 'px' ) {
			onChange( ( current / 16 ).toFixed( 2 ) + newUnit );
		} else if (
			[ 'em', 'rem' ].includes( currentUnit ) &&
			newUnit === 'px'
		) {
			onChange( Math.round( current * 16 ) + newUnit );
		} else if ( [ 'vh', 'vw', '%' ].includes( newUnit ) && current > 100 ) {
			onChange( 100 + newUnit );
		}
	};

	const sliderMax =
		max !== 100 ? max : ( RANGE_BY_UNIT[ selectedUnit ]?.max ?? 100 );
	const sliderStep =
		step !== 1 ? step : ( RANGE_BY_UNIT[ selectedUnit ]?.step ?? 0.1 );

	return (
		<fieldset className={ classes } aria-describedby={ helpId }>
			<BaseControl.VisualLabel as="legend">
				{ label || __( 'Dimension', 'blocklane' ) }
			</BaseControl.VisualLabel>
			<Flex>
				<FlexItem isBlock>
					<UnitControl
						value={ value }
						units={ availableUnits }
						onChange={ onUnitValueChange }
						onUnitChange={ onUnitChange }
						min={ min }
						max={ max }
						size="__unstable-large"
					/>
				</FlexItem>
				<FlexItem isBlock>
					<RangeControl
						__next40pxDefaultSize
						value={ rangeValue }
						min={ min }
						max={ sliderMax }
						step={ sliderStep }
						withInputField={ false }
						onChange={ ( next ) =>
							onChange( [ next, selectedUnit ].join( '' ) )
						}
						__nextHasNoMarginBottom
					/>
				</FlexItem>
			</Flex>
			{ renderHelp() }
		</fieldset>
	);
}
