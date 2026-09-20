/**
 * Whitelist SVG sanitiser — shared by button-icons and icon-library
 * (the PHP twin is blocklane_pro_ext_sanitize_svg in loader/runtime-helpers.php).
 *
 * @package
 */

/**
 * Whitelist-sanitise an SVG string on the client (defence in depth; PHP does the
 * same on render). Strips anything not in the allow-lists, event handlers, and
 * dangerous URI schemes.
 *
 * @param {string} raw Raw SVG markup.
 * @return {string} Sanitised SVG, or '' on failure.
 */
export default function sanitizeSvgString( raw ) {
	if ( ! raw || typeof raw !== 'string' ) {
		return '';
	}

	const doc = new DOMParser().parseFromString( raw, 'image/svg+xml' );
	const svg = doc.querySelector( 'svg' );
	if ( ! svg || doc.querySelector( 'parsererror' ) ) {
		return '';
	}

	const allowedElements = new Set( [
		'svg',
		'path',
		'circle',
		'rect',
		'line',
		'polyline',
		'polygon',
		'ellipse',
		'g',
		'defs',
		'clippath',
		'use',
		'title',
		'desc',
	] );
	const allowedAttrs = new Set( [
		'viewbox',
		'xmlns',
		'xmlns:xlink',
		'width',
		'height',
		'fill',
		'class',
		'd',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'x',
		'y',
		'x1',
		'y1',
		'x2',
		'y2',
		'points',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'fill-rule',
		'clip-rule',
		'opacity',
		'transform',
		'fill-opacity',
		'stroke-opacity',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-miterlimit',
		'id',
		'clip-path',
		'clippathunits',
		'href',
		'xlink:href',
	] );

	const cleanAttrs = ( el ) => {
		for ( const attr of Array.from( el.attributes ) ) {
			const name = attr.name.toLowerCase();
			if (
				name.startsWith( 'on' ) ||
				! allowedAttrs.has( name ) ||
				/^\s*(javascript|data|vbscript)\s*:/i.test( attr.value )
			) {
				el.removeAttribute( attr.name );
			}
		}
	};

	const clean = ( node ) => {
		for ( const child of Array.from( node.childNodes ) ) {
			if ( child.nodeType === Node.TEXT_NODE ) {
				continue;
			}
			if ( child.nodeType !== Node.ELEMENT_NODE ) {
				node.removeChild( child );
				continue;
			}
			if ( ! allowedElements.has( child.tagName.toLowerCase() ) ) {
				node.removeChild( child );
				continue;
			}
			cleanAttrs( child );
			clean( child );
		}
	};

	// The root <svg>'s own attributes, then its descendants — a bare
	// <svg onload="…"> would otherwise slip through.
	cleanAttrs( svg );
	clean( svg );

	return new XMLSerializer().serializeToString( svg );
}
