/**
 * Transparent Header — front-end behavior: the clearance below the overlay
 * (every mode), and for "Solid on scroll" the swap between the transparent
 * (at top) and solid (scrolled past) states.
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

	/*
	 * Clearance below the overlay (index.scss): the first section after an
	 * overlaid header pads at least the header's height plus a gap. The
	 * stylesheet holds without this script on a 6rem estimate; this measures
	 * the real height (plus the Top Offset) onto <html>, and copies each
	 * section's own inline top padding into --blocklane-pro-th-own-pad so the
	 * stylesheet's max() keeps a larger one.
	 */
	const CLEARED = [
		':where(.wp-site-blocks > :has(.wp-block-group.blocklane-pro-transparent-header)) + * > .alignfull:first-child:not(:has(> .alignfull:first-child))',
		':where(.wp-site-blocks > :has(.wp-block-group.blocklane-pro-transparent-header)) + * > :first-child > .alignfull:first-child:not(:has(> .alignfull:first-child))',
		':where(.wp-site-blocks > :has(.wp-block-group.blocklane-pro-transparent-header)) + * > :first-child > :first-child > .alignfull:first-child:not(:has(> .alignfull:first-child))',
		':where(.wp-site-blocks > :has(.wp-block-group.blocklane-pro-transparent-header)) + :not(:has(> .alignfull:first-child, > :first-child > .alignfull:first-child, > :first-child > :first-child > .alignfull:first-child))',
	].join( ',' );

	function measureClearance( header ) {
		const root = document.documentElement;
		const update = () => {
			const offset =
				parseFloat(
					getComputedStyle( header ).getPropertyValue(
						'--blocklane-pro-th-offset'
					)
				) || 0;
			root.style.setProperty(
				'--blocklane-pro-th-height',
				Math.ceil( header.offsetHeight + offset ) + 'px'
			);
		};
		update();
		document.querySelectorAll( CLEARED ).forEach( ( section ) => {
			if ( section.style.paddingTop ) {
				section.style.setProperty(
					'--blocklane-pro-th-own-pad',
					section.style.paddingTop
				);
			}
		} );
		if ( window.ResizeObserver ) {
			new ResizeObserver( update ).observe( header );
		}
	}

	function init() {
		const overlaid = document.querySelector(
			'.wp-block-group.blocklane-pro-transparent-header'
		);
		if ( overlaid ) {
			measureClearance( overlaid );
		}
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
