/**
 * Transparent Header — front-end behavior for "Solid on scroll": swap the
 * header between its transparent (at top) and solid (scrolled past) states.
 *
 * A hidden sentinel the height of the header is observed instead of listening
 * to scroll events: while the sentinel is in view the page is at (or near)
 * the top and the header stays transparent; once it leaves, the header's own
 * background returns. The server renders the transparent state so the first
 * paint is right; loading mid-page (anchor links, refresh) corrects on the
 * observer's initial callback.
 *
 * @package
 */

( function () {
	'use strict';

	function initTransparentHeader( header ) {
		// Confirms to the stylesheet that solid-on-scroll's JS half is alive
		// BEFORE the observer's first callback (which can take a frame) —
		// index.scss requires this class to promote the header to
		// position: fixed, so a header this script never reaches stays on
		// the base rule's position: absolute (scrolls away) instead of
		// sitting fixed and transparent over the page forever.
		header.classList.add( 'blocklane-pro-th-js' );

		const sentinel = document.createElement( 'div' );
		sentinel.setAttribute( 'aria-hidden', 'true' );
		sentinel.style.cssText =
			'position:absolute;top:0;left:0;width:1px;pointer-events:none;visibility:hidden;';
		sentinel.style.height = Math.max( header.offsetHeight, 1 ) + 'px';
		document.body.prepend( sentinel );

		const observer = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				// The solid state needs no class of its own — removing
				// is-th-transparent is enough to restore the group's own
				// background (index.scss). Only the class in the spec's
				// Delivery contract is toggled.
				header.classList.toggle(
					'is-th-transparent',
					entry.isIntersecting
				);
			} );
		} );
		observer.observe( sentinel );

		// Keep the swap point in step with the header's real height (responsive
		// wraps, admin bar, font loading).
		if ( window.ResizeObserver ) {
			new ResizeObserver( () => {
				sentinel.style.height =
					Math.max( header.offsetHeight, 1 ) + 'px';
			} ).observe( header );
		}
	}

	function init() {
		document
			.querySelectorAll(
				'.blocklane-pro-transparent-header.blocklane-pro-th-solidify'
			)
			.forEach( initTransparentHeader );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
