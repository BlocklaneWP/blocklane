/**
 * Preset-color encode/decode shared by extensions that store palette picks
 * in core's `var:preset|color|{slug}` form (hover-color's storage idiom,
 * lifted here verbatim for reuse — transparent-header state colors ride the
 * same encoding). Custom colors store as-is.
 *
 * @package
 */

export const PRESET_PREFIX = 'var:preset|color|';

/**
 * Encode a picked color for storage: palette colors as
 * `var:preset|color|{slug}`, custom colors as-is.
 *
 * @param {string|undefined} value     Picked CSS color.
 * @param {Object[]}         allColors Flattened palette (objects with slug + color).
 * @return {string|undefined} Stored form.
 */
export function encodePresetColor( value, allColors ) {
	if ( ! value ) {
		return undefined;
	}
	const match = allColors.find( ( c ) => c.color === value );
	return match?.slug ? `${ PRESET_PREFIX }${ match.slug }` : value;
}

/**
 * Decode a stored color to a displayable CSS color for the picker swatch
 * (preset slugs resolved against the palette).
 *
 * @param {string|undefined} stored    Stored value.
 * @param {Object[]}         allColors Flattened palette.
 * @return {string|undefined} CSS color.
 */
export function decodePresetColor( stored, allColors ) {
	if ( ! stored ) {
		return undefined;
	}
	if ( stored.startsWith( PRESET_PREFIX ) ) {
		const slug = stored.slice( PRESET_PREFIX.length );
		return allColors.find( ( c ) => c.slug === slug )?.color;
	}
	return stored;
}

/**
 * The stored form as a CSS value for inline custom properties: preset slugs
 * become `var(--wp--preset--color--{slug})`, raw colors pass through.
 * Mirrors the server-side resolver — the two must agree or the canvas and
 * the front end drift.
 *
 * @param {string|undefined} stored Stored value.
 * @return {string|undefined} CSS value.
 */
export function presetColorToCss( stored ) {
	if ( ! stored ) {
		return undefined;
	}
	if ( stored.startsWith( PRESET_PREFIX ) ) {
		return `var(--wp--preset--color--${ stored.slice(
			PRESET_PREFIX.length
		) })`;
	}
	return stored;
}
