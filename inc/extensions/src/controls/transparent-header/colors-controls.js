/**
 * The group's transparent-state color picker — Transparent Background only,
 * rendered into the block's Color panel via core's
 * ColorGradientSettingsDropdown — the hover-color precedent: colors live
 * where core puts colors, never scattered into a feature panel.
 *
 * The background stays on the group because it IS the group's background at
 * the top of the page (a scrim). Text color left for the blocks that carry
 * text (./blocks.js — navigation, site title) when the blanket delivery was
 * retired: one rule painting every descendant forced a denylist of
 * self-painting containers that could never be complete.
 *
 * Values store in the flat prefixed attribute (the extension's shipped
 * idiom), palette picks encoded as `var:preset|color|{slug}` via the shared
 * preset-color helpers.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import {
	InspectorControls,
	useSettings,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';

import {
	encodePresetColor,
	decodePresetColor,
} from '../../components/preset-color';

const SLOTS = [
	{
		attribute: 'blocklaneProTransparentBackground',
		label: __( 'Transparent Background', 'blocklane' ),
	},
];

function TransparentHeaderColors( { attributes, setAttributes, clientId } ) {
	const colorGradientSettings = useMultipleOriginColorsAndGradients();
	// Palette entries OR the custom picker count as color UI — a palette-less
	// theme with custom colors enabled still gets pickers (hover-color's gate).
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
			{ SLOTS.map( ( { attribute, label } ) => (
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
							// Core's Color-panel Reset All chains these
							// filters over the accumulated attributes.
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

export default TransparentHeaderColors;
