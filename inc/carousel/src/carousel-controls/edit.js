/**
 * The carousel controls block's editor: the justification control in the
 * block toolbar, the settings in the sidebar, and an inert preview in the
 * canvas that draws what the server renders for the block.
 */

import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	JustifyContentControl,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	RangeControl,
	TextareaControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { createElement, useMemo } from '@wordpress/element';
import { Path, SVG } from '@wordpress/primitives';

import ICONS from '../../arrow-icons.json';

const NAV_TYPES = [ 'arrows', 'dots', 'bars', 'scrollbar', 'autoplay' ];
const JUSTIFICATIONS = [ 'left', 'center', 'right', 'stretch' ];
const WEIGHTS = [ 'regular', 'bold', 'fill' ];
const CHEVRON = ICONS.find( ( entry ) => 'chevron' === entry.name );
const FALLBACK_VIEWBOX = '0 0 256 256';

const MAX_PASTE_BYTES = 65536;
const MAX_PASTE_DEPTH = 256;

/*
 * The elements a pasted icon keeps, each with the attributes it keeps
 * (names in lower case), as the server's list has them.
 */
const SHARED_ATTRIBUTES = [
	'fill',
	'opacity',
	'stroke',
	'stroke-width',
	'transform',
];
const ALLOWED_ELEMENTS = new Map( [
	[ 'svg', [ 'class', 'fill', 'height', 'viewbox', 'width', 'xmlns' ] ],
	[
		'path',
		[
			...SHARED_ATTRIBUTES,
			'clip-rule',
			'd',
			'fill-rule',
			'stroke-linecap',
			'stroke-linejoin',
		],
	],
	[ 'circle', [ ...SHARED_ATTRIBUTES, 'cx', 'cy', 'r' ] ],
	[ 'ellipse', [ ...SHARED_ATTRIBUTES, 'cx', 'cy', 'rx', 'ry' ] ],
	[ 'g', [ ...SHARED_ATTRIBUTES, 'clip-path' ] ],
	[ 'line', [ ...SHARED_ATTRIBUTES, 'x1', 'x2', 'y1', 'y2' ] ],
	[ 'polygon', [ ...SHARED_ATTRIBUTES, 'points' ] ],
	[ 'polyline', [ ...SHARED_ATTRIBUTES, 'points' ] ],
	[
		'rect',
		[ ...SHARED_ATTRIBUTES, 'height', 'rx', 'ry', 'width', 'x', 'y' ],
	],
] );

/* The elements that draw something; a cleaned icon needs one of them. */
const DRAWING_ELEMENTS = new Set( [
	'path',
	'circle',
	'rect',
	'line',
	'polyline',
	'polygon',
	'ellipse',
] );

/*
 * React's names for the attributes kept. The namespace declaration is left
 * out: an inline SVG in an HTML page needs none.
 */
const REACT_NAMES = new Map( [
	[ 'class', 'className' ],
	[ 'clip-path', 'clipPath' ],
	[ 'clip-rule', 'clipRule' ],
	[ 'cx', 'cx' ],
	[ 'cy', 'cy' ],
	[ 'd', 'd' ],
	[ 'fill', 'fill' ],
	[ 'fill-rule', 'fillRule' ],
	[ 'height', 'height' ],
	[ 'opacity', 'opacity' ],
	[ 'points', 'points' ],
	[ 'r', 'r' ],
	[ 'rx', 'rx' ],
	[ 'ry', 'ry' ],
	[ 'stroke', 'stroke' ],
	[ 'stroke-linecap', 'strokeLinecap' ],
	[ 'stroke-linejoin', 'strokeLinejoin' ],
	[ 'stroke-width', 'strokeWidth' ],
	[ 'transform', 'transform' ],
	[ 'viewbox', 'viewBox' ],
	[ 'width', 'width' ],
	[ 'x', 'x' ],
	[ 'x1', 'x1' ],
	[ 'x2', 'x2' ],
	[ 'y', 'y' ],
	[ 'y1', 'y1' ],
	[ 'y2', 'y2' ],
] );

/* An ampersand that does not start one of XML's own references. */
const UNKNOWN_REFERENCE =
	/&(?!(?:amp|lt|gt|quot|apos|#[0-9]+|#x[0-9a-fA-F]+);)/;

/* The color shapes the server admits. */
const COLOR_SHAPES = [
	/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/,
	/^var\(--[A-Za-z0-9-]+\)$/,
	/^(?:rgb|rgba|hsl|hsla)\([0-9.,%\s]*\)$/,
];

const NUMERIC =
	/^\s*[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\s*$/;

/**
 * Whether this host can check a pasted SVG. The server says so before the
 * editor script loads; anything but `false` means it can.
 *
 * @return {boolean} True unless the server said it cannot.
 */
function canCheckPastedSvg() {
	const state = window.blocklaneProCarousel;
	return ! ( state && false === state.svgValidator );
}

/**
 * The whole part of a number or numeric string, or null for anything else.
 *
 * @param {unknown} value A stored attribute value.
 * @return {number|null} The whole number, or null.
 */
function wholeNumber( value ) {
	let number = null;
	if ( 'number' === typeof value ) {
		number = value;
	} else if ( 'string' === typeof value && NUMERIC.test( value ) ) {
		number = Number( value );
	}
	if ( null === number || ! Number.isFinite( number ) ) {
		return null;
	}
	return Math.trunc( number ) || 0;
}

/**
 * A size as the server draws it: the whole number clamped to its range, or
 * the default when the value is not a number.
 *
 * @param {unknown} value    The stored attribute value.
 * @param {number}  min      The smallest size.
 * @param {number}  max      The largest size.
 * @param {number}  fallback The attribute's default.
 * @return {number} The size to draw.
 */
function clampSize( value, min, max, fallback ) {
	const number = wholeNumber( value );
	if ( null === number ) {
		return fallback;
	}
	return Math.min( max, Math.max( min, number ) );
}

/**
 * A color as the server draws it: the value when it has an admitted shape,
 * else nothing.
 *
 * @param {unknown} value The stored color.
 * @return {string|undefined} The color, or undefined when none is drawn.
 */
function safeColor( value ) {
	if ( 'string' !== typeof value ) {
		return undefined;
	}
	return COLOR_SHAPES.some( ( shape ) => shape.test( value ) )
		? value
		: undefined;
}

/**
 * The justification as the server draws it.
 *
 * @param {unknown} value The stored justification.
 * @return {string} One of left, center, right or stretch.
 */
function toJustification( value ) {
	return JUSTIFICATIONS.includes( value ) ? value : 'left';
}

/**
 * The catalog icon of a name, if there is one.
 *
 * @param {unknown} name The stored icon name.
 * @return {Object|undefined} The catalog entry.
 */
function catalogIcon( name ) {
	return ICONS.find( ( entry ) => entry.name === name );
}

/**
 * Whether a catalog icon has a drawing at a weight.
 *
 * @param {Object}  entry  A catalog icon.
 * @param {unknown} weight A weight.
 * @return {boolean} True when it has one.
 */
function hasWeight( entry, weight ) {
	return (
		'string' === typeof weight &&
		Object.prototype.hasOwnProperty.call( entry.paths, weight ) &&
		'string' === typeof entry.paths[ weight ]
	);
}

/**
 * The weights a catalog icon has, in the order the editor offers them.
 *
 * @param {Object} entry A catalog icon.
 * @return {string[]} Its weights.
 */
function iconWeights( entry ) {
	return WEIGHTS.filter( ( weight ) => hasWeight( entry, weight ) );
}

/**
 * The weight an icon is drawn at: the one asked for when it has it, else
 * regular, else its first.
 *
 * @param {Object}  entry  A catalog icon.
 * @param {unknown} weight The stored weight.
 * @return {string} The weight drawn.
 */
function drawnWeight( entry, weight ) {
	if ( WEIGHTS.includes( weight ) && hasWeight( entry, weight ) ) {
		return weight;
	}
	if ( hasWeight( entry, 'regular' ) ) {
		return 'regular';
	}
	return Object.keys( entry.paths )[ 0 ];
}

/**
 * The size of a string in bytes of UTF-8.
 *
 * @param {string} text The string.
 * @return {number} Its length in bytes.
 */
function utf8Length( text ) {
	let bytes = 0;
	for ( const char of text ) {
		const code = char.codePointAt( 0 );
		if ( code < 0x80 ) {
			bytes += 1;
		} else if ( code < 0x800 ) {
			bytes += 2;
		} else if ( code < 0x10000 ) {
			bytes += 3;
		} else {
			bytes += 4;
		}
	}
	return bytes;
}

/**
 * The index just past a marker, or -1 when the marker never comes.
 *
 * @param {string} text   The paste.
 * @param {string} marker What ends the construct.
 * @param {number} from   Where to start looking.
 * @return {number} The index after the marker, or -1.
 */
function indexAfter( text, marker, from ) {
	const found = text.indexOf( marker, from );
	return -1 === found ? -1 : found + marker.length;
}

/**
 * The index of the `>` that ends a tag or declaration, skipping quoted
 * values and a bracketed internal subset; -1 when there is none.
 *
 * @param {string} text The paste.
 * @param {number} from The index after the tag's `<`.
 * @return {number} The index of the closing `>`, or -1.
 */
function tagEnd( text, from ) {
	let quote = '';
	let brackets = 0;
	for ( let index = from; index < text.length; index++ ) {
		const char = text[ index ];
		if ( quote ) {
			if ( char === quote ) {
				quote = '';
			}
		} else if ( '"' === char || "'" === char ) {
			quote = char;
		} else if ( '[' === char ) {
			brackets++;
		} else if ( ']' === char ) {
			brackets--;
		} else if ( '>' === char && brackets <= 0 ) {
			return index;
		}
	}
	return -1;
}

/**
 * Read a paste's tags before parsing it: refuse an XML declaration that is
 * not at the very start, a reference XML does not define, tags that do not
 * balance, and nesting deeper than the server reads. Comments, CDATA and
 * processing instructions are not tags.
 *
 * @param {string} text The paste.
 * @return {boolean} True when the paste may be parsed.
 */
function tagsAdmitted( text ) {
	const open = [];
	let at = 0;
	while ( at < text.length ) {
		const lt = text.indexOf( '<', at );
		const plain = text.slice( at, -1 === lt ? text.length : lt );
		if ( UNKNOWN_REFERENCE.test( plain ) ) {
			return false;
		}
		if ( -1 === lt ) {
			break;
		}
		let next;
		if ( text.startsWith( '<!--', lt ) ) {
			next = indexAfter( text, '-->', lt + 4 );
		} else if ( text.startsWith( '<![CDATA[', lt ) ) {
			next = indexAfter( text, ']]>', lt + 9 );
		} else if ( text.startsWith( '<?', lt ) ) {
			if ( lt > 0 && /^<\?xml[\s?]/i.test( text.slice( lt, lt + 6 ) ) ) {
				return false;
			}
			next = indexAfter( text, '?>', lt + 2 );
		} else {
			const end = tagEnd( text, lt + 1 );
			if ( -1 === end ) {
				return false;
			}
			const tag = text.slice( lt, end + 1 );
			if ( UNKNOWN_REFERENCE.test( tag ) ) {
				return false;
			}
			if ( ! tag.startsWith( '<!' ) ) {
				const match = /^<(\/?)([^\s/>]+)/.exec( tag );
				if ( ! match ) {
					return false;
				}
				if ( match[ 1 ] ) {
					if ( open.pop() !== match[ 2 ] ) {
						return false;
					}
				} else if ( ! tag.endsWith( '/>' ) ) {
					open.push( match[ 2 ] );
					if ( open.length > MAX_PASTE_DEPTH ) {
						return false;
					}
				}
			}
			next = end + 1;
		}
		if ( -1 === next ) {
			return false;
		}
		at = next;
	}
	return 0 === open.length;
}

/**
 * Whether a paste may be parsed at all.
 *
 * @param {unknown} paste The stored paste.
 * @return {boolean} True when it passes every check made before parsing.
 */
function pasteAdmitted( paste ) {
	if ( 'string' !== typeof paste || '' === paste ) {
		return false;
	}
	if ( /<!ENTITY/i.test( paste ) ) {
		return false;
	}
	if (
		paste.length > MAX_PASTE_BYTES ||
		utf8Length( paste ) > MAX_PASTE_BYTES
	) {
		return false;
	}
	return tagsAdmitted( paste );
}

/**
 * Parse a paste as XML; its root element when it parsed cleanly and the
 * root is an svg element, else null.
 *
 * @param {string} paste The paste.
 * @return {Element|null} The root element, or null.
 */
function parseSvgRoot( paste ) {
	let doc;
	try {
		doc = new window.DOMParser().parseFromString( paste, 'image/svg+xml' );
	} catch {
		return null;
	}
	if ( ! doc || ! doc.documentElement ) {
		return null;
	}
	if (
		doc.getElementsByTagName( 'parsererror' ).length ||
		doc.getElementsByTagNameNS( '*', 'parsererror' ).length
	) {
		return null;
	}
	const root = doc.documentElement;
	return 'svg' === String( root.nodeName ).toLowerCase() ? root : null;
}

/**
 * Whether an attribute value points outside the document: any `url(` that
 * is not a fragment of the same document. A backslash is refused too, since
 * a CSS escape can spell `url(` in a presentation attribute.
 *
 * @param {string} value The decoded attribute value.
 * @return {boolean} True when the attribute must go.
 */
function pointsOutside( value ) {
	if ( value.includes( '\\' ) ) {
		return true;
	}
	for ( const match of value.matchAll( /url\(/gi ) ) {
		const rest = value.slice( match.index + match[ 0 ].length );
		if ( ! /^[\s'"]*#/.test( rest ) ) {
			return true;
		}
	}
	return false;
}

/**
 * An element's kept attributes, under React's names.
 *
 * @param {Element} element The parsed element.
 * @param {string}  name    Its lower-case name, one the allowlist holds.
 * @return {Object} The props to build it with.
 */
function keptAttributes( element, name ) {
	const allowed = ALLOWED_ELEMENTS.get( name );
	const props = {};
	for ( const attribute of Array.from( element.attributes ) ) {
		const attributeName = String( attribute.name ).toLowerCase();
		if (
			! allowed.includes( attributeName ) ||
			! REACT_NAMES.has( attributeName )
		) {
			continue;
		}
		const value = String( attribute.value );
		if ( pointsOutside( value ) ) {
			continue;
		}
		props[ REACT_NAMES.get( attributeName ) ] = value;
	}
	return props;
}

/**
 * An element's children, cleaned: allowlisted elements with their kept
 * attributes; any other element is dropped and its own children take its
 * place. Text is never kept.
 *
 * @param {Element} element The parsed element.
 * @return {Object[]} The cleaned children: { name, props, children }.
 */
function cleanChildren( element ) {
	const kept = [];
	for ( const child of Array.from( element.childNodes ) ) {
		if ( 1 !== child.nodeType ) {
			continue;
		}
		const name = String( child.nodeName ).toLowerCase();
		const grandchildren = cleanChildren( child );
		if ( ALLOWED_ELEMENTS.has( name ) ) {
			kept.push( {
				name,
				props: keptAttributes( child, name ),
				children: grandchildren,
			} );
		} else {
			kept.push( ...grandchildren );
		}
	}
	return kept;
}

/**
 * Whether a cleaned tree holds something that draws.
 *
 * @param {Object[]} nodes Cleaned nodes.
 * @return {boolean} True when one of them, or a descendant, draws.
 */
function drawsSomething( nodes ) {
	return nodes.some(
		( node ) =>
			DRAWING_ELEMENTS.has( node.name ) || drawsSomething( node.children )
	);
}

/**
 * A cleaned node as a React element.
 *
 * @param {Object} node  A cleaned node.
 * @param {number} index Its place among its siblings.
 * @return {Object} The React element.
 */
function toElement( node, index ) {
	return createElement(
		node.name,
		{ ...node.props, key: index },
		node.children.length ? node.children.map( toElement ) : undefined
	);
}

/**
 * A pasted icon as the server will draw it: the cleaned svg as React
 * elements, or null when the server draws the chevron instead.
 *
 * @param {unknown} paste The stored paste.
 * @return {Object|null} The cleaned svg element, or null.
 */
function cleanPastedSvg( paste ) {
	if ( ! pasteAdmitted( paste ) ) {
		return null;
	}
	const root = parseSvgRoot( paste );
	if ( ! root ) {
		return null;
	}
	const children = cleanChildren( root );
	if ( ! drawsSomething( children ) ) {
		return null;
	}
	return createElement(
		'svg',
		keptAttributes( root, 'svg' ),
		children.map( toElement )
	);
}

/**
 * The type choices, in the order the editor offers them.
 *
 * @return {Object[]} Each { value, label }.
 */
function typeOptions() {
	return [
		{ value: 'arrows', label: __( 'Arrows', 'blocklane' ) },
		{ value: 'dots', label: __( 'Dots', 'blocklane' ) },
		{ value: 'bars', label: __( 'Bars', 'blocklane' ) },
		{ value: 'scrollbar', label: __( 'Scrollbar', 'blocklane' ) },
		{ value: 'autoplay', label: __( 'Play/Pause', 'blocklane' ) },
	];
}

/**
 * A weight's label.
 *
 * @param {string} weight A weight.
 * @return {string} Its label.
 */
function weightLabel( weight ) {
	if ( 'bold' === weight ) {
		return __( 'Bold', 'blocklane' );
	}
	if ( 'fill' === weight ) {
		return __( 'Fill', 'blocklane' );
	}
	return __( 'Regular', 'blocklane' );
}

/**
 * The color settings each type offers.
 *
 * @param {string}                       navType       The type drawn.
 * @param {Object}                       attributes    The block's attributes.
 * @param {(attributes: Object) => void} setAttributes Writes attributes.
 * @return {Object[]} The color panel's settings.
 */
function colorSettings( navType, attributes, setAttributes ) {
	/**
	 * One color setting.
	 *
	 * @param {string} key   The attribute it writes.
	 * @param {string} label Its label.
	 * @return {Object} The setting.
	 */
	const setting = ( key, label ) => ( {
		label,
		value: safeColor( attributes[ key ] ),
		onChange: ( value ) => setAttributes( { [ key ]: value } ),
	} );
	if ( 'dots' === navType ) {
		return [ setting( 'dotColor', __( 'Dot color', 'blocklane' ) ) ];
	}
	if ( 'bars' === navType || 'scrollbar' === navType ) {
		return [ setting( 'dotColor', __( 'Bar color', 'blocklane' ) ) ];
	}
	if ( 'autoplay' === navType ) {
		return [
			setting( 'buttonTextColor', __( 'Button text', 'blocklane' ) ),
			setting(
				'buttonBackgroundColor',
				__( 'Button background', 'blocklane' )
			),
		];
	}
	return [ setting( 'arrowColor', __( 'Arrow color', 'blocklane' ) ) ];
}

/**
 * The drawing inside each arrow button: a catalog icon, the pasted icon, or
 * the chevron the server falls back to.
 *
 * @param {Object}      props        The component's props.
 * @param {unknown}     props.icon   The stored icon name.
 * @param {unknown}     props.weight The stored weight.
 * @param {Object|null} props.custom The cleaned pasted icon, when it is used.
 * @return {Object} The drawing.
 */
function ArrowGlyph( { icon, weight, custom } ) {
	if ( custom ) {
		return (
			<span className="blocklane-carousel-custom-icon" aria-hidden="true">
				{ custom }
			</span>
		);
	}
	const entry = catalogIcon( icon );
	const viewBox = entry ? entry.viewBox : FALLBACK_VIEWBOX;
	const d = entry
		? entry.paths[ drawnWeight( entry, weight ) ]
		: CHEVRON.paths.regular;
	return (
		<svg
			viewBox={ viewBox }
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ d } />
		</svg>
	);
}

/**
 * The preview: the markup the server renders for the type, drawn from the
 * checked attributes. It is a stand-in to see, never a control to use.
 *
 * @param {Object}      props            The component's props.
 * @param {string}      props.navType    The type drawn.
 * @param {Object}      props.attributes The block's attributes.
 * @param {Object|null} props.custom     The cleaned pasted icon, when used.
 * @return {Object} The preview.
 */
function Preview( { navType, attributes, custom } ) {
	const justify = toJustification( attributes.justifyContent );
	const justification = `is-content-justification-${ justify }`;
	const dotColor = safeColor( attributes.dotColor );
	const barHeight = clampSize( attributes.barSize, 2, 24, 4 );

	if ( 'dots' === navType || 'bars' === navType ) {
		const isBars = 'bars' === navType;
		const fills = isBars && !! attributes.barFill;
		const style = {};
		if ( dotColor ) {
			style[ '--blocklane-carousel-dot-color' ] = dotColor;
		}
		if ( isBars ) {
			style[ '--blocklane-carousel-bar-height' ] = `${ barHeight }px`;
			if ( ! fills ) {
				const barWidth = clampSize( attributes.barWidth, 8, 120, 32 );
				style[ '--blocklane-carousel-bar-width' ] = `${ barWidth }px`;
			}
		} else {
			const dotSize = clampSize( attributes.dotSize, 2, 24, 8 );
			style[ '--blocklane-carousel-dot-size' ] = `${ dotSize }px`;
		}
		const classes = [ 'blocklane-carousel-pagination', justification ];
		if ( isBars ) {
			classes.push( 'blocklane-carousel-pagination--bars' );
		}
		if ( fills ) {
			classes.push( 'blocklane-carousel-pagination--bars-fill' );
		}
		return (
			<div className={ classes.join( ' ' ) } style={ style }>
				<span className="blocklane-carousel-dot is-active" />
				<span className="blocklane-carousel-dot" />
				<span className="blocklane-carousel-dot" />
			</div>
		);
	}

	if ( 'scrollbar' === navType ) {
		const style = { '--blocklane-carousel-bar-height': `${ barHeight }px` };
		if ( dotColor ) {
			style[ '--blocklane-carousel-dot-color' ] = dotColor;
		}
		return (
			<div
				className={ `blocklane-carousel-scrollbar ${ justification }` }
				style={ style }
			>
				<div className="blocklane-carousel-scrollbar__track">
					<div className="blocklane-carousel-scrollbar__thumb" />
				</div>
			</div>
		);
	}

	if ( 'autoplay' === navType ) {
		const style = {};
		const textColor = safeColor( attributes.buttonTextColor );
		const backgroundColor = safeColor( attributes.buttonBackgroundColor );
		const fontSize = attributes.buttonFontSize;
		if ( textColor ) {
			style.color = textColor;
		}
		if ( backgroundColor ) {
			style.backgroundColor = backgroundColor;
		}
		if ( 'number' === typeof fontSize && Number.isFinite( fontSize ) ) {
			style.fontSize = `${ clampSize( fontSize, 10, 40, 10 ) }px`;
		} else if ( 'string' === typeof fontSize && '' !== fontSize ) {
			style.fontSize = fontSize;
		}
		return (
			<div
				className={ `blocklane-carousel-nav blocklane-carousel-nav--autoplay ${ justification }` }
			>
				<div className="wp-block-button">
					<button
						type="button"
						className="wp-block-button__link wp-element-button blocklane-carousel-autoplay-toggle"
						style={
							Object.keys( style ).length ? style : undefined
						}
						tabIndex={ -1 }
					>
						{ __( 'Pause autoplay', 'blocklane' ) }
					</button>
				</div>
			</div>
		);
	}

	const arrowSize = clampSize( attributes.arrowSize, 16, 96, 36 );
	const style = { '--blocklane-carousel-arrow-size': `${ arrowSize }px` };
	const arrowColor = safeColor( attributes.arrowColor );
	if ( arrowColor ) {
		style[ '--blocklane-carousel-arrow-color' ] = arrowColor;
	}
	const glyph = (
		<ArrowGlyph
			icon={ attributes.arrowIcon }
			weight={ attributes.arrowIconWeight }
			custom={ custom }
		/>
	);
	return (
		<div
			className={ `blocklane-carousel-nav ${ justification }` }
			style={ style }
		>
			<button
				type="button"
				className="blocklane-carousel-prev"
				aria-label={ __( 'Previous slide', 'blocklane' ) }
				tabIndex={ -1 }
			>
				{ glyph }
			</button>
			<button
				type="button"
				className="blocklane-carousel-next"
				aria-label={ __( 'Next slide', 'blocklane' ) }
				tabIndex={ -1 }
			>
				{ glyph }
			</button>
		</div>
	);
}

/**
 * A catalog icon's drawing for its button in the picker.
 *
 * @param {Object}  entry  A catalog icon.
 * @param {unknown} weight The stored weight.
 * @return {Object} The drawing.
 */
function pickerIcon( entry, weight ) {
	return (
		<SVG viewBox={ entry.viewBox } fill="currentColor">
			<Path d={ entry.paths[ drawnWeight( entry, weight ) ] } />
		</SVG>
	);
}

/**
 * The arrows' settings: the icon picker, the weight or the pasted icon, the
 * size, and the offset when the carousel centers its arrows.
 *
 * @param {Object}                       props               The component's props.
 * @param {Object}                       props.attributes    The block's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Writes attributes.
 * @param {boolean}                      props.centered      Whether the carousel centers its arrows.
 * @param {boolean}                      props.canCheck      Whether the server can check a paste.
 * @return {Object} The settings.
 */
function ArrowSettings( { attributes, setAttributes, centered, canCheck } ) {
	const { arrowIcon, arrowIconWeight, customArrowSvg } = attributes;
	const isCustom = 'custom' === arrowIcon;
	const current = catalogIcon( arrowIcon );
	const pasted = 'string' === typeof customArrowSvg ? customArrowSvg : '';
	let pasteHelp = __(
		'Paste SVG markup. Scripts and links are stripped.',
		'blocklane'
	);
	if ( ! canCheck ) {
		pasteHelp = __(
			"This server cannot check pasted SVG (PHP's DOM extension is not installed), so the default arrow is shown instead of a custom icon.",
			'blocklane'
		);
	}

	/**
	 * Choose a catalog icon, moving the weight to one it has when needed.
	 *
	 * @param {Object} entry The catalog icon chosen.
	 */
	const chooseIcon = ( entry ) => {
		const next = { arrowIcon: entry.name };
		if ( ! hasWeight( entry, arrowIconWeight ) ) {
			next.arrowIconWeight = hasWeight( entry, 'regular' )
				? 'regular'
				: Object.keys( entry.paths )[ 0 ];
		}
		setAttributes( next );
	};

	return (
		<>
			<div
				role="group"
				aria-label={ __( 'Arrow icon', 'blocklane' ) }
				className="blocklane-carousel-controls-icon-picker"
			>
				{ ICONS.map( ( entry ) => (
					<Button
						key={ entry.name }
						__next40pxDefaultSize
						aria-label={ entry.label }
						isPressed={ entry.name === arrowIcon }
						onClick={ () => chooseIcon( entry ) }
						icon={ pickerIcon( entry, arrowIconWeight ) }
					/>
				) ) }
				<Button
					__next40pxDefaultSize
					isPressed={ isCustom }
					onClick={ () => setAttributes( { arrowIcon: 'custom' } ) }
				>
					{ __( 'Custom', 'blocklane' ) }
				</Button>
			</div>
			{ ! isCustom && current && (
				<ToggleGroupControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					isBlock
					label={ __( 'Weight', 'blocklane' ) }
					value={ drawnWeight( current, arrowIconWeight ) }
					onChange={ ( value ) =>
						setAttributes( { arrowIconWeight: value } )
					}
				>
					{ iconWeights( current ).map( ( weight ) => (
						<ToggleGroupControlOption
							key={ weight }
							value={ weight }
							label={ weightLabel( weight ) }
						/>
					) ) }
				</ToggleGroupControl>
			) }
			{ isCustom && (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Custom SVG', 'blocklane' ) }
					help={ pasteHelp }
					value={ pasted }
					onChange={ ( value ) =>
						setAttributes( { customArrowSvg: value } )
					}
					rows={ 6 }
				/>
			) }
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Arrow size', 'blocklane' ) }
				value={ clampSize( attributes.arrowSize, 16, 96, 36 ) }
				min={ 16 }
				max={ 96 }
				step={ 1 }
				onChange={ ( value ) => {
					if ( Number.isFinite( value ) ) {
						setAttributes( { arrowSize: value } );
					}
				} }
			/>
			{ centered && (
				<RangeControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Arrow offset', 'blocklane' ) }
					help={ __(
						'Distance from the carousel edges when arrows are centered.',
						'blocklane'
					) }
					value={ clampSize( attributes.arrowOffset, -100, 100, 10 ) }
					min={ -100 }
					max={ 100 }
					step={ 1 }
					onChange={ ( value ) => {
						if ( Number.isFinite( value ) ) {
							setAttributes( { arrowOffset: value } );
						}
					} }
				/>
			) }
		</>
	);
}

/**
 * The settings of the dots, the bars, the scrollbar and the pause button.
 *
 * @param {Object}                       props               The component's props.
 * @param {string}                       props.navType       The type drawn.
 * @param {Object}                       props.attributes    The block's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Writes attributes.
 * @return {Object|null} The settings.
 */
function OtherSettings( { navType, attributes, setAttributes } ) {
	/**
	 * Write a number from a range, ignoring a value that is not one.
	 *
	 * @param {string}  key   The attribute.
	 * @param {unknown} value The range's value.
	 */
	const writeNumber = ( key, value ) => {
		if ( Number.isFinite( value ) ) {
			setAttributes( { [ key ]: value } );
		}
	};

	if ( 'dots' === navType ) {
		return (
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Dot size', 'blocklane' ) }
				value={ clampSize( attributes.dotSize, 2, 24, 8 ) }
				min={ 2 }
				max={ 24 }
				step={ 1 }
				onChange={ ( value ) => writeNumber( 'dotSize', value ) }
			/>
		);
	}

	const barHeight = (
		<RangeControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Bar height', 'blocklane' ) }
			value={ clampSize( attributes.barSize, 2, 24, 4 ) }
			min={ 2 }
			max={ 24 }
			step={ 1 }
			onChange={ ( value ) => writeNumber( 'barSize', value ) }
		/>
	);

	if ( 'bars' === navType ) {
		const fills = !! attributes.barFill;
		return (
			<>
				{ barHeight }
				{ ! fills && (
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Bar width', 'blocklane' ) }
						value={ clampSize( attributes.barWidth, 8, 120, 32 ) }
						min={ 8 }
						max={ 120 }
						step={ 1 }
						onChange={ ( value ) =>
							writeNumber( 'barWidth', value )
						}
					/>
				) }
				<ToggleGroupControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					isBlock
					label={ __( 'Width', 'blocklane' ) }
					value={ fills ? 'fill' : 'fixed' }
					onChange={ ( value ) =>
						setAttributes( { barFill: 'fill' === value } )
					}
				>
					<ToggleGroupControlOption
						value="fixed"
						label={ __( 'Fixed', 'blocklane' ) }
					/>
					<ToggleGroupControlOption
						value="fill"
						label={ __( 'Fill', 'blocklane' ) }
					/>
				</ToggleGroupControl>
			</>
		);
	}

	if ( 'scrollbar' === navType ) {
		return barHeight;
	}

	if ( 'autoplay' === navType ) {
		const fontSize = attributes.buttonFontSize;
		return (
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Font size', 'blocklane' ) }
				value={
					'number' === typeof fontSize && Number.isFinite( fontSize )
						? clampSize( fontSize, 10, 40, 10 )
						: undefined
				}
				min={ 10 }
				max={ 40 }
				step={ 1 }
				allowReset
				onChange={ ( value ) =>
					setAttributes( {
						buttonFontSize: Number.isFinite( value )
							? value
							: undefined,
					} )
				}
			/>
		);
	}

	return null;
}

/**
 * The block's editor.
 *
 * @param {Object}                       props               The block's props.
 * @param {Object}                       props.attributes    The block's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Writes attributes.
 * @param {Object}                       props.context       The carousel's context.
 * @return {Object} The editor.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const { navType, justifyContent, arrowIcon, customArrowSvg } = attributes;
	const type = NAV_TYPES.includes( navType ) ? navType : 'arrows';
	const canCheck = canCheckPastedSvg();
	const usesPaste = 'arrows' === type && 'custom' === arrowIcon && canCheck;
	const custom = useMemo(
		() => ( usesPaste ? cleanPastedSvg( customArrowSvg ) : null ),
		[ usesPaste, customArrowSvg ]
	);
	const blockProps = useBlockProps();
	const centered = !! context?.[ 'blocklane/carouselCenterNav' ];

	return (
		<>
			<BlockControls group="block">
				<JustifyContentControl
					allowedControls={ JUSTIFICATIONS }
					value={ toJustification( justifyContent ) }
					onChange={ ( value ) =>
						setAttributes( {
							justifyContent: toJustification( value ),
						} )
					}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Controls', 'blocklane' ) }>
					<VStack spacing={ 4 }>
						<ToggleGroupControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							isBlock
							label={ __( 'Type', 'blocklane' ) }
							value={ type }
							onChange={ ( value ) =>
								setAttributes( { navType: value } )
							}
						>
							{ typeOptions().map( ( option ) => (
								<ToggleGroupControlOption
									key={ option.value }
									value={ option.value }
									label={ option.label }
								/>
							) ) }
						</ToggleGroupControl>
						{ 'arrows' === type ? (
							<ArrowSettings
								attributes={ attributes }
								setAttributes={ setAttributes }
								centered={ centered }
								canCheck={ canCheck }
							/>
						) : (
							<OtherSettings
								navType={ type }
								attributes={ attributes }
								setAttributes={ setAttributes }
							/>
						) }
					</VStack>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Colors', 'blocklane' ) }
					colorSettings={ colorSettings(
						type,
						attributes,
						setAttributes
					) }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<Preview
					navType={ type }
					attributes={ attributes }
					custom={ custom }
				/>
			</div>
		</>
	);
}
