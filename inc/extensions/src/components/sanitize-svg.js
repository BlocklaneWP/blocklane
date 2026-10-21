import cssVerdict from './svg-css-verdict';

/**
 * The editor half of the SVG sanitizer: it keeps the same small part of SVG
 * the server keeps, so a preview in the block editor shows what will be
 * stored. The server half is the security boundary; this one applies the
 * same policy with the browser's XML parser.
 */

/* A fixed XML declaration, put in front of the input before parsing. */
const XML_DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>';

/* Namespace declarations are listed as attributes in this namespace. */
const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

/* Node types the walk tells apart. */
const ELEMENT_NODE = 1;
const TEXT_NODE = 3;

/* Blanks are space, tab, LF, CR, FF and VT, spelled out. */
const LEADING_BLANKS = /^[\x20\t\n\r\f\v]+/;
const ONLY_BLANKS = /^[\x20\t\n\r\f\v]*$/;

/* A javascript, data or vbscript scheme at the start of a value, blanks allowed around its name. */
const SCHEME =
	/^[\x20\t\n\r\f\v]*(?:javascript|data|vbscript)[\x20\t\n\r\f\v]*:/i;

/* Geometry, paint and placement for the drawing elements. */
const SHAPE = new Set( [
	'class',
	'clip-rule',
	'cx',
	'cy',
	'd',
	'fill',
	'fill-opacity',
	'fill-rule',
	'height',
	'opacity',
	'points',
	'r',
	'rx',
	'ry',
	'stroke',
	'stroke-dasharray',
	'stroke-dashoffset',
	'stroke-linecap',
	'stroke-linejoin',
	'stroke-miterlimit',
	'stroke-opacity',
	'stroke-width',
	'transform',
	'width',
	'x',
	'x1',
	'x2',
	'y',
	'y1',
	'y2',
] );

/*
 * The elements that may survive, each with the attribute names it may keep.
 * Element names are lowercase and in alphabetical order, and so are the
 * attribute names in each row.
 */
const ROWS = new Map( [
	[ 'circle', SHAPE ],
	[ 'clippath', new Set( [ 'clippathunits', 'id' ] ) ],
	[ 'defs', new Set( [ 'id' ] ) ],
	[ 'desc', new Set() ],
	[ 'ellipse', SHAPE ],
	[
		'g',
		new Set( [
			'class',
			'clip-path',
			'fill',
			'id',
			'opacity',
			'stroke',
			'stroke-width',
			'transform',
		] ),
	],
	[ 'line', SHAPE ],
	[ 'path', SHAPE ],
	[ 'polygon', SHAPE ],
	[ 'polyline', SHAPE ],
	[ 'rect', SHAPE ],
	[
		'svg',
		new Set( [
			'class',
			'fill',
			'height',
			'viewbox',
			'width',
			'xmlns',
			'xmlns:xlink',
		] ),
	],
	[ 'title', new Set() ],
	[ 'use', new Set( [ 'height', 'href', 'width', 'x', 'xlink:href', 'y' ] ) ],
] );

/**
 * Lowercases the ASCII letters of a name and leaves every other character
 * as it is, as the server does.
 *
 * @param {string} name A qualified name.
 * @return {string} The name with A to Z lowercased.
 */
function asciiLower( name ) {
	return name.replace( /[A-Z]+/g, ( run ) => run.toLowerCase() );
}

/**
 * Whether one attribute may stay. All of these must hold: its name is in the
 * element's row; its name does not start with `on`; its value does not start
 * with a javascript, data or vbscript scheme; an href or xlink:href value
 * starts with `#` after its leading blanks; and the CSS verdict answers
 * exactly true for the value. Nothing is decoded: the value is judged as the
 * parser handed it over.
 *
 * @param {string}      name    The attribute's qualified name, lowercased.
 * @param {string}      value   The attribute's value, as parsed.
 * @param {Set<string>} allowed The attribute names the element's row allows.
 * @return {boolean} Whether the attribute stays.
 */
function keeps( name, value, allowed ) {
	if ( ! allowed.has( name ) || name.startsWith( 'on' ) ) {
		return false;
	}
	if ( SCHEME.test( value ) ) {
		return false;
	}
	if (
		( name === 'href' || name === 'xlink:href' ) &&
		! value.replace( LEADING_BLANKS, '' ).startsWith( '#' )
	) {
		return false;
	}
	return cssVerdict( value ) === true;
}

/**
 * Removes every attribute of an element that fails keeps(). Namespace
 * declarations are skipped. The failures are collected first and then
 * removed as nodes, so of two attributes sharing a local name only the one
 * that failed goes.
 *
 * @param {Element}     element The element whose attributes are judged.
 * @param {Set<string>} allowed The attribute names its row allows.
 */
function judge( element, allowed ) {
	const failed = [];
	for ( const attr of Array.from( element.attributes ) ) {
		if ( attr.namespaceURI === XMLNS_NAMESPACE ) {
			continue;
		}
		if ( ! keeps( asciiLower( attr.name ), attr.value, allowed ) ) {
			failed.push( attr );
		}
	}
	for ( const attr of failed ) {
		element.removeAttributeNode( attr );
	}
}

/**
 * Walks an element's children: a text node with more than blanks stays, an
 * element in the set stays with its attributes judged and its own children
 * walked, and every other node (a blank text node, an element outside the
 * set with its whole subtree, a CDATA section, a comment, a processing
 * instruction) is removed.
 *
 * @param {Element} parent The element whose children are walked.
 */
function walk( parent ) {
	// Copy the children first, so removing one does not disturb the loop.
	for ( const child of Array.from( parent.childNodes ) ) {
		if ( child.nodeType === TEXT_NODE ) {
			if ( ONLY_BLANKS.test( child.data ) ) {
				parent.removeChild( child );
			}
			continue;
		}
		if ( child.nodeType === ELEMENT_NODE ) {
			// The qualified name, so a prefixed name is never in the set.
			const allowed = ROWS.get( asciiLower( child.nodeName ) );
			if ( allowed ) {
				judge( child, allowed );
				walk( child );
				continue;
			}
		}
		parent.removeChild( child );
	}
}

/**
 * Sanitizes SVG markup down to the small part of SVG an icon may keep, the
 * same part the server keeps. The input is parsed as XML; the first element
 * whose local name is `svg` becomes the root; every element under it that is
 * not in the set is removed with its subtree; every attribute left, the
 * root's included, is judged by keeps(); and the root alone is serialized.
 * Any failure is ''.
 *
 * @param {unknown} svg The markup to sanitize.
 * @return {string} The sanitized svg element, or '' when refused or when any step fails.
 */
export default function sanitizeSvgString( svg ) {
	if ( typeof svg !== 'string' || svg === '' || ! /<svg/i.test( svg ) ) {
		return '';
	}
	if (
		typeof window === 'undefined' ||
		typeof window.DOMParser !== 'function' ||
		typeof window.XMLSerializer !== 'function'
	) {
		return '';
	}
	try {
		const doc = new window.DOMParser().parseFromString(
			XML_DECLARATION + svg,
			'application/xml'
		);
		const elements = Array.from( doc.getElementsByTagName( '*' ) );
		// The parser reports malformed XML with a parsererror element.
		if (
			elements.some( ( element ) => element.localName === 'parsererror' )
		) {
			return '';
		}
		const root = elements.find(
			( element ) => element.localName === 'svg'
		);
		if ( ! root ) {
			return '';
		}
		judge( root, ROWS.get( 'svg' ) );
		walk( root );
		const out = new window.XMLSerializer().serializeToString( root );
		return typeof out === 'string' ? out : '';
	} catch {
		return '';
	}
}
