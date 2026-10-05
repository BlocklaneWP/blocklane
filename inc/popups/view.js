/**
 * Popups — front-end behavior: the trigger REGISTRY (time and manual arm
 * here; a contributing unit registers its own trigger types through
 * window.blocklanePopups.register — the second registry of the popups
 * vocabularies, edition-manifest rule 6), frequency cookies (seen +
 * dismissed windows), one-per-position slots with queueing, and
 * position-dependent a11y — center popups are real modals (focus trap,
 * scroll lock, Escape/overlay close, focus return); corner slide-ins are
 * non-modal. Plus the Popup Bindings surfaces (popup-bindings.md):
 * delegated click + keyboard openers, in-content closers,
 * #blocklane-popup-{id} hash deep-links, and the blocklane:popup-open /
 * blocklane:popup-close analytics events. Plain script on purpose
 * (video-modal precedent): nothing here hydrates server directives, and a
 * buildless file bakes cleanly.
 *
 * A popup whose trigger type has no arm in this edition is PARKED: it keeps
 * its stored trigger verbatim, opens only by click, hash or preview, and is
 * armed the moment a unit registers that type — even after init() ran.
 * Never coerced to a page-load popup.
 *
 * @package
 */

( function () {
	'use strict';

	const COOKIE_PREFIX = 'blocklane_popup_';
	const DAY = 86400000;

	// One open popup per position slot; later triggers queue behind it.
	const slots = {};
	const queues = {};

	function readCookie( name ) {
		const match = document.cookie.match(
			new RegExp( '(?:^|; )' + name + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	function writeCookie( name, value, days ) {
		document.cookie =
			name +
			'=' +
			encodeURIComponent( value ) +
			'; path=/; max-age=' +
			Math.max( 1, days ) * 86400 +
			'; samesite=lax';
	}

	// Cookie value is "seenTs:dismissedTs" (0 = never).
	function readStamps( id ) {
		const parts = readCookie( COOKIE_PREFIX + id ).split( ':' );
		return {
			seen: parseInt( parts[ 0 ], 10 ) || 0,
			dismissed: parseInt( parts[ 1 ], 10 ) || 0,
		};
	}

	function writeStamps( id, stamps, settings ) {
		// Both windows 0 = frequency is off entirely — set no cookie at all.
		if ( ! settings.frequency.seen && ! settings.frequency.dismissed ) {
			return;
		}
		const days = Math.max(
			settings.frequency.seen,
			settings.frequency.dismissed,
			1
		);
		writeCookie(
			COOKIE_PREFIX + id,
			stamps.seen + ':' + stamps.dismissed,
			days
		);
	}

	function isSuppressed( id, settings ) {
		const stamps = readStamps( id );
		const now = Date.now();
		if (
			settings.frequency.seen > 0 &&
			stamps.seen > 0 &&
			now < stamps.seen + settings.frequency.seen * DAY
		) {
			return true;
		}
		if (
			settings.frequency.dismissed > 0 &&
			stamps.dismissed > 0 &&
			now < stamps.dismissed + settings.frequency.dismissed * DAY
		) {
			return true;
		}
		return false;
	}

	function popupId( el ) {
		return el.id.replace( 'blocklane-popup-', '' );
	}

	// --- Modal a11y (center popups only) -----------------------------------

	let activeModal = null;
	let previousFocus = null;
	let scrollLocked = false;

	function lockScroll() {
		if ( scrollLocked ) {
			return;
		}
		const gutter = window.innerWidth - document.documentElement.clientWidth;
		document.body.style.paddingRight = gutter > 0 ? gutter + 'px' : '';
		document.body.style.overflow = 'hidden';
		scrollLocked = true;
	}

	function unlockScroll() {
		if ( ! scrollLocked ) {
			return;
		}
		document.body.style.overflow = '';
		document.body.style.paddingRight = '';
		scrollLocked = false;
	}

	function focusables( dialog ) {
		return Array.prototype.filter.call(
			dialog.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
			),
			function ( el ) {
				return el.offsetParent !== null;
			}
		);
	}

	function trapTab( event ) {
		if ( 'Tab' !== event.key || ! activeModal ) {
			return;
		}
		const items = focusables(
			activeModal.querySelector( '.blocklane-popup__dialog' )
		);
		if ( ! items.length ) {
			event.preventDefault();
			return;
		}
		const current = activeModal.ownerDocument.activeElement;
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];
		if ( event.shiftKey && current === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && current === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	// --- Open / close -------------------------------------------------------

	// Anchor the close button to the VISIBLE card, not the dialog box. The
	// dialog is an invisible layout surface (center = theme content width),
	// and a designed card narrower than it — a group with its own max-width,
	// a fixed-width cover — would leave the X floating in empty space.
	// Measured as the union of the content's child boxes; re-measured on
	// resize and content growth (images loading) via ResizeObserver.
	const closeObservers = new WeakMap();

	function positionClose( el ) {
		const button = el.querySelector( '.blocklane-popup__close' );
		const dialog = el.querySelector( '.blocklane-popup__dialog' );
		const content = el.querySelector( '.blocklane-popup__content' );
		if ( ! button || ! dialog || ! content ) {
			return;
		}
		let right = null;
		let top = null;
		Array.prototype.forEach.call( content.children, function ( child ) {
			const rect = child.getBoundingClientRect();
			if ( ! rect.width || ! rect.height ) {
				return;
			}
			right = null === right ? rect.right : Math.max( right, rect.right );
			top = null === top ? rect.top : Math.min( top, rect.top );
		} );
		if ( null === right ) {
			return;
		}
		const dialogRect = dialog.getBoundingClientRect();
		button.style.right =
			Math.max( 0, Math.round( dialogRect.right - right ) ) + 8 + 'px';
		button.style.top =
			Math.max( 0, Math.round( top - dialogRect.top ) ) + 8 + 'px';
	}

	function watchClose( el ) {
		positionClose( el );
		if ( ! window.ResizeObserver || closeObservers.has( el ) ) {
			return;
		}
		const observer = new window.ResizeObserver( function () {
			positionClose( el );
		} );
		observer.observe( el.querySelector( '.blocklane-popup__dialog' ) );
		observer.observe( el.querySelector( '.blocklane-popup__content' ) );
		closeObservers.set( el, observer );
	}

	function unwatchClose( el ) {
		const observer = closeObservers.get( el );
		if ( observer ) {
			observer.disconnect();
			closeObservers.delete( el );
		}
	}

	function open( el, settings, via ) {
		const slot = settings.position;

		if ( slots[ slot ] ) {
			// Slot busy — queue once (skip if this popup is already waiting).
			queues[ slot ] = queues[ slot ] || [];
			const queued = queues[ slot ].some( function ( entry ) {
				return entry.el === el;
			} );
			if ( ! queued && slots[ slot ] !== el ) {
				queues[ slot ].push( { el, settings, via } );
			}
			return;
		}

		slots[ slot ] = el;
		el.hidden = false;
		// Next frame so the unhide paints before is-open starts the animation
		// (and so the close anchor measures laid-out boxes).
		window.requestAnimationFrame( function () {
			el.classList.add( 'is-open' );
			watchClose( el );
		} );

		const stamps = readStamps( popupId( el ) );
		stamps.seen = Date.now();
		writeStamps( popupId( el ), stamps, settings );

		if ( settings.center ) {
			previousFocus = el.ownerDocument.activeElement;
			activeModal = el;
			lockScroll();
			el.querySelector( '.blocklane-popup__dialog' ).focus();
		}

		// Analytics hook (popup-bindings.md) — fires only on a real open,
		// never on queueing. Names and payloads are public API.
		document.dispatchEvent(
			new CustomEvent( 'blocklane:popup-open', {
				detail: {
					id: parseInt( popupId( el ), 10 ),
					via: via || 'manual',
				},
			} )
		);
	}

	// keepQueue skips the auto-dequeue: openReplacing closes the source popup
	// only to free the slot for the clicked target, so a popup queued behind
	// it must NOT jump in first (it would steal the slot and the target would
	// queue invisibly — the very thing openReplacing exists to prevent).
	function close( el, settings, dismissed, keepQueue ) {
		const slot = settings.position;

		if ( dismissed ) {
			const stamps = readStamps( popupId( el ) );
			stamps.dismissed = Date.now();
			writeStamps( popupId( el ), stamps, settings );
		}

		el.classList.remove( 'is-open' );
		el.hidden = true;
		unwatchClose( el );

		if ( slots[ slot ] === el ) {
			slots[ slot ] = null;
		}
		if ( activeModal === el ) {
			activeModal = null;
			unlockScroll();
			if ( previousFocus && previousFocus.focus ) {
				previousFocus.focus();
			}
			previousFocus = null;
		}

		document.dispatchEvent(
			new CustomEvent( 'blocklane:popup-close', {
				detail: {
					id: parseInt( popupId( el ), 10 ),
					dismissed: !! dismissed,
				},
			} )
		);

		if ( keepQueue ) {
			return;
		}
		const next = ( queues[ slot ] || [] ).shift();
		if ( next ) {
			open( next.el, next.settings, next.via );
		}
	}

	// --- Trigger registry ---------------------------------------------------
	//
	// type => arm( el, settings, fire ). A unit registers the types it owns;
	// popups whose type has no arm yet wait in `pending` and are armed by the
	// registration that brings it — so script order cannot lose a popup, and
	// a type this edition never registers stays parked (opened by click, hash
	// or preview only). One registration per type: the last wins, as with
	// any registry, and the base file registers exactly its own two.
	const arms = {};
	const pending = {};

	function register( type, arm ) {
		arms[ type ] = arm;
		( pending[ type ] || [] ).forEach( function ( entry ) {
			arm( entry.el, entry.settings, entry.fire );
		} );
		delete pending[ type ];
	}

	window.blocklanePopups = window.blocklanePopups || {};
	window.blocklanePopups.register = register;

	register( 'time', function ( el, settings, fire ) {
		window.setTimeout( fire, settings.trigger.value * 1000 );
	} );
	// Manual arms nothing — the delegated click openers below are the only
	// way in. Registered explicitly so "no arm" always means "not in this
	// edition", never "forgot".
	register( 'manual', function () {} );

	function armTrigger( el, settings ) {
		let fired = false;
		const fire = function () {
			if ( fired ) {
				return;
			}
			fired = true;
			open( el, settings, settings.trigger.type );
		};

		const type = settings.trigger.type;
		if ( arms[ type ] ) {
			arms[ type ]( el, settings, fire );
			return;
		}
		( pending[ type ] = pending[ type ] || [] ).push( {
			el,
			settings,
			fire,
		} );
	}

	// --- Init ---------------------------------------------------------------

	function init() {
		const popups = [];

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-blocklane-popup]' ),
			function ( el ) {
				let settings;
				try {
					settings = JSON.parse(
						el.getAttribute( 'data-blocklane-popup' )
					);
				} catch ( e ) {
					return;
				}
				popups.push( { el, settings } );

				// Preview force-open bypasses cookies, minWidth, and triggers.
				if ( settings.force ) {
					open( el, settings, 'preview' );
					return;
				}

				// A CSS length (px/em/rem) — matchMedia does the unit math.
				if (
					settings.minWidth &&
					! window.matchMedia(
						'(min-width: ' + settings.minWidth + ')'
					).matches
				) {
					return;
				}
				// Editors (settings.bypass) skip frequency suppression so a
				// test dismissal can't silence the popup while designing.
				if (
					! settings.bypass &&
					isSuppressed( popupId( el ), settings )
				) {
					return;
				}

				armTrigger( el, settings );
			}
		);

		if ( ! popups.length ) {
			return;
		}

		function findPopup( id ) {
			return popups.filter( function ( p ) {
				return popupId( p.el ) === String( id );
			} )[ 0 ];
		}

		// An opener inside an open popup replaces it (funnel flows): close
		// the source — not a dismissal — so the target can take its slot
		// instead of queueing invisibly behind it.
		function openReplacing( origin, entry, via ) {
			const container = origin.closest( '.blocklane-popup' );
			if ( container && container !== entry.el ) {
				const containerEntry = popups.filter( function ( p ) {
					return p.el === container;
				} )[ 0 ];
				if ( containerEntry ) {
					// keepQueue: the target takes the freed slot, not a popup
					// queued behind the source.
					close( container, containerEntry.settings, false, true );
				}
			}
			open( entry.el, entry.settings, via );
		}

		// Manual openers work for every popup regardless of trigger:
		// a link to #blocklane-popup-{id} or a .blocklane-popup-open-{id}
		// class on any element (core Button blocks included).
		document.addEventListener( 'click', function ( event ) {
			const target = event.target.closest(
				'a[href*="#blocklane-popup-"], [class*="blocklane-popup-open-"]'
			);
			if ( ! target ) {
				return;
			}
			// Don't hijack a click on an interactive element nested inside a
			// class-based opener wrapper (a link or button inside a bound
			// Cover) — it keeps its own behavior.
			const interactive = event.target.closest( 'a[href], button' );
			if (
				interactive &&
				interactive !== target &&
				target.contains( interactive )
			) {
				return;
			}
			let id = '';
			if ( 'A' === target.tagName && target.hasAttribute( 'href' ) ) {
				// Same-page openers only: a bare #blocklane-popup-{id}
				// fragment. A cross-page deep link (…/page#blocklane-popup-3)
				// navigates and opens on arrival via the hash handler.
				const match = ( target.getAttribute( 'href' ) || '' ).match(
					/^#blocklane-popup-(\d+)$/
				);
				if ( ! match ) {
					return;
				}
				id = match[ 1 ];
			} else {
				const match = target.className.match(
					/blocklane-popup-open-(\d+)/
				);
				id = match ? match[ 1 ] : '';
			}
			const entry = findPopup( id );
			if ( entry ) {
				event.preventDefault();
				openReplacing( target, entry, 'click' );
			}
		} );

		// Close paths: the close button, in-content closers (data-popup-close
		// — Popup Bindings render dismiss buttons with it), the overlay
		// (center), Escape. preventDefault so a closer anchor's href="#"
		// never scroll-jumps.
		document.addEventListener( 'click', function ( event ) {
			const closer = event.target.closest(
				'[data-popup-close], [data-popup-overlay]'
			);
			if ( ! closer ) {
				return;
			}
			// A dismiss button renders as an anchor with href="#" (kept
			// focusable). Swallow its default here so it never scroll-jumps to
			// the top — including when it was copied outside a popup, where
			// there's nothing to close.
			if (
				closer.matches( '[data-popup-close]' ) &&
				'A' === closer.tagName
			) {
				event.preventDefault();
			}
			const el = closer.closest( '.blocklane-popup' );
			const entry = popups.filter( function ( p ) {
				return p.el === el;
			} )[ 0 ];
			if ( entry && entry.settings.closable ) {
				event.preventDefault();
				close( el, entry.settings, true );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				// The modal, else a corner popup the key landed inside
				// (keydown targets the focused element).
				const el =
					activeModal ||
					( event.target && event.target.closest
						? event.target.closest( '.blocklane-popup' )
						: null );
				const entry = popups.filter( function ( p ) {
					return p.el === el;
				} )[ 0 ];
				if ( entry && entry.settings.closable ) {
					close( el, entry.settings, true );
				}
				return;
			}

			// Keyboard activation for non-anchor openers — image/cover
			// wrappers carry role="button" + tabindex (popup-bindings.md);
			// anchors and real buttons already synthesize clicks natively.
			if ( 'Enter' === event.key || ' ' === event.key ) {
				const opener =
					event.target && event.target.closest
						? event.target.closest(
								'[class*="blocklane-popup-open-"]'
							)
						: null;
				// Activation must originate on the opener itself, not a nested
				// interactive descendant (a link/button inside a bound Cover).
				if (
					opener &&
					event.target !== opener &&
					event.target.closest &&
					event.target.closest( 'a[href], button' )
				) {
					return;
				}
				if ( opener && ! opener.closest( 'a, button' ) ) {
					const match = opener.className.match(
						/blocklane-popup-open-(\d+)/
					);
					const entry = match && findPopup( match[ 1 ] );
					if ( entry ) {
						// Space must not scroll the page.
						event.preventDefault();
						openReplacing( opener, entry, 'click' );
					}
				}
				return;
			}

			trapTab( event );
		} );

		// Hash deep-links (popup-bindings.md): #blocklane-popup-{id} in the
		// URL opens the popup on arrival (email campaigns, external links)
		// and on hash changes (browser back/forward). Same-page fragment
		// clicks are intercepted above (preventDefault), so no double-open.
		// Explicit navigation bypasses frequency suppression, like clicks.
		function openFromHash() {
			const match = ( window.location.hash || '' ).match(
				/^#blocklane-popup-(\d+)$/
			);
			const entry = match && findPopup( match[ 1 ] );
			if ( entry ) {
				open( entry.el, entry.settings, 'hash' );
			}
		}
		window.addEventListener( 'hashchange', openFromHash );
		openFromHash();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
