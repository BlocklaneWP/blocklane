/**
 * Custom-SVG sanitizer for the editor: the browser-side twin of
 * blocklane_pro_ext_sanitize_svg() (inc/extensions/loader/runtime-helpers.php).
 *
 * Both implement one policy. The server copy is the security boundary (it
 * runs on save and on render); this copy keeps the editor's previews honest
 * and keeps markup the server would strip out of the canvas.
 *
 * The steps:
 *
 * 1. Refuse anything that is not a non-empty string mentioning `<svg`.
 * 2. Parse it as XML behind a prolog supplied here (so an input that brings
 *    its own XML declaration does not parse), and refuse a parse error.
 * 3. Take the first element whose local name is `svg` as the root.
 * 4. Walk the root's subtree: text stays (whitespace-only text goes, as the
 *    server's parser drops it), allowed elements stay with their attributes
 *    judged, every other node goes with its subtree.
 * 5. Serialize the root alone.
 *
 * Where the browser's parser differs from libxml (DTD entities, namespace
 * re-declaration on serialization), it may answer '' or add a namespace
 * declaration where the server keeps a node; the jest test beside this file
 * names each such fixture case.
 */

const SHAPE_ATTRIBUTES = [
	// Geometry.
	'd',
	'points',
	'x',
	'y',
	'x1',
	'y1',
	'x2',
	'y2',
	'cx',
	'cy',
	'r',
	'rx',
	'ry',
	'width',
	'height',
	// Paint.
	'fill',
	'fill-opacity',
	'fill-rule',
	'clip-rule',
	'opacity',
	'stroke',
	'stroke-width',
	'stroke-opacity',
	'stroke-linecap',
	'stroke-linejoin',
	'stroke-miterlimit',
	'stroke-dasharray',
	'stroke-dashoffset',
	// Placement.
	'transform',
	'class',
];

/**
 * Allowed element name (lowercased) → the attribute names (lowercased) it
 * may keep. An element absent here is removed with its subtree.
 *
 * @type {Map<string, Set<string>>}
 */
const POLICY = new Map( [
	[
		'svg',
		new Set( [
			'xmlns',
			'xmlns:xlink',
			'viewbox',
			'width',
			'height',
			'fill',
			'class',
		] ),
	],
	[
		'g',
		new Set( [
			'id',
			'class',
			'transform',
			'clip-path',
			'fill',
			'stroke',
			'stroke-width',
			'opacity',
		] ),
	],
	[ 'defs', new Set( [ 'id' ] ) ],
	[ 'clippath', new Set( [ 'id', 'clippathunits' ] ) ],
	[ 'use', new Set( [ 'href', 'xlink:href', 'x', 'y', 'width', 'height' ] ) ],
	[ 'title', new Set() ],
	[ 'desc', new Set() ],
	...[
		'path',
		'rect',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
	].map( ( drawing ) => [ drawing, new Set( SHAPE_ATTRIBUTES ) ] ),
] );

const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

// Space, tab, LF, CR, FF, VT — spelled out, as the server copy does.
const SCRIPT_SCHEME =
	/^[\x20\t\n\r\f\v]*(?:javascript|data|vbscript)[\x20\t\n\r\f\v]*:/i;
const LEADING_BLANKS = /^[\x20\t\n\r\f\v]+/;
const ONLY_BLANKS = /^[\x20\t\n\r\f\v]*$/;
// A CSS escape: up to six hex digits and one optional blank, or any one
// character. And a url( opening, through an optional quote, capturing the
// first character of its target.
const CSS_ESCAPE = /\\(?:([0-9a-f]{1,6})[\x20\t\n\r\f]?|([\s\S]))/gi;
const URL_TARGET =
	/url[\x20\t\n\r\f\v]*\([\x20\t\n\r\f\v]*["']?[\x20\t\n\r\f\v]*([\s\S]?)/gi;

/**
 * Whether every CSS url() in a value names a same-document fragment. The
 * value is read as CSS reads it: escapes decoded first (only ASCII decides,
 * so an escape of anything wider becomes '?', never `#`), any case, blanks
 * before `(`, inside it and around an optional quote.
 *
 * @param {string} value Attribute value as parsed.
 * @return {boolean} Whether no url() points anywhere but `#…`.
 */
function urlsAreFragments( value ) {
	const css = value.replace( CSS_ESCAPE, ( match, hex, char ) => {
		if ( hex === undefined ) {
			return char;
		}
		const code = parseInt( hex, 16 );
		return code <= 0x7f ? String.fromCharCode( code ) : '?';
	} );
	return Array.from( css.matchAll( URL_TARGET ) ).every(
		( match ) => match[ 1 ] === '#'
	);
}

/**
 * Whether one attribute survives. Removed when its name is not allowed on
 * the element, names an event handler (redundant with today's list, kept so
 * a widened list cannot admit one), its value opens with a script-capable
 * scheme, or it is a reference that is not a same-document fragment: an
 * href/xlink:href that does not start with `#`, or a CSS url() anywhere in
 * any attribute's value whose target does not (a remote or data: paint,
 * clip, mask or filter is a request on every page view).
 *
 * @param {string}      name    Qualified attribute name, lowercased.
 * @param {string}      value   Attribute value as parsed.
 * @param {Set<string>} allowed Names the element may keep.
 * @return {boolean} Whether to keep it.
 */
function keepsAttribute( name, value, allowed ) {
	if ( ! allowed.has( name ) || name.startsWith( 'on' ) ) {
		return false;
	}
	if ( SCRIPT_SCHEME.test( value ) ) {
		return false;
	}
	if ( name === 'href' || name === 'xlink:href' ) {
		return value.replace( LEADING_BLANKS, '' ).startsWith( '#' );
	}
	return urlsAreFragments( value );
}

/**
 * Strip from an element every attribute its policy row does not keep.
 * Namespace declarations are not judged: the DOM lists them as attributes,
 * the policy does not.
 *
 * @param {Element} element The element.
 * @param {string}  row     Its row in POLICY.
 */
function judgeAttributes( element, row ) {
	const allowed = POLICY.get( row ) || new Set();
	const doomed = Array.from( element.attributes ).filter(
		( attribute ) =>
			attribute.namespaceURI !== XMLNS_NAMESPACE &&
			! keepsAttribute(
				attribute.name.toLowerCase(),
				attribute.value,
				allowed
			)
	);
	// Remove the nodes, not names: two attributes may share a local name.
	doomed.forEach( ( attribute ) => element.removeAttributeNode( attribute ) );
}

/**
 * Remove every child the policy does not allow, recursing into kept elements.
 *
 * @param {Element} parent A kept element.
 */
function scrubChildren( parent ) {
	Array.from( parent.childNodes ).forEach( ( child ) => {
		if ( child.nodeType === child.TEXT_NODE ) {
			if ( ONLY_BLANKS.test( child.data ) ) {
				parent.removeChild( child );
			}
			return;
		}
		if ( child.nodeType === child.ELEMENT_NODE ) {
			const name = child.nodeName.toLowerCase();
			if ( POLICY.has( name ) ) {
				judgeAttributes( child, name );
				scrubChildren( child );
				return;
			}
		}
		parent.removeChild( child );
	} );
}

/**
 * Whether a parsed document is the parser's error report. Browsers do not
 * throw on malformed XML; they hand back (or insert) a `parsererror`
 * element in a namespace of their own, which an input cannot place there
 * without also failing this test (a false refusal, never a false pass).
 *
 * @param {Document} doc The parsed document.
 * @return {boolean} Whether parsing failed.
 */
function isParseError( doc ) {
	return Array.from( doc.getElementsByTagName( '*' ) ).some(
		( element ) =>
			element.localName === 'parsererror' &&
			( element.namespaceURI ===
				'http://www.mozilla.org/newlayout/xml/parsererror.xml' ||
				element.namespaceURI === 'http://www.w3.org/1999/xhtml' )
	);
}

/**
 * A custom SVG reduced to the sanitizer's policy.
 *
 * @param {unknown} svg Raw SVG markup (any value; non-strings are refused).
 * @return {string} The scrubbed SVG, or '' when the input is not SVG markup
 *                  or does not parse.
 */
export default function sanitizeSvgString( svg ) {
	if ( typeof svg !== 'string' || svg === '' || ! /<svg/i.test( svg ) ) {
		return '';
	}
	if (
		typeof window === 'undefined' ||
		typeof window.DOMParser === 'undefined' ||
		typeof window.XMLSerializer === 'undefined'
	) {
		return '';
	}

	let doc;
	try {
		doc = new window.DOMParser().parseFromString(
			'<?xml version="1.0" encoding="UTF-8"?>' + svg,
			'application/xml'
		);
	} catch {
		return '';
	}
	if ( ! doc || isParseError( doc ) ) {
		return '';
	}

	const root = Array.from( doc.getElementsByTagName( '*' ) ).find(
		( element ) => element.localName === 'svg'
	);
	if ( ! root ) {
		return '';
	}

	// The root is judged as an svg element whatever its prefix.
	judgeAttributes( root, 'svg' );
	scrubChildren( root );

	try {
		return new window.XMLSerializer().serializeToString( root ) || '';
	} catch {
		return '';
	}
}
