/**
 * The carousel's store: the front end of a carousel block suite, on
 * WordPress's Interactivity API.
 *
 * Each carousel is driven from its shell. The shell's init callback reads
 * the row's settings, takes the row over and keeps what it learns in a
 * record keyed by the shell. Every other directive finds that record through
 * its own element's nearest shell, so a carousel nested inside another's
 * slide is driven on its own.
 */

import {
	store,
	getContext,
	getElement,
	withScope,
	withSyncEvent,
} from '@wordpress/interactivity';

const SHELL = '.wp-block-blocklane-carousel';
const VIEWPORT_CLASS = 'blocklane-carousel-viewport';
const CONTAINER_CLASS = 'blocklane-carousel-container';
const SLIDE_CLASS = 'blocklane-carousel-slide';
const CLONE_CLASS = 'blocklane-carousel-clone';
const DOT_CLASS = 'blocklane-carousel-dot';
const CONTROLS =
	'.blocklane-carousel-prev, .blocklane-carousel-next, .blocklane-carousel-pagination, .blocklane-carousel-scrollbar';
const SCROLLBAR = '.blocklane-carousel-scrollbar';
const FRAME_MS = 16.667;

const DEFAULT_STRINGS = {
	slideLabel: '%1$d of %2$d',
	goToSlide: 'Go to slide %1$d of %2$d',
	pause: 'Pause autoplay',
	play: 'Play autoplay',
	scrollbar: 'Carousel scroll bar',
	slideRole: 'slide',
};

/** Live carousels, keyed by their shell. */
const carousels = new WeakMap();

/** How many row ids the store has made. */
let madeIds = 0;

/**
 * Hold a number within a range.
 *
 * @param {number} value The number.
 * @param {number} min   The lowest it may be.
 * @param {number} max   The highest it may be.
 * @return {number} The number, held within the range.
 */
function clamp( value, min, max ) {
	return Math.min( max, Math.max( min, value ) );
}

/**
 * Wrap an index around a count.
 *
 * @param {number} value The index, possibly outside the count.
 * @param {number} count The count.
 * @return {number} The index, from 0 to the count less one.
 */
function wrap( value, count ) {
	return ( ( value % count ) + count ) % count;
}

/**
 * The in-out cubic curve.
 *
 * @param {number} p The progress, from 0 to 1.
 * @return {number} The eased progress.
 */
function ease( p ) {
	return p < 0.5 ? 4 * p * p * p : 1 - Math.pow( -2 * p + 2, 3 ) / 2;
}

/**
 * Whether a value is a finite number within a range.
 *
 * @param {unknown} value The value.
 * @param {number}  min   The lowest it may be.
 * @param {number}  max   The highest it may be.
 * @return {boolean} Whether it is.
 */
function inRange( value, min, max ) {
	return (
		'number' === typeof value &&
		Number.isFinite( value ) &&
		value >= min &&
		value <= max
	);
}

/**
 * Put two numbers into a string's `%1$d` and `%2$d`.
 *
 * @param {string} text   The string.
 * @param {number} first  The first number.
 * @param {number} second The second number.
 * @return {string} The string with the numbers in place.
 */
function format( text, first, second ) {
	return text
		.split( '%1$d' )
		.join( String( first ) )
		.split( '%2$d' )
		.join( String( second ) );
}

/**
 * Whether the visitor asks for reduced motion.
 *
 * @return {boolean} Whether reduced motion is requested.
 */
function prefersReducedMotion() {
	return (
		'function' === typeof window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * The record of the carousel an element belongs to.
 *
 * @param {?Element} el An element inside a carousel, or its shell.
 * @return {Object|undefined} The record, while the carousel is live.
 */
function recordOf( el ) {
	const shell = el && el.closest ? el.closest( SHELL ) : null;
	return shell ? carousels.get( shell ) : undefined;
}

/**
 * The elements matching a selector that a shell owns (those not inside a
 * nested shell).
 *
 * @param {Element} shell    The shell.
 * @param {string}  selector The selector.
 * @return {Element[]} The owned elements.
 */
function owned( shell, selector ) {
	return Array.from( shell.querySelectorAll( selector ) ).filter(
		( el ) => el.closest( SHELL ) === shell
	);
}

/**
 * The first child of an element that carries a class.
 *
 * @param {Element} parent    The element.
 * @param {string}  className The class.
 * @return {?Element} The child, or null.
 */
function childWithClass( parent, className ) {
	return (
		Array.from( parent.children ).find( ( child ) =>
			child.classList.contains( className )
		) || null
	);
}

/**
 * A number setting from the row.
 *
 * @param {Element} row      The row.
 * @param {string}  name     The attribute's name after `data-bl-carousel-`.
 * @param {number}  fallback The default.
 * @return {number} The setting.
 */
function readNumber( row, name, fallback ) {
	const raw = row.getAttribute( 'data-bl-carousel-' + name );
	if ( null === raw || '' === raw.trim() ) {
		return fallback;
	}
	const value = Number( raw );
	return Number.isFinite( value ) ? value : fallback;
}

/**
 * A true-or-false setting from the row.
 *
 * @param {Element} row  The row.
 * @param {string}  name The attribute's name after `data-bl-carousel-`.
 * @return {boolean} True exactly when the value is `true`.
 */
function readFlag( row, name ) {
	return 'true' === row.getAttribute( 'data-bl-carousel-' + name );
}

/**
 * A JSON setting from the row.
 *
 * @param {Element} row  The row.
 * @param {string}  name The attribute's name after `data-bl-carousel-`.
 * @return {unknown} The parsed value, or null when it does not parse.
 */
function readJson( row, name ) {
	const raw = row.getAttribute( 'data-bl-carousel-' + name );
	if ( ! raw ) {
		return null;
	}
	try {
		return JSON.parse( raw );
	} catch {
		return null;
	}
}

/**
 * The row's breakpoints, narrowest first.
 *
 * @param {Element} row The row.
 * @return {Object[]} Each with `width`, and `perView` and `perGroup` when set.
 */
function readBreakpoints( row ) {
	const list = readJson( row, 'breakpoints' );
	if ( ! Array.isArray( list ) ) {
		return [];
	}
	const kept = [];
	for ( const entry of list ) {
		if (
			! entry ||
			'object' !== typeof entry ||
			! inRange( entry.width, 320, 3840 )
		) {
			continue;
		}
		kept.push( {
			width: entry.width,
			perView: inRange( entry.slidesPerView, 1, 10 )
				? entry.slidesPerView
				: undefined,
			perGroup: inRange( entry.slidesPerGroup, 1, 10 )
				? Math.trunc( entry.slidesPerGroup )
				: undefined,
		} );
	}
	return kept.sort( ( a, b ) => a.width - b.width );
}

/**
 * The row's strings, over the defaults.
 *
 * @param {Element} row The row.
 * @return {Object} The strings, by name.
 */
function readStrings( row ) {
	const parsed = readJson( row, 'i18n' );
	const strings = { ...DEFAULT_STRINGS };
	if ( parsed && 'object' === typeof parsed ) {
		for ( const name of Object.keys( DEFAULT_STRINGS ) ) {
			if ( 'string' === typeof parsed[ name ] ) {
				strings[ name ] = parsed[ name ];
			}
		}
	}
	return strings;
}

/**
 * Every setting of a carousel, read from its row.
 *
 * @param {Element} row The row.
 * @return {Object} The settings.
 */
function readSettings( row ) {
	return {
		perView: readNumber( row, 'per-view', 3 ),
		perGroup: readNumber( row, 'per-group', 1 ),
		minWidth: readNumber( row, 'min-slide-width', 0 ),
		speed: readNumber( row, 'speed', 300 ),
		delay: readNumber( row, 'autoplay-speed', 3000 ),
		scrollSpeed: readNumber( row, 'scroll-speed', 2 ),
		loop: readFlag( row, 'loop' ),
		autoplay: readFlag( row, 'autoplay' ),
		pauseOnHover: readFlag( row, 'pause-on-hover' ),
		wheel: readFlag( row, 'wheel-gestures' ),
		fade: readFlag( row, 'fade' ),
		rtl: readFlag( row, 'rtl' ) || 'rtl' === row.getAttribute( 'dir' ),
		style:
			'scroll' === row.getAttribute( 'data-bl-carousel-autoplay-style' )
				? 'scroll'
				: 'slide',
		breakpoints: readBreakpoints( row ),
		strings: readStrings( row ),
	};
}

/**
 * Decide the slides per view and per group for the current window and row
 * width, and write the per view on the row.
 *
 * @param {Object} rec The carousel's record.
 */
function decideView( rec ) {
	const { s, row } = rec;
	let perView = s.perView;
	let group = s.perGroup;
	if ( 'function' === typeof window.matchMedia ) {
		for ( const point of s.breakpoints ) {
			const query = '(max-width: ' + point.width + 'px)';
			if ( window.matchMedia( query ).matches ) {
				if ( undefined !== point.perView ) {
					perView = point.perView;
				}
				if ( undefined !== point.perGroup ) {
					group = point.perGroup;
				}
				break;
			}
		}
	}
	const width = row.clientWidth;
	if ( s.minWidth > 0 && width > 0 && width / perView < s.minWidth ) {
		perView = Math.min(
			perView,
			Math.max( 1, Math.floor( width / s.minWidth ) )
		);
	}
	group = Math.max( 1, Math.floor( group ) );
	rec.view = { perView, group };
	row.style.setProperty( '--blocklane-carousel-per-view', String( perView ) );
}

/**
 * The scroll position at which an element's start edge meets the row's.
 *
 * @param {Object}  rec The carousel's record.
 * @param {Element} el  A slide or a copy.
 * @return {number} The position.
 */
function positionOf( rec, el ) {
	const box = el.getBoundingClientRect();
	const frame = rec.row.getBoundingClientRect();
	const edge = rec.s.rtl ? box.right - frame.right : box.left - frame.left;
	return edge + rec.row.scrollLeft;
}

/**
 * How far the row can scroll.
 *
 * @param {Object} rec The carousel's record.
 * @return {number} The overflow, 0 or more.
 */
function overflowOf( rec ) {
	return Math.max( 0, rec.row.scrollWidth - rec.row.clientWidth );
}

/**
 * Hold a scroll position within the row's range.
 *
 * @param {Object} rec The carousel's record.
 * @param {number} x   The position.
 * @return {number} The position, held within the range.
 */
function clampToRange( rec, x ) {
	const max = overflowOf( rec );
	return rec.s.rtl ? clamp( x, -max, 0 ) : clamp( x, 0, max );
}

/**
 * Measure the snaps.
 *
 * @param {Object} rec The carousel's record.
 * @return {number} The snap count.
 */
function measure( rec ) {
	const stops = [];
	for ( let i = 0; i < rec.real.length; i += rec.view.group ) {
		if ( rec.s.fade ) {
			stops.push( 0 );
		} else if ( rec.s.loop ) {
			stops.push( positionOf( rec, rec.real[ i ] ) );
		} else {
			const x = clampToRange( rec, positionOf( rec, rec.real[ i ] ) );
			if (
				stops.length &&
				Math.abs( x - stops[ stops.length - 1 ] ) <= 1
			) {
				break;
			}
			stops.push( x );
		}
	}
	rec.stops = stops;
	return stops.length;
}

/**
 * The position of the first copy after the real slides (the step past the
 * last snap, with loop).
 *
 * @param {Object} rec The carousel's record.
 * @return {?number} The position, or null with no such copy.
 */
function pastLastPosition( rec ) {
	const copy = rec.copies.find(
		( entry ) => entry.after && 0 === entry.twin
	);
	return copy ? positionOf( rec, copy.el ) : null;
}

/**
 * The position of the copy before the real slides that is the twin of the
 * last snap's first slide (the step before the first snap, with loop).
 *
 * @param {Object} rec The carousel's record.
 * @return {?number} The position, or null with no such copy.
 */
function beforeFirstPosition( rec ) {
	const lastFirst = ( rec.stops.length - 1 ) * rec.view.group;
	const copy = rec.copies.find(
		( entry ) => ! entry.after && lastFirst === entry.twin
	);
	return copy ? positionOf( rec, copy.el ) : null;
}

/**
 * The index of the candidate whose position is nearest the row's scroll
 * position.
 *
 * @param {Object}  rec        The carousel's record.
 * @param {Array[]} candidates Pairs of an index and a position.
 * @return {number} The nearest candidate's index.
 */
function nearest( rec, candidates ) {
	const x = rec.row.scrollLeft;
	let best = candidates.length ? candidates[ 0 ][ 0 ] : 0;
	let gap = Infinity;
	for ( const [ index, position ] of candidates ) {
		const distance = Math.abs( position - x );
		if ( distance < gap ) {
			gap = distance;
			best = index;
		}
	}
	return best;
}

/**
 * The current snap, from the scroll position.
 *
 * @param {Object} rec The carousel's record.
 * @return {number} The snap.
 */
function snapFromPosition( rec ) {
	const count = rec.stops.length;
	if ( rec.s.fade ) {
		return rec.ctx.snap;
	}
	if ( rec.s.loop ) {
		const candidates = rec.real.map( ( el, i ) => [
			i,
			positionOf( rec, el ),
		] );
		for ( const copy of rec.copies ) {
			candidates.push( [ copy.twin, positionOf( rec, copy.el ) ] );
		}
		const slide = nearest( rec, candidates );
		return Math.min( count - 1, Math.floor( slide / rec.view.group ) );
	}
	return nearest(
		rec,
		rec.stops.map( ( position, k ) => [ k, position ] )
	);
}

/**
 * The scrollbar's thumb size and start, as percentages of its track.
 *
 * @param {Object} rec   The carousel's record.
 * @param {number} snap  The current snap.
 * @param {number} snaps The snap count.
 * @return {Object} `size` and `start`.
 */
function thumbValues( rec, snap, snaps ) {
	const count = Math.max( 1, snaps );
	const size = Math.max( 10, 100 / count );
	let progress = 0;
	if ( rec.s.loop || rec.s.fade ) {
		progress = count > 1 ? snap / ( count - 1 ) : 0;
	} else {
		const max = overflowOf( rec );
		progress =
			max > 0 ? Math.min( 1, Math.abs( rec.row.scrollLeft ) / max ) : 0;
	}
	const start = ( 100 - size ) * ( rec.s.rtl ? 1 - progress : progress );
	return { size, start };
}

/**
 * A percentage with at most three decimals.
 *
 * @param {number} value The number.
 * @return {string} The percentage.
 */
function percent( value ) {
	return Math.round( value * 1000 ) / 1000 + '%';
}

/**
 * Write the thumb's size and start on one scrollbar.
 *
 * @param {Object}  rec   The carousel's record.
 * @param {Element} bar   The scrollbar.
 * @param {number}  snap  The current snap.
 * @param {number}  snaps The snap count.
 */
function writeThumb( rec, bar, snap, snaps ) {
	const { size, start } = thumbValues( rec, snap, snaps );
	bar.style.setProperty( '--blocklane-carousel-thumb-size', percent( size ) );
	bar.style.setProperty(
		'--blocklane-carousel-thumb-start',
		percent( start )
	);
}

/**
 * Write the thumb on every scrollbar the shell owns.
 *
 * @param {Object} rec The carousel's record.
 */
function updateScrollbars( rec ) {
	const snap = rec.ctx.snap;
	const snaps = rec.ctx.snaps;
	for ( const bar of rec.scrollbars ) {
		writeThumb( rec, bar, snap, snaps );
	}
}

/**
 * Mark the row as moving while a step animates or continuous autoplay runs.
 *
 * @param {Object} rec The carousel's record.
 */
function syncMoving( rec ) {
	rec.ctx.moving =
		Boolean( rec.move ) || Boolean( rec.auto && rec.auto.scrolling );
}

/**
 * Bring a looping row from a copy onto its twin, then set the current snap
 * from the position and update the scrollbar.
 *
 * @param {Object} rec The carousel's record.
 */
function settle( rec ) {
	if ( ! rec.s.fade ) {
		if ( rec.s.loop ) {
			const x = rec.row.scrollLeft;
			for ( const copy of rec.copies ) {
				if ( Math.abs( positionOf( rec, copy.el ) - x ) <= 1 ) {
					rec.row.scrollLeft = positionOf(
						rec,
						rec.real[ copy.twin ]
					);
					break;
				}
			}
		}
		rec.ctx.snap = snapFromPosition( rec );
	}
	updateScrollbars( rec );
}

/**
 * Stop a step under way.
 *
 * @param {Object} rec The carousel's record.
 */
function cancelMove( rec ) {
	if ( rec.move ) {
		window.cancelAnimationFrame( rec.move.frame );
		rec.move = null;
	}
}

/**
 * Move the row to a scroll position, animated unless it need not be.
 *
 * @param {Object}  rec     The carousel's record.
 * @param {number}  to      The position.
 * @param {boolean} instant Whether to set it at once.
 */
function moveTo( rec, to, instant ) {
	cancelMove( rec );
	const from = rec.row.scrollLeft;
	if (
		instant ||
		rec.s.speed <= 0 ||
		prefersReducedMotion() ||
		Math.abs( to - from ) < 1
	) {
		rec.row.scrollLeft = to;
		syncMoving( rec );
		settle( rec );
		return;
	}
	rec.move = { from, to, start: null, frame: 0 };
	rec.move.frame = window.requestAnimationFrame( rec.animate );
	syncMoving( rec );
}

/**
 * One frame of a step.
 *
 * @param {Object} rec  The carousel's record.
 * @param {number} time The frame's time.
 */
function animate( rec, time ) {
	const move = rec.move;
	if ( ! move ) {
		return;
	}
	if ( null === move.start ) {
		move.start = time;
	}
	const p = Math.min( 1, ( time - move.start ) / rec.s.speed );
	if ( p < 1 ) {
		rec.row.scrollLeft = move.from + ( move.to - move.from ) * ease( p );
		move.frame = window.requestAnimationFrame( rec.animate );
		return;
	}
	rec.row.scrollLeft = move.to;
	rec.move = null;
	syncMoving( rec );
	settle( rec );
}

/**
 * Go to a snap.
 *
 * @param {Object}  rec     The carousel's record.
 * @param {number}  k       The snap; with loop, one past either end steps
 *                          onto a copy.
 * @param {boolean} instant Whether to move without animation.
 */
function goTo( rec, k, instant = false ) {
	const count = rec.stops.length;
	if ( ! count ) {
		return;
	}
	const { ctx, s } = rec;
	if ( s.fade ) {
		ctx.snap = s.loop ? wrap( k, count ) : clamp( k, 0, count - 1 );
		updateScrollbars( rec );
		return;
	}
	if ( ! s.loop ) {
		const target = clamp( k, 0, count - 1 );
		ctx.snap = target;
		moveTo( rec, rec.stops[ target ], instant );
		return;
	}
	const target = wrap( k, count );
	ctx.snap = target;
	let to = rec.stops[ target ];
	if ( k > count - 1 ) {
		const past = pastLastPosition( rec );
		to = null === past ? to : past;
	} else if ( k < 0 ) {
		const before = beforeFirstPosition( rec );
		to = null === before ? to : before;
	}
	moveTo( rec, to, instant );
}

/**
 * Whether an arrow is off: at an end of a carousel that does not loop. It
 * reads only the context, so a state getter that calls it is evaluated
 * again whenever the snap or the count changes.
 *
 * @param {Object} rec       The carousel's record.
 * @param {number} direction -1 for the previous arrow, 1 for the next.
 * @return {boolean} Whether it is off.
 */
function isOff( rec, direction ) {
	if ( rec.s.loop ) {
		return false;
	}
	return direction < 0
		? rec.ctx.snap <= 0
		: rec.ctx.snap >= rec.ctx.snaps - 1;
}

/**
 * The stop nearest the scroll position, for the end of a drag: a snap, or
 * with loop the step past the last snap or before the first.
 *
 * @param {Object} rec The carousel's record.
 * @return {number} The snap to go to.
 */
function nearestStop( rec ) {
	const candidates = rec.stops.map( ( position, k ) => [ k, position ] );
	if ( rec.s.loop ) {
		const past = pastLastPosition( rec );
		if ( null !== past ) {
			candidates.push( [ rec.stops.length, past ] );
		}
		const before = beforeFirstPosition( rec );
		if ( null !== before ) {
			candidates.push( [ -1, before ] );
		}
	}
	return nearest( rec, candidates );
}

/**
 * Whether a slide is out of view at a snap.
 *
 * @param {Object} rec   The carousel's record.
 * @param {number} index The slide's index among the real slides.
 * @param {number} snap  The snap.
 * @return {boolean} Whether it is out of view.
 */
function outOfView( rec, index, snap ) {
	const count = rec.real.length;
	const first = clamp( snap * rec.view.group, 0, count - 1 );
	if ( rec.s.fade ) {
		return index !== first;
	}
	const shown = Math.max( 1, Math.ceil( rec.view.perView ) );
	if ( shown >= count ) {
		return false;
	}
	if ( rec.s.loop ) {
		return wrap( index - first, count ) >= shown;
	}
	const start = clamp( first, 0, count - shown );
	return index < start || index >= start + shown;
}

/**
 * Whether autoplay can run now.
 *
 * @param {Object} rec The carousel's record.
 * @return {boolean} Whether it can.
 */
function canAutoplay( rec ) {
	const { ctx, s } = rec;
	return (
		Boolean( ctx.live ) &&
		s.autoplay &&
		! ctx.paused &&
		! ( s.pauseOnHover && rec.hover ) &&
		! rec.focus &&
		! ctx.dragging &&
		! ctx.scrubbing
	);
}

/**
 * Start autoplay when it can run and is stopped, or stop it when it cannot.
 *
 * @param {Object} rec The carousel's record.
 */
function syncAutoplay( rec ) {
	if ( canAutoplay( rec ) ) {
		if ( ! rec.auto ) {
			rec.auto = {
				last: null,
				elapsed: 0,
				pos: null,
				scrolling: 'scroll' === rec.s.style && ! rec.s.fade,
				frame: 0,
			};
			rec.auto.frame = window.requestAnimationFrame( rec.autoplay );
			syncMoving( rec );
		}
		return;
	}
	if ( rec.auto ) {
		window.cancelAnimationFrame( rec.auto.frame );
		rec.auto = null;
		syncMoving( rec );
	}
}

/**
 * Autoplay by steps: count the time that passed, and step after each delay.
 *
 * @param {Object} rec    The carousel's record.
 * @param {number} passed Milliseconds since the last frame.
 */
function autoplayStep( rec, passed ) {
	if ( rec.move ) {
		return;
	}
	rec.auto.elapsed += passed;
	if ( rec.auto.elapsed < rec.s.delay ) {
		return;
	}
	rec.auto.elapsed = 0;
	const next = rec.ctx.snap + 1;
	goTo( rec, ! rec.s.loop && next >= rec.stops.length ? 0 : next );
}

/**
 * Continuous autoplay: move the row forward by the scroll speed.
 *
 * @param {Object} rec    The carousel's record.
 * @param {number} passed Milliseconds since the last frame.
 */
function autoplayScroll( rec, passed ) {
	if ( rec.move || passed <= 0 ) {
		return;
	}
	const { row, s, auto } = rec;
	const direction = s.rtl ? -1 : 1;
	const here =
		null !== auto.pos && Math.abs( row.scrollLeft - auto.pos ) < 1
			? auto.pos
			: row.scrollLeft;
	let next = here + direction * s.scrollSpeed * ( passed / FRAME_MS );
	if ( s.loop ) {
		const seam = pastLastPosition( rec );
		if ( null !== seam && direction * ( next - seam ) >= 0 ) {
			next -= seam - positionOf( rec, rec.real[ 0 ] );
		}
	} else if ( direction * next >= overflowOf( rec ) ) {
		next = 0;
	}
	row.scrollLeft = next;
	auto.pos = next;
	rec.ctx.snap = snapFromPosition( rec );
	updateScrollbars( rec );
}

/**
 * One frame of autoplay.
 *
 * @param {Object} rec  The carousel's record.
 * @param {number} time The frame's time.
 */
function autoplayFrame( rec, time ) {
	const auto = rec.auto;
	if ( ! auto ) {
		return;
	}
	const passed = null === auto.last ? 0 : time - auto.last;
	auto.last = time;
	if ( auto.scrolling ) {
		autoplayScroll( rec, passed );
	} else {
		autoplayStep( rec, passed );
	}
	if ( rec.auto === auto ) {
		auto.frame = window.requestAnimationFrame( rec.autoplay );
	}
}

/**
 * Re-decide the view after the row is resized, and keep the slide that began
 * the current snap in view.
 *
 * @param {Object} rec The carousel's record.
 */
function resized( rec ) {
	if ( carousels.get( rec.shell ) !== rec ) {
		return;
	}
	const first = rec.ctx.snap * rec.view.group;
	const before = rec.view;
	decideView( rec );
	const count = measure( rec );
	rec.ctx.snaps = count;
	goTo(
		rec,
		Math.min( Math.floor( first / rec.view.group ), count - 1 ),
		true
	);
	if (
		before.perView !== rec.view.perView ||
		before.group !== rec.view.group
	) {
		// The view is not reactive; flipping `live` and back has every
		// directive that reads it evaluate again against the new view.
		rec.ctx.live = false;
		rec.ctx.live = true;
	}
}

/**
 * A row id no element on the page has.
 *
 * @param {Document} doc The page.
 * @return {string} The id.
 */
function makeId( doc ) {
	let id = '';
	do {
		madeIds += 1;
		id = 'blocklane-carousel-' + madeIds;
	} while ( doc.getElementById( id ) );
	return id;
}

/**
 * Let go of a carousel.
 *
 * @param {Object} rec The carousel's record.
 */
function release( rec ) {
	cancelMove( rec );
	if ( rec.auto ) {
		window.cancelAnimationFrame( rec.auto.frame );
		rec.auto = null;
	}
	if ( rec.observer ) {
		rec.observer.disconnect();
		rec.observer = null;
	}
	carousels.delete( rec.shell );
	rec.ctx.live = false;
	rec.ctx.moving = false;
	rec.row.style.removeProperty( '--blocklane-carousel-speed' );
	if ( rec.madeId ) {
		rec.row.removeAttribute( 'id' );
	}
}

/**
 * The live record and context of the element a directive is on.
 *
 * @return {?Object} `ctx` and `rec`, or null while the carousel is not live.
 */
function liveScope() {
	const ctx = getContext();
	if ( ! ctx.live ) {
		return null;
	}
	const { ref } = getElement();
	const rec = recordOf( ref );
	return rec ? { ctx, rec } : null;
}

/**
 * The record of a row action's carousel, when the action's element is the
 * carousel's own row.
 *
 * @return {Object|undefined} The record.
 */
function rowRecord() {
	const { ref } = getElement();
	const rec = recordOf( ref );
	return rec && ref === rec.row ? rec : undefined;
}

/**
 * Whether an event on a row began in that row's own carousel (not in a
 * carousel nested inside one of its slides).
 *
 * @param {Object} rec   The carousel's record.
 * @param {Event}  event The event.
 * @return {boolean} Whether it did.
 */
function ownsEvent( rec, event ) {
	const target = event.target;
	return Boolean(
		target && target.closest && target.closest( SHELL ) === rec.shell
	);
}

/**
 * The dots of a pagination row.
 *
 * @param {Element} row The pagination row.
 * @return {Element[]} Its buttons.
 */
function dotsOf( row ) {
	return Array.from( row.children ).filter(
		( el ) => 'BUTTON' === el.tagName
	);
}

/**
 * The index of the dot an event target is in, within a pagination row.
 *
 * @param {Element}  row    The pagination row.
 * @param {?Element} target The event's target.
 * @return {number} The index, or -1 when it is not one of the row's dots.
 */
function dotIndex( row, target ) {
	const dot =
		target && target.closest ? target.closest( '.' + DOT_CLASS ) : null;
	if ( ! dot || dot.parentElement !== row ) {
		return -1;
	}
	return dotsOf( row ).indexOf( dot );
}

/**
 * Keep a pagination row's dots true to the snaps.
 *
 * @param {Element}          row   The pagination row.
 * @param {Object|undefined} rec   The carousel's record, while live.
 * @param {number}           count The snap count.
 * @param {number}           snap  The current snap.
 */
function syncDots( row, rec, count, snap ) {
	let buttons = dotsOf( row );
	if ( ! rec ) {
		buttons.forEach( ( button ) => button.remove() );
		return;
	}
	if ( buttons.length !== count ) {
		buttons.forEach( ( button ) => button.remove() );
		buttons = [];
		for ( let k = 0; k < count; k++ ) {
			const button = row.ownerDocument.createElement( 'button' );
			button.setAttribute( 'type', 'button' );
			button.classList.add( DOT_CLASS );
			row.append( button );
			buttons.push( button );
		}
	}
	buttons.forEach( ( button, k ) => {
		const active = k === snap;
		button.setAttribute(
			'aria-label',
			format(
				rec.s.strings.goToSlide,
				k * rec.view.group + 1,
				rec.real.length
			)
		);
		button.classList.toggle( 'is-active', active );
		if ( active ) {
			button.setAttribute( 'aria-current', 'true' );
		} else {
			button.removeAttribute( 'aria-current' );
		}
		button.setAttribute( 'tabindex', active ? '0' : '-1' );
	} );
}

/**
 * The snap under a pointer on a scrollbar's track.
 *
 * @param {Object}  rec     The carousel's record.
 * @param {Element} track   The track.
 * @param {number}  clientX The pointer's position.
 * @return {number} The snap.
 */
function snapAtPointer( rec, track, clientX ) {
	const box = track.getBoundingClientRect();
	let fraction = box.width > 0 ? ( clientX - box.left ) / box.width : 0;
	fraction = clamp( fraction, 0, 1 );
	if ( rec.s.rtl ) {
		fraction = 1 - fraction;
	}
	return Math.round( fraction * ( rec.stops.length - 1 ) );
}

/**
 * Capture or release a pointer on an element, when it can.
 *
 * @param {Element}      el      The element.
 * @param {PointerEvent} event   The pointer event.
 * @param {boolean}      capture Whether to capture (else release).
 */
function pointerCapture( el, event, capture ) {
	const method = capture ? 'setPointerCapture' : 'releasePointerCapture';
	if ( 'function' !== typeof el[ method ] ) {
		return;
	}
	try {
		el[ method ]( event.pointerId );
	} catch {
		// The pointer is no longer active: nothing to capture or release.
	}
}

/**
 * Take a carousel over.
 *
 * @param {Element} shell The shell.
 * @param {Object}  ctx   The shell's context.
 * @return {(() => void)|undefined} The cleanup, when the carousel went live.
 */
function begin( shell, ctx ) {
	const row = childWithClass( shell, VIEWPORT_CLASS );
	const container = row ? childWithClass( row, CONTAINER_CLASS ) : null;
	if ( ! container ) {
		return undefined;
	}
	const kids = Array.from( container.children ).filter( ( el ) =>
		el.classList.contains( SLIDE_CLASS )
	);
	const isCopy = ( el ) => el.classList.contains( CLONE_CLASS );
	const real = kids.filter( ( el ) => ! isCopy( el ) );
	if ( ! real.length ) {
		return undefined;
	}
	const firstAt = kids.indexOf( real[ 0 ] );
	const lastAt = kids.indexOf( real[ real.length - 1 ] );
	const before = kids.slice( 0, firstAt ).filter( isCopy );
	const after = kids.slice( lastAt + 1 ).filter( isCopy );
	const copies = [];
	before.forEach( ( el, j ) => {
		const twin = real.length - before.length + j;
		if ( twin >= 0 ) {
			copies.push( { el, twin, after: false } );
		}
	} );
	after.forEach( ( el, j ) => {
		if ( j < real.length ) {
			copies.push( { el, twin: j, after: true } );
		}
	} );

	const rec = {
		shell,
		row,
		ctx,
		s: readSettings( row ),
		real,
		copies,
		view: { perView: 1, group: 1 },
		stops: [],
		move: null,
		auto: null,
		hover: false,
		focus: false,
		drag: null,
		observer: null,
		madeId: false,
		controls: owned( shell, CONTROLS ).length > 0,
		scrollbars: owned( shell, SCROLLBAR ),
	};
	rec.animate = withScope( ( time ) => animate( rec, time ) );
	rec.autoplay = withScope( ( time ) => autoplayFrame( rec, time ) );

	// The slide the visitor scrolled to before the store ran, if any.
	let noted = 0;
	if ( 0 !== row.scrollLeft ) {
		noted = nearest(
			rec,
			real.map( ( el, i ) => [ i, positionOf( rec, el ) ] )
		);
	}

	if ( ! row.id ) {
		row.id = makeId( row.ownerDocument );
		rec.madeId = true;
	}
	row.style.setProperty( '--blocklane-carousel-speed', rec.s.speed + 'ms' );
	decideView( rec );
	ctx.paused = rec.s.autoplay && prefersReducedMotion();
	rec.focus = row.contains( row.ownerDocument.activeElement );

	carousels.set( shell, rec );
	ctx.live = true;
	const count = measure( rec );
	ctx.snaps = count;
	goTo(
		rec,
		Math.min( Math.floor( noted / rec.view.group ), count - 1 ),
		true
	);

	if ( 'function' === typeof window.ResizeObserver ) {
		rec.observer = new window.ResizeObserver(
			withScope( () => resized( rec ) )
		);
		rec.observer.observe( row );
	}
	syncAutoplay( rec );
	return () => release( rec );
}

const { state } = store( 'blocklane/carousel', {
	state: {
		/**
		 * The row's tab index: out of the tab order while live when the
		 * shell has arrows, dots or a scrollbar.
		 *
		 * @return {?string} `'0'`, or null.
		 */
		get rowTabindex() {
			const live = liveScope();
			return live && live.rec.controls ? null : '0';
		},

		/**
		 * Whether a slide is out of view.
		 *
		 * @return {?boolean} True when hidden, else null.
		 */
		get slideHidden() {
			const live = liveScope();
			if ( ! live || 'number' !== typeof live.ctx.slide ) {
				return null;
			}
			// Read the count too, so a new measure evaluates this again.
			return live.ctx.snaps > 0 &&
				outOfView( live.rec, live.ctx.slide, live.ctx.snap )
				? true
				: null;
		},

		/**
		 * Whether the previous arrow is off.
		 *
		 * @return {?boolean} True at the first snap without loop, else null.
		 */
		get prevOff() {
			const live = liveScope();
			return live && isOff( live.rec, -1 ) ? true : null;
		},

		/**
		 * Whether the next arrow is off.
		 *
		 * @return {?boolean} True at the last snap without loop, else null.
		 */
		get nextOff() {
			const live = liveScope();
			return live && isOff( live.rec, 1 ) ? true : null;
		},

		/**
		 * The snap count, as text.
		 *
		 * @return {?string} The count, or null.
		 */
		get snapCount() {
			const live = liveScope();
			return live ? String( live.ctx.snaps ) : null;
		},

		/**
		 * The current snap's number, as text.
		 *
		 * @return {?string} The number, or null.
		 */
		get snapNumber() {
			const live = liveScope();
			return live ? String( live.ctx.snap + 1 ) : null;
		},

		/**
		 * The row's id.
		 *
		 * @return {?string} The id, or null.
		 */
		get rowId() {
			const live = liveScope();
			return live ? live.rec.row.id : null;
		},

		/**
		 * Whether there is one snap or none.
		 *
		 * @return {boolean} Whether there is.
		 */
		get oneSnap() {
			const live = liveScope();
			return live ? live.ctx.snaps <= 1 : false;
		},

		/**
		 * Whether the carousel has no autoplay.
		 *
		 * @return {boolean} Whether it has none.
		 */
		get noAutoplay() {
			const live = liveScope();
			return live ? ! live.rec.s.autoplay : false;
		},

		/**
		 * Whether the pause button is hidden.
		 *
		 * @return {boolean} Whether it is.
		 */
		get toggleHidden() {
			const live = liveScope();
			return live ? ! live.rec.s.autoplay : true;
		},

		/**
		 * The pause button's text.
		 *
		 * @return {string} Its play string while paused, else its pause one.
		 */
		get toggleText() {
			const ctx = getContext();
			return ctx.paused ? ctx.play : ctx.pause;
		},
	},

	actions: {
		/** Step one snap back. */
		prev() {
			const rec = recordOf( getElement().ref );
			if ( rec && ! isOff( rec, -1 ) ) {
				goTo( rec, rec.ctx.snap - 1 );
			}
		},

		/** Step one snap forward. */
		next() {
			const rec = recordOf( getElement().ref );
			if ( rec && ! isOff( rec, 1 ) ) {
				goTo( rec, rec.ctx.snap + 1 );
			}
		},

		/**
		 * Go to the snap of the dot clicked.
		 *
		 * @param {MouseEvent} event The click.
		 */
		dot: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const rec = recordOf( ref );
			const index = rec ? dotIndex( ref, event.target ) : -1;
			if ( index >= 0 ) {
				goTo( rec, index );
			}
		} ),

		/**
		 * Move between the dots with the arrow keys, Home and End.
		 *
		 * @param {KeyboardEvent} event The key.
		 */
		dotKey: withSyncEvent( ( event ) => {
			if ( event.altKey || event.ctrlKey || event.metaKey ) {
				return;
			}
			const { ref } = getElement();
			const rec = recordOf( ref );
			const index = rec ? dotIndex( ref, event.target ) : -1;
			if ( index < 0 ) {
				return;
			}
			const dots = dotsOf( ref );
			const last = dots.length - 1;
			const reversed = ref.classList.contains(
				'blocklane-carousel-controls--rtl'
			);
			const forward = reversed ? 'ArrowLeft' : 'ArrowRight';
			const back = reversed ? 'ArrowRight' : 'ArrowLeft';
			let next;
			if ( forward === event.key ) {
				next = index >= last ? 0 : index + 1;
			} else if ( back === event.key ) {
				next = index <= 0 ? last : index - 1;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = last;
			} else {
				return;
			}
			event.preventDefault();
			goTo( rec, next );
			dots[ next ].focus();
		} ),

		/** Follow the visitor's own scrolling. */
		scrolled: withSyncEvent( () => {
			const rec = rowRecord();
			if ( ! rec ) {
				return;
			}
			if ( ! rec.ctx.moving && ! rec.ctx.dragging && ! rec.s.fade ) {
				rec.ctx.snap = snapFromPosition( rec );
			}
			updateScrollbars( rec );
		} ),

		/** Settle when the visitor's scrolling ends. */
		settled: withSyncEvent( () => {
			const rec = rowRecord();
			if ( rec && ! rec.ctx.moving && ! rec.ctx.dragging ) {
				settle( rec );
			}
		} ),

		/**
		 * Begin a mouse drag.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		dragStart: withSyncEvent( ( event ) => {
			const rec = rowRecord();
			if (
				! rec ||
				rec.s.fade ||
				'mouse' !== event.pointerType ||
				0 !== event.button ||
				! ownsEvent( rec, event )
			) {
				return;
			}
			cancelMove( rec );
			rec.drag = { x: event.clientX, scroll: rec.row.scrollLeft };
			rec.ctx.dragging = true;
			syncMoving( rec );
			syncAutoplay( rec );
			pointerCapture( rec.row, event, true );
		} ),

		/**
		 * Follow a mouse drag.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		dragMove: withSyncEvent( ( event ) => {
			const rec = rowRecord();
			if ( ! rec || ! rec.drag || ! rec.ctx.dragging ) {
				return;
			}
			rec.row.scrollLeft =
				rec.drag.scroll - ( event.clientX - rec.drag.x );
		} ),

		/**
		 * End a mouse drag on the nearest stop.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		dragEnd: withSyncEvent( ( event ) => {
			const rec = rowRecord();
			if ( ! rec || ! rec.drag ) {
				return;
			}
			rec.drag = null;
			rec.ctx.dragging = false;
			pointerCapture( rec.row, event, false );
			goTo( rec, nearestStop( rec ) );
			syncAutoplay( rec );
		} ),

		/**
		 * Step with a sideways wheel, when wheel navigation is on.
		 *
		 * @param {WheelEvent} event The wheel.
		 */
		wheel: withSyncEvent( ( event ) => {
			const rec = rowRecord();
			if (
				! rec ||
				! rec.s.wheel ||
				! ownsEvent( rec, event ) ||
				Math.abs( event.deltaX ) <= Math.abs( event.deltaY )
			) {
				return;
			}
			event.preventDefault();
			if ( rec.move ) {
				return;
			}
			let direction = event.deltaX > 0 ? 1 : -1;
			if ( rec.s.rtl ) {
				direction = -direction;
			}
			const next = rec.ctx.snap + direction;
			if ( ! rec.s.loop && ( next < 0 || next > rec.stops.length - 1 ) ) {
				return;
			}
			goTo( rec, next );
		} ),

		/** The pointer is over the row. */
		hover() {
			const rec = rowRecord();
			if ( rec ) {
				rec.hover = true;
				syncAutoplay( rec );
			}
		},

		/** The pointer left the row. */
		unhover() {
			const rec = rowRecord();
			if ( rec ) {
				rec.hover = false;
				syncAutoplay( rec );
			}
		},

		/** Focus came into the row. */
		focusIn() {
			const rec = rowRecord();
			if ( rec ) {
				rec.focus = true;
				syncAutoplay( rec );
			}
		},

		/**
		 * Focus left the row, unless it moved within it.
		 *
		 * @param {FocusEvent} event The focus change.
		 */
		focusOut: withSyncEvent( ( event ) => {
			const rec = rowRecord();
			if ( ! rec ) {
				return;
			}
			const to = event.relatedTarget;
			if ( ! to || ! rec.row.contains( to ) ) {
				rec.focus = false;
				syncAutoplay( rec );
			}
		} ),

		/**
		 * Begin scrubbing on the scrollbar's track.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		scrubStart: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const rec = recordOf( ref );
			if ( ! rec ) {
				return;
			}
			rec.ctx.scrubbing = true;
			syncAutoplay( rec );
			pointerCapture( ref, event, true );
			goTo( rec, snapAtPointer( rec, ref, event.clientX ) );
		} ),

		/**
		 * Follow a scrub.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		scrubMove: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const rec = recordOf( ref );
			if ( ! rec || ! rec.ctx.scrubbing ) {
				return;
			}
			const k = snapAtPointer( rec, ref, event.clientX );
			if ( k !== rec.ctx.snap ) {
				goTo( rec, k );
			}
		} ),

		/**
		 * End a scrub.
		 *
		 * @param {PointerEvent} event The pointer.
		 */
		scrubEnd: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const rec = recordOf( ref );
			if ( ! rec || ! rec.ctx.scrubbing ) {
				return;
			}
			rec.ctx.scrubbing = false;
			pointerCapture( ref, event, false );
			syncAutoplay( rec );
		} ),

		/**
		 * Step the scrollbar's thumb with the keyboard.
		 *
		 * @param {KeyboardEvent} event The key.
		 */
		thumbKey: withSyncEvent( ( event ) => {
			const rec = recordOf( getElement().ref );
			if ( ! rec ) {
				return;
			}
			const last = rec.stops.length - 1;
			const snap = rec.ctx.snap;
			let next;
			if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				next = snap - 1;
			} else if (
				'ArrowRight' === event.key ||
				'ArrowDown' === event.key
			) {
				next = snap + 1;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = last;
			} else {
				return;
			}
			event.preventDefault();
			if ( ! rec.s.loop ) {
				next = clamp( next, 0, last );
			}
			if ( next !== snap ) {
				goTo( rec, next );
			}
		} ),

		/** Pause or play autoplay. */
		toggleAutoplay() {
			const rec = recordOf( getElement().ref );
			if ( ! rec ) {
				return;
			}
			rec.ctx.paused = ! rec.ctx.paused;
			syncAutoplay( rec );
		},
	},

	callbacks: {
		/**
		 * Take the carousel over, once the page is hydrated.
		 *
		 * @return {(() => void)|undefined} The cleanup, when it went live.
		 */
		start() {
			const { ref } = getElement();
			return ref ? begin( ref, getContext() ) : undefined;
		},

		/** Keep a pagination row's dots true to the snaps. */
		dots() {
			const ctx = getContext();
			const live = Boolean( ctx.live );
			const count = ctx.snaps;
			const snap = ctx.snap;
			const { ref } = getElement();
			const rec = live ? recordOf( ref ) : undefined;
			if ( ref ) {
				syncDots( ref, rec, count, snap );
			}
		},

		/** Keep a scrollbar's thumb true to the current snap and count. */
		thumb() {
			const ctx = getContext();
			const live = Boolean( ctx.live );
			const snap = ctx.snap;
			const snaps = ctx.snaps;
			const { ref } = getElement();
			const rec = live ? recordOf( ref ) : undefined;
			if ( rec && ref ) {
				writeThumb( rec, ref, snap, snaps );
			}
		},

		/** Keep a slide out of view from taking focus. */
		slideInert() {
			const hidden = true === state.slideHidden;
			const { ref } = getElement();
			if ( ref ) {
				ref.inert = hidden;
			}
		},
	},
} );
