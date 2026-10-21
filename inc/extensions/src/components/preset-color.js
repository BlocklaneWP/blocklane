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
 * The raw colors the canvas shows: the CSS named colors, transparent and
 * currentcolor. With hex and a preset variable, this is everything core's
 * rule (safecss_filter_attr) admits that the editor's pickers can store.
 */
const NAMED_COLORS = new Set( [
	'aliceblue',
	'antiquewhite',
	'aqua',
	'aquamarine',
	'azure',
	'beige',
	'bisque',
	'black',
	'blanchedalmond',
	'blue',
	'blueviolet',
	'brown',
	'burlywood',
	'cadetblue',
	'chartreuse',
	'chocolate',
	'coral',
	'cornflowerblue',
	'cornsilk',
	'crimson',
	'cyan',
	'darkblue',
	'darkcyan',
	'darkgoldenrod',
	'darkgray',
	'darkgreen',
	'darkgrey',
	'darkkhaki',
	'darkmagenta',
	'darkolivegreen',
	'darkorange',
	'darkorchid',
	'darkred',
	'darksalmon',
	'darkseagreen',
	'darkslateblue',
	'darkslategray',
	'darkslategrey',
	'darkturquoise',
	'darkviolet',
	'deeppink',
	'deepskyblue',
	'dimgray',
	'dimgrey',
	'dodgerblue',
	'firebrick',
	'floralwhite',
	'forestgreen',
	'fuchsia',
	'gainsboro',
	'ghostwhite',
	'gold',
	'goldenrod',
	'gray',
	'green',
	'greenyellow',
	'grey',
	'honeydew',
	'hotpink',
	'indianred',
	'indigo',
	'ivory',
	'khaki',
	'lavender',
	'lavenderblush',
	'lawngreen',
	'lemonchiffon',
	'lightblue',
	'lightcoral',
	'lightcyan',
	'lightgoldenrodyellow',
	'lightgray',
	'lightgreen',
	'lightgrey',
	'lightpink',
	'lightsalmon',
	'lightseagreen',
	'lightskyblue',
	'lightslategray',
	'lightslategrey',
	'lightsteelblue',
	'lightyellow',
	'lime',
	'limegreen',
	'linen',
	'magenta',
	'maroon',
	'mediumaquamarine',
	'mediumblue',
	'mediumorchid',
	'mediumpurple',
	'mediumseagreen',
	'mediumslateblue',
	'mediumspringgreen',
	'mediumturquoise',
	'mediumvioletred',
	'midnightblue',
	'mintcream',
	'mistyrose',
	'moccasin',
	'navajowhite',
	'navy',
	'oldlace',
	'olive',
	'olivedrab',
	'orange',
	'orangered',
	'orchid',
	'palegoldenrod',
	'palegreen',
	'paleturquoise',
	'palevioletred',
	'papayawhip',
	'peachpuff',
	'peru',
	'pink',
	'plum',
	'powderblue',
	'purple',
	'rebeccapurple',
	'red',
	'rosybrown',
	'royalblue',
	'saddlebrown',
	'salmon',
	'sandybrown',
	'seagreen',
	'seashell',
	'sienna',
	'silver',
	'skyblue',
	'slateblue',
	'slategray',
	'slategrey',
	'snow',
	'springgreen',
	'steelblue',
	'tan',
	'teal',
	'thistle',
	'tomato',
	'turquoise',
	'violet',
	'wheat',
	'white',
	'whitesmoke',
	'yellow',
	'yellowgreen',
	'transparent',
	'currentcolor',
] );

/** #rgb, #rgba, #rrggbb or #rrggbbaa. */
const HEX = /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

/** A preset variable written out, as a hex-free stored custom color. */
const PRESET_VAR = /^var\(--wp--preset--color--[A-Za-z0-9_-]+\)$/;

/**
 * The stored form as a CSS value for inline custom properties: preset slugs
 * become `var(--wp--preset--color--{slug})` (the slug cleaned as the
 * server's sanitize_html_class() cleans it), and a raw color passes only
 * when it is hex, a preset variable or a named color. Everything else (url(),
 * expression(), rgb(), hsl(), a `;`, a quote, a brace) is refused, as the
 * server-side resolver (blocklane_pro_ext_resolve_preset_color()) refuses it,
 * so the canvas never shows a color the front end drops.
 *
 * @param {string|undefined} stored Stored value.
 * @return {string|undefined} CSS value, or undefined when refused.
 */
export function presetColorToCss( stored ) {
	if ( ! stored || 'string' !== typeof stored ) {
		return undefined;
	}
	if ( stored.startsWith( PRESET_PREFIX ) ) {
		return `var(--wp--preset--color--${ stored
			.slice( PRESET_PREFIX.length )
			.replace( /[^A-Za-z0-9_-]/g, '' ) })`;
	}
	if (
		HEX.test( stored ) ||
		PRESET_VAR.test( stored ) ||
		NAMED_COLORS.has( stored.toLowerCase() )
	) {
		return stored;
	}
	return undefined;
}
