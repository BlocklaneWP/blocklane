/**
 * Forms view module — the submission runtime (Interactivity API).
 *
 * Validation is the browser's own constraint engine (element.validity /
 * validationMessage) rendered into accessible inline messages — no vendor
 * validator. The time-trap arms 3s after load (cache-proof, clock-free:
 * an empty token server-side means bot). Server field errors render through
 * the same inline path as client ones.
 */
import { store, getElement } from '@wordpress/interactivity';

const DEFAULT_ARM_DELAY_MS = 3000;

const isCandidate = ( el ) =>
	el &&
	[ 'INPUT', 'SELECT', 'TEXTAREA' ].includes( el.tagName ) &&
	el.type !== 'hidden' &&
	! el.closest( '.blocklane-form__hp' );

const controlsOf = ( form ) =>
	Array.from( form.elements ).filter( isCandidate );

const wrapperOf = ( control ) =>
	control.closest( '.blocklane-form__field' ) || control.parentElement;

const errorIdOf = ( control ) =>
	( control.id || control.name.replace( /\[\]$/, '' ) ) + '-error';

/*
 * Conditional visibility (v3). The wrapper carries its rule (data-bl-cond +
 * data-bl-name, emitted by the one render base); this engine mirrors the
 * server resolver EXACTLY: a hidden field's effective value is '', passes
 * only ever ADD to the hidden set until stable (monotone — chains resolve
 * deterministically, cycles cannot oscillate), and hidden fields disable
 * their inputs so they neither validate nor submit — the server discards
 * their values regardless.
 */
// The three primitives below are MIRRORED from runtime.php
// (blocklane_pro_forms_cond_norm / _fold / _is_num). They must stay identical:
// when the two evaluators disagree about whether a field is active, the server
// discards what the visitor typed into it and still answers success.

// Collapse every run of whitespace to one space, then trim.
//
// ONE explicit class, spelled out identically in runtime.php
// (blocklane_pro_forms_cond_norm). Neither language's \s defines the set,
// because they disagree and both drift: PHP's /u enables PCRE2_UCP so \s
// there is the full Unicode White_Space set (it adds U+0085, and U+180E on
// current PCRE2), while JS \s omits those two and adds U+FEFF. Letting \s
// stand on either side is what split the two evaluators twice already.
// Written out, the mirror is a literal string you can compare by eye.
//
// U+200B is deliberately absent from BOTH: it is not whitespace in either
// language, so leaving it alone is itself an agreement.
const condNorm = ( value ) =>
	String( value ?? '' )
		.replace(
			/[\t\n\v\f\r \u0085\u00A0\u1680\u180E\u2000-\u200A\u2028\u2029\u202F\u205F\u3000\uFEFF]+/g,
			' '
		)
		.trim();

// Unicode-aware, matching mb_strtolower on the server. Byte-wise folding there
// meant "CAFÉ" matched "café" here and never there.
const condFold = ( value ) =>
	// Final sigma folded to plain sigma, mirroring runtime.php. JS toLowerCase
	// already does this; PHP's mb_strtolower only learned it in 8.3, so the map
	// is spelled out on both sides rather than left to either runtime.
	condNorm( value )
		.toLowerCase()
		.replace( /\u03C2/g, '\u03C3' );

// The same explicit decimal pattern the server uses. parseFloat accepted a
// numeric PREFIX ("12 units" -> 12) that is_numeric rejected, and a checkbox
// group joins to "7, 3" — which both sides now agree is not a number.
const NUMERIC = /^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/;
const condIsNum = ( value ) => '' !== value && NUMERIC.test( value );

const evaluateCondition = ( cond, refValue ) => {
	const raw = Array.isArray( refValue ) ? refValue : [ refValue ];
	// An empty array and an absent value must read the same — see the matching
	// note in runtime.php. A group with nothing ticked arrives as [] here and
	// as '' there, which made "is ''" disagree; an empty target is the shipped
	// default for a new rule, so that was the most reachable divergence.
	const values = ( raw.length ? raw : [ '' ] ).map( condNorm );

	const joined = () => condNorm( values.join( ', ' ) );
	const target = () => condNorm( cond.value );
	const foldedValues = () => values.map( condFold );

	switch ( cond.operator ) {
		case 'is':
			return foldedValues().includes( condFold( target() ) );
		case 'is_not':
			return ! foldedValues().includes( condFold( target() ) );
		case 'contains':
			return (
				'' !== target() &&
				condFold( joined() ).includes( condFold( target() ) )
			);
		case 'empty':
			return '' === joined();
		case 'not_empty':
			return '' !== joined();
		case 'gt':
		case 'lt': {
			if ( ! condIsNum( joined() ) || ! condIsNum( target() ) ) {
				return false;
			}
			const a = parseFloat( joined() );
			const b = parseFloat( target() );
			return 'gt' === cond.operator ? a > b : a < b;
		}
	}
	return true;
};

const liveFieldValue = ( form, name ) => {
	const controls = Array.from( form.elements ).filter(
		( el ) => el.name === name || el.name === `${ name }[]`
	);
	if ( ! controls.length ) {
		return '';
	}
	const checkable = controls.filter( ( el ) =>
		[ 'radio', 'checkbox' ].includes( el.type )
	);
	if ( checkable.length ) {
		return checkable
			.filter( ( el ) => el.checked )
			.map( ( el ) => el.value );
	}
	return controls[ 0 ].value || '';
};

const applyConditions = ( form ) => {
	const targets = Array.from( form.querySelectorAll( '[data-bl-cond]' ) );
	if ( ! targets.length ) {
		return;
	}
	const rules = targets
		.map( ( wrapper ) => {
			try {
				return {
					wrapper,
					name: wrapper.dataset.blName || '',
					cond: JSON.parse( wrapper.dataset.blCond ),
				};
			} catch ( e ) {
				return null;
			}
		} )
		.filter( Boolean );

	const hidden = new Set();
	for ( let pass = 0; pass <= rules.length; pass++ ) {
		let changed = false;
		rules.forEach( ( { name, cond } ) => {
			if ( hidden.has( name ) ) {
				return;
			}
			const refHidden = hidden.has( cond.field );
			const refValue = refHidden
				? ''
				: liveFieldValue( form, cond.field );
			if ( ! evaluateCondition( cond, refValue ) ) {
				hidden.add( name );
				changed = true;
			}
		} );
		if ( ! changed ) {
			break;
		}
	}

	rules.forEach( ( { wrapper, name } ) => {
		// A field the server rejected stays visible until its error clears —
		// otherwise the next keystroke hides the message the visitor is
		// reading and the form dead-ends again.
		const hide = wrapper.dataset.blServerActive
			? false
			: hidden.has( name );
		wrapper.classList.toggle( 'is-bl-hidden', hide );
		wrapper.hidden = hide;
		// A hidden input carries its own rule and has no wrapper, so it IS the
		// element here — querySelectorAll would not match it and it would keep
		// submitting a value the server had already decided to discard.
		if ( wrapper.matches( 'input, select, textarea' ) ) {
			wrapper.disabled = hide;
		}
		wrapper
			.querySelectorAll( 'input, select, textarea' )
			.forEach( ( control ) => {
				control.disabled = hide;
			} );
	} );
};

const setNotification = ( form, type, visible ) => {
	const notification = form.querySelector(
		`[data-bl-notification="${ type }"]`
	);
	if ( notification ) {
		notification.classList.toggle( 'is-visible', visible );
		// The markup ships `hidden` (#743): the attribute, not the
		// stylesheet, is what keeps a message off the page until now.
		notification.hidden = ! visible;
	}
	return notification;
};

const clearFeedback = ( form ) => {
	form.querySelectorAll( '.blocklane-form__error' ).forEach( ( node ) =>
		node.remove()
	);
	// The errors are gone, so the server's override goes with them and the
	// rules resume deciding. Without this the field stays pinned open forever.
	form.querySelectorAll( '[data-bl-server-active]' ).forEach( ( wrapper ) => {
		delete wrapper.dataset.blServerActive;
	} );
	form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( control ) => {
		control.removeAttribute( 'aria-invalid' );
		const described = ( control.getAttribute( 'aria-describedby' ) || '' )
			.split( /\s+/ )
			.filter( ( id ) => id && id !== errorIdOf( control ) )
			.join( ' ' );
		if ( described ) {
			control.setAttribute( 'aria-describedby', described );
		} else {
			control.removeAttribute( 'aria-describedby' );
		}
	} );
	setNotification( form, 'success', false );
	setNotification( form, 'error', false );
};

const showFieldError = ( form, control, message ) => {
	const wrapper = wrapperOf( control );
	const errorId = errorIdOf( control );
	if ( ! wrapper || wrapper.querySelector( `[id="${ errorId }"]` ) ) {
		return;
	}
	// If this field is condition-hidden, the client's verdict is stale: the
	// server has just told us the field is active AND invalid, and the server
	// is the one that decides what a submission may contain. Appending the
	// error into a display:none wrapper produced a dead end — the form failed,
	// nothing was highlighted, and the control could not be reached or fixed.
	// Reveal it and re-enable it so the message is visible and answerable.
	if ( wrapper.classList.contains( 'is-bl-hidden' ) || wrapper.hidden ) {
		// Marked, not just revealed: applyConditions runs on the very next
		// keystroke and re-hides from scratch, which would have swallowed the
		// error again a moment after showing it. The mark says "the server has
		// ruled on this field" and applyConditions honours it until the error
		// is cleared.
		wrapper.dataset.blServerActive = '1';
		wrapper.classList.remove( 'is-bl-hidden' );
		wrapper.hidden = false;
		if ( wrapper.matches( 'input, select, textarea' ) ) {
			wrapper.disabled = false;
		}
		wrapper
			.querySelectorAll( 'input, select, textarea' )
			.forEach( ( el ) => {
				el.disabled = false;
			} );
	}

	const error = form.ownerDocument.createElement( 'p' );
	error.className = 'blocklane-form__error';
	error.id = errorId;
	error.textContent = message;
	wrapper.append( error );

	control.setAttribute( 'aria-invalid', 'true' );
	const described = ( control.getAttribute( 'aria-describedby' ) || '' )
		.split( /\s+/ )
		.filter( Boolean );
	if ( ! described.includes( errorId ) ) {
		described.push( errorId );
	}
	control.setAttribute( 'aria-describedby', described.join( ' ' ) );
};

/*
 * Multi-step (v3). Steps are UX only — every step's inputs stay ENABLED
 * (they all submit; the server validates the full schema regardless);
 * only conditional hiding disables controls. Next validates the current
 * step's visible controls through the same native-validity inline
 * presentation as submit; a step whose every candidate control is
 * condition-hidden auto-skips. The author-placed submit button shows
 * only on the last effective step.
 */
const stepState = new WeakMap();

const stepsOf = ( form ) =>
	Array.from( form.querySelectorAll( '.blocklane-form__step' ) );

// A step is reachable unless it HAS candidate controls and every one of them
// is condition-hidden. The old test was "has at least one visible candidate",
// which quietly skipped two legitimate step shapes: a content-only step (a
// wizard intro, an instructions page, a review screen) and a step holding
// only hidden inputs. Those have no candidates at all, so they were dropped
// from navigation entirely and the visitor never saw them.
const stepIsReachable = ( step ) => {
	// A step holding a REVEALED notification is reachable while it is
	// revealed (#747): the message the visitor was just shown must not be
	// snapped away because the step's fields are hidden or empty.
	// clearFeedback() re-hides notifications, so this reverts on the next
	// Next/Back/submit.
	if ( step.querySelector( '[data-bl-notification]:not([hidden])' ) ) {
		return true;
	}
	const controls = Array.from(
		step.querySelectorAll( 'input, select, textarea' )
	).filter( isCandidate );

	return ! controls.length || controls.some( ( el ) => ! el.disabled );
};

const effectiveStepIndexes = ( form ) => {
	const steps = stepsOf( form );
	const indexes = steps
		.map( ( step, index ) => ( { step, index } ) )
		.filter( ( { step } ) => stepIsReachable( step ) )
		.map( ( { index } ) => index );
	// A form whose every step is condition-hidden still needs one visible
	// step (the closing one carries the nav/submit context).
	return indexes.length ? indexes : steps.map( ( _, index ) => index );
};

const applyStepView = ( form ) => {
	if ( ! form.dataset.blStepped ) {
		return;
	}
	const steps = stepsOf( form );
	if ( steps.length < 2 ) {
		return;
	}
	const effective = effectiveStepIndexes( form );
	let current = stepState.get( form ) ?? effective[ 0 ];
	// Conditions may have hidden the remembered step — snap to the nearest
	// effective one after it (or the last).
	if ( ! effective.includes( current ) ) {
		current =
			effective.find( ( index ) => index > current ) ??
			effective[ effective.length - 1 ];
	}
	stepState.set( form, current );

	const position = effective.indexOf( current );
	const isFirst = 0 === position;
	const isLast = position === effective.length - 1;

	steps.forEach( ( step, index ) => {
		const active = index === current;
		step.hidden = ! active;
		const back = step.querySelector( '[data-bl-step-back]' );
		const next = step.querySelector( '[data-bl-step-next]' );
		if ( back ) {
			back.hidden = ! active || isFirst;
		}
		if ( next ) {
			next.hidden = ! active || isLast;
		}
	} );

	// The author-placed submit button belongs to the closing step — and to
	// the wizard nav convention (TurboTax/Typeform): Back bottom-left,
	// primary action bottom-right, Submit taking EXACTLY the slot Next
	// occupied. A submit sitting directly in the form (the canonical
	// after-the-steps placement) is relocated into the last EFFECTIVE
	// step's nav row — dynamic, because conditional auto-skip can change
	// which step closes the flow. One the author placed inside a step is
	// respected where it is. Hide the BUTTON ITSELF — never an ancestor:
	// wrapperOf's parentElement fallback resolves to the <form> for a
	// direct child, and hiding that hid the ENTIRE form (the v3 bug
	// Garrett caught; the original "verification" probed
	// closest('[hidden]') and misread the hidden form as a hidden submit).
	const lastStep = steps[ effective[ effective.length - 1 ] ];
	const lastNav = lastStep
		? lastStep.querySelector( '.blocklane-form__step-nav' )
		: null;
	form.querySelectorAll( '.blocklane-form__submit' ).forEach( ( submit ) => {
		const owningStep = submit.closest( '.blocklane-form__step' );
		const wasRelocated = submit.parentElement?.classList.contains(
			'blocklane-form__step-nav'
		);
		// Move when it sits directly in the form (canonical after-the-steps
		// placement), inside the CLOSING step's content (the nav row is its
		// natural slot there — authors can't reach the runtime-rendered nav
		// themselves), or when WE moved it before and the closing step
		// changed (conditions re-shaped the flow). Only a submit an author
		// parked inside a NON-last step stays where it is.
		if (
			lastNav &&
			submit.parentElement !== lastNav &&
			( ! owningStep || owningStep === lastStep || wasRelocated )
		) {
			lastNav.append( submit );
		}
		submit.hidden = ! isLast;
	} );

	const progress = form.querySelector( '[data-bl-progress]' );
	if ( progress ) {
		Array.from( progress.children ).forEach( ( item, index ) => {
			item.classList.toggle( 'is-current', index === current );
			item.classList.toggle( 'is-done', index < current );
		} );
	}
};

const validateStep = ( form, step ) => {
	let firstInvalid = null;
	Array.from( step.querySelectorAll( 'input, select, textarea' ) )
		.filter( ( el ) => isCandidate( el ) && ! el.disabled )
		.forEach( ( control ) => {
			if ( ! control.checkValidity() ) {
				showFieldError( form, control, control.validationMessage );
				firstInvalid = firstInvalid || control;
			}
		} );
	if ( firstInvalid ) {
		firstInvalid.focus();
		return false;
	}
	return true;
};

/**
 * The next reachable step after `current` (or the last reachable one), read
 * from the CURRENT list — `current` itself need not be in it.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {number}          current The step index the visitor is on.
 * @return {number} The target step index.
 */
const stepAfter = ( form, current ) => {
	const effective = effectiveStepIndexes( form );
	return (
		effective.find( ( index ) => index > current ) ??
		effective[ effective.length - 1 ]
	);
};

/**
 * The previous reachable step before `current` (or the first reachable one),
 * read from the CURRENT list — `current` itself need not be in it.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {number}          current The step index the visitor is on.
 * @return {number} The target step index.
 */
const stepBefore = ( form, current ) => {
	const effective = effectiveStepIndexes( form );
	const earlier = effective.filter( ( index ) => index < current );
	return earlier.length ? earlier[ earlier.length - 1 ] : effective[ 0 ];
};

/**
 * Announce "Step N of M" politely. One live region per form, created on
 * demand and reused, so repeated navigation does not stack regions.
 *
 * @param {HTMLFormElement} form     The form element.
 * @param {number}          position 1-based position among effective steps.
 * @param {number}          total    Effective step count.
 */
const announceStep = ( form, position, total ) => {
	if ( position < 1 || total < 1 ) {
		return;
	}
	let region = form.querySelector( '[data-bl-step-status]' );
	if ( ! region ) {
		region = document.createElement( 'p' );
		region.setAttribute( 'data-bl-step-status', '' );
		region.setAttribute( 'role', 'status' );
		region.setAttribute( 'aria-live', 'polite' );
		region.className = 'blocklane-form__sr-only';
		form.appendChild( region );
	}
	// The template comes from the server so it can be translated; falling
	// back keeps navigation working if the attribute is ever absent.
	const template = form.dataset.blStepStatus || 'Step %1$s of %2$s';
	// split/join, not replace(): a translator who repeats a placeholder would
	// otherwise leave the second one showing as literal "%1$s" to a screen
	// reader, and replace() only ever substitutes the first occurrence.
	region.textContent = template
		.split( '%1$s' )
		.join( String( position ) )
		.split( '%2$s' )
		.join( String( total ) );
};

/**
 * Move focus into the newly active step and say where we are.
 *
 * The button the visitor just pressed lives on the step being hidden, so
 * without this focus falls to <body>: keyboard users restart from the top of
 * the document on every step, and a screen reader announces nothing at all —
 * the page silently became a different page.
 *
 * @param {HTMLFormElement} form The form element.
 */
const focusStep = ( form ) => {
	const steps = stepsOf( form );
	const effective = effectiveStepIndexes( form );
	const current = stepState.get( form ) ?? effective[ 0 ];
	const step = steps[ current ];
	if ( ! step ) {
		return;
	}

	const control = Array.from(
		step.querySelectorAll( 'input, select, textarea' )
	).find( ( el ) => isCandidate( el ) && ! el.disabled );

	if ( control ) {
		control.focus();
	} else {
		// A content-only step has nothing to focus, so focus the step itself.
		step.setAttribute( 'tabindex', '-1' );
		step.focus();
	}

	announceStep( form, effective.indexOf( current ) + 1, effective.length );
};

const initStepping = ( form ) => {
	if ( ! form.dataset.blStepped ) {
		return;
	}
	applyStepView( form );
	form.addEventListener( 'click', ( event ) => {
		const next = event.target.closest( '[data-bl-step-next]' );
		const back = event.target.closest( '[data-bl-step-back]' );
		if ( ! next && ! back ) {
			return;
		}
		event.preventDefault();
		// The step the visitor is ON is read before feedback clears; the
		// TARGET is read after it. clearFeedback() re-hides notifications,
		// and a revealed one keeps its step reachable — so the current step
		// itself may leave the list (a message-pinned step), and the target
		// is "the next reachable step after it", never an index into a list
		// the current step is no longer in.
		const current =
			stepState.get( form ) ?? effectiveStepIndexes( form )[ 0 ];
		clearFeedback( form );
		if ( next ) {
			const step = stepsOf( form )[ current ];
			if ( step && ! validateStep( form, step ) ) {
				return;
			}
			stepState.set( form, stepAfter( form, current ) );
		} else {
			stepState.set( form, stepBefore( form, current ) );
		}
		applyStepView( form );
		form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		focusStep( form );
	} );
};

// Submit-time correction for stepped forms: an invalid control living on a
// non-active step (a condition changed after its step was passed) must be
// SHOWN before its error focuses.
const activateStepOf = ( form, control ) => {
	if ( ! form.dataset.blStepped ) {
		return;
	}
	const step = control.closest( '.blocklane-form__step' );
	if ( ! step ) {
		return;
	}
	const index = stepsOf( form ).indexOf( step );
	if ( index >= 0 && stepState.get( form ) !== index ) {
		stepState.set( form, index );
		applyStepView( form );
	}
};

const armTimeTrap = ( form ) => {
	const token = form.querySelector( 'input[name="_bl_time"]' );
	if ( token ) {
		// Delay is server-filterable (render.php emits data-bl-arm-delay).
		const delay =
			parseInt( form.dataset.blArmDelay, 10 ) || DEFAULT_ARM_DELAY_MS;
		setTimeout( () => {
			token.value = '1';
		}, delay );
	}
};

const controlForName = ( form, name ) => {
	const direct = form.elements[ name ] || form.elements[ name + '[]' ];
	if ( ! direct ) {
		return null;
	}
	return direct instanceof Element ? direct : direct[ 0 ] || null;
};

// Replace-form-with-message (v2): hide every branch of the form that does
// not contain the success notification, so the revealed message alone
// remains — walking the containment chain handles a notification nested in
// wrapper blocks. Inline display:none (not just [hidden]) because a layout
// class's own display would beat the attribute's UA rule. Focus then lands
// on the role=status container, which stays OUTSIDE the hidden region.
const replaceWithMessage = ( form, success ) => {
	// The success node's own step must stay visible: the walk below hides
	// the step's OTHER children and every sibling branch, but never un-hides
	// an ancestor the step machinery hid (#747).
	const step = success.closest( '.blocklane-form__step' );
	if ( step ) {
		step.hidden = false;
	}
	let node = success;
	while ( node && node !== form && node.parentElement ) {
		Array.from( node.parentElement.children ).forEach( ( sibling ) => {
			if ( sibling !== node ) {
				sibling.hidden = true;
				sibling.style.display = 'none';
			}
		} );
		node = node.parentElement;
	}
	form.classList.add( 'is-replaced' );
};

// Turnstile tokens are single-use and expire in 300s: after ANY submit
// attempt the consumed token is dead, so re-run every widget in the form.
// No-ops when the feature (and so the api.js script) is off the page.
const resetTurnstile = ( form ) => {
	if ( ! window.turnstile ) {
		return;
	}
	form.querySelectorAll( '.cf-turnstile' ).forEach( ( widget ) => {
		try {
			window.turnstile.reset( widget );
		} catch ( error ) {
			// An unrendered widget throws — nothing to reset.
		}
	} );
};

const setBusy = ( form, busy ) => {
	form.classList.toggle( 'is-submitting', busy );
	const button = form.querySelector( '.blocklane-form__submit' );
	if ( ! button ) {
		return;
	}
	if ( busy ) {
		button.dataset.blLabel = button.textContent;
		button.textContent = button.dataset.busyText || button.textContent;
		button.disabled = true;
	} else {
		if ( button.dataset.blLabel ) {
			button.textContent = button.dataset.blLabel;
			delete button.dataset.blLabel;
		}
		button.disabled = false;
	}
};

// The authored error-notification nodes, cloned once so a superseding server
// message can be reverted on the next failure — no innerHTML round-trips.
const authoredNodes = new WeakMap();

const rememberAuthored = ( form ) => {
	const notification = form.querySelector( '[data-bl-notification="error"]' );
	if ( notification && ! authoredNodes.has( notification ) ) {
		authoredNodes.set(
			notification,
			Array.from( notification.childNodes ).map( ( node ) =>
				node.cloneNode( true )
			)
		);
	}
};

const revealErrors = ( form, firstInvalid, message ) => {
	const notification = setNotification( form, 'error', true );
	// A server-supplied message (e.g. the honest 429 rate-limit reply, which
	// carries no per-field errors) supersedes the authored error copy —
	// otherwise the visitor sees "check the highlighted fields" with nothing
	// highlighted. Field-level failures keep the authored content.
	if ( notification ) {
		if ( message ) {
			notification.replaceChildren(
				form.ownerDocument.createTextNode( message )
			);
		} else if ( authoredNodes.has( notification ) ) {
			notification.replaceChildren(
				...authoredNodes
					.get( notification )
					.map( ( node ) => node.cloneNode( true ) )
			);
		}
	}
	if ( firstInvalid ) {
		// Stepped forms: the invalid control may live on a non-active step
		// (a condition changed after its step was passed) — reveal it first.
		activateStepOf( form, firstInvalid );
		firstInvalid.focus();
		// The step now shown is the view (the field's step when the field
		// has one; the current step when the field sits beside the steps).
		// An error message living on ANY OTHER step goes back to hidden: a
		// revealed notification pins its step (stepIsReachable), so one left
		// revealed off-view would pull a condition-hidden step back into the
		// wizard — Submit turning into Next under the visitor's hands (#747).
		// Non-stepped forms keep their one step wrapper visible: nothing to
		// re-hide there.
		const stepOfError = notification?.closest( '.blocklane-form__step' );
		if ( form.dataset.blStepped && stepOfError ) {
			const shown =
				stepsOf( form )[
					stepState.get( form ) ?? effectiveStepIndexes( form )[ 0 ]
				];
			if ( stepOfError !== shown ) {
				setNotification( form, 'error', false );
				// The reveal made that step reachable and activateStepOf()
				// already laid the wizard out with it in the list; hiding it
				// again changes the list, so the view is re-applied now —
				// not on the visitor's next keystroke.
				applyStepView( form );
			}
		}
	} else if ( notification ) {
		// No field claims the view (a 429, an expired nonce, a network
		// failure): the message's own step is shown first, then the message
		// takes focus — in that order, because focus() on a node inside a
		// hidden step is a no-op (#747).
		activateStepOf( form, notification );
		notification.focus();
	}
};

store( 'blocklane-pro/forms', {
	callbacks: {
		init() {
			const form = getElement().ref;
			armTimeTrap( form );
			rememberAuthored( form );
			// Conditional visibility: resolve once at load, then live on
			// every input/change (recomputed from scratch each time, so
			// fields un-hide as naturally as they hide). Stepping re-applies
			// after conditions — hidden fields change which steps exist.
			applyConditions( form );
			initStepping( form );
			form.addEventListener( 'input', () => {
				applyConditions( form );
				applyStepView( form );
			} );
			form.addEventListener( 'change', () => {
				applyConditions( form );
				applyStepView( form );
			} );
		},
	},
	actions: {
		async submit( event ) {
			event.preventDefault();
			const form = getElement().ref;
			if ( form.classList.contains( 'is-submitting' ) ) {
				return;
			}

			// Enter inside any text input fires the form's submit event from
			// ANY step — the author's submit button is merely hidden on
			// earlier ones, not removed, so there was nothing to stop it. On a
			// stepped form, treat that as pressing Next: validate this step
			// and advance. Without it, Enter on step 1 either submitted a
			// half-filled form or failed validation against a control the
			// visitor could not see.
			if ( form.dataset.blStepped ) {
				// "Not on the last step" is decided BEFORE feedback clears
				// (a message-pinned step counts as a step the visitor is on);
				// the target is the next reachable step after it, read after.
				const before = effectiveStepIndexes( form );
				const current = stepState.get( form ) ?? before[ 0 ];
				const position = before.indexOf( current );
				if ( position > -1 && position < before.length - 1 ) {
					clearFeedback( form );
					const step = stepsOf( form )[ current ];
					if ( step && ! validateStep( form, step ) ) {
						return;
					}
					stepState.set( form, stepAfter( form, current ) );
					applyStepView( form );
					focusStep( form );
					return;
				}
			}

			clearFeedback( form );

			// Native constraint validation, our inline presentation. One
			// message per field name (radio groups share one). File inputs
			// add size/count checks the constraint API doesn't cover — the
			// messages are server-rendered data attributes, so client and
			// server rejections read identically.
			const seen = new Set();
			let firstInvalid = null;
			controlsOf( form ).forEach( ( control ) => {
				const name = control.name.replace( /\[\]$/, '' );
				if ( seen.has( name ) ) {
					return;
				}
				if ( ! control.checkValidity() ) {
					seen.add( name );
					showFieldError( form, control, control.validationMessage );
					if ( ! firstInvalid ) {
						firstInvalid = control;
					}
					return;
				}
				if ( 'file' === control.type && control.files.length ) {
					const maxFiles =
						parseInt( control.dataset.maxFiles, 10 ) || 1;
					const maxSize =
						parseInt( control.dataset.maxSize, 10 ) || 0;
					let message = '';
					if ( control.files.length > maxFiles ) {
						message = control.dataset.blErrorCount;
					} else if (
						Array.from( control.files ).some(
							( file ) => file.size <= 0
						)
					) {
						message = control.dataset.blErrorEmpty;
					} else if (
						maxSize &&
						Array.from( control.files ).some(
							( file ) => file.size > maxSize
						)
					) {
						message = control.dataset.blErrorSize;
					}
					if ( message ) {
						seen.add( name );
						showFieldError( form, control, message );
						if ( ! firstInvalid ) {
							firstInvalid = control;
						}
					}
				}
			} );
			if ( firstInvalid ) {
				revealErrors( form, firstInvalid );
				return;
			}

			setBusy( form, true );
			let payload = null;
			try {
				// Urlencoded for file-LESS submissions (the v1-proven path
				// every server parses) — multipart only when a file is
				// actually attached, so merely ADDING an optional file field
				// never narrows the transport. A present-but-empty file
				// input posts an empty File placeholder that URLSearchParams
				// would coerce to "[object File]", so string entries are
				// copied explicitly.
				const data = new FormData( form );
				// Lead provenance (v2): the page the visitor is actually on —
				// the live URL carries the UTM/campaign signal a (possibly
				// cache-stale) post id can't — plus how they arrived. The
				// server treats both as sanitized display metadata only.
				data.append( '_bl_url', window.location.href );
				data.append( '_bl_referrer', document.referrer );
				const hasFiles = Array.from(
					form.querySelectorAll( 'input[type="file"]' )
				).some( ( input ) => input.files.length );
				let body = data;
				if ( ! hasFiles ) {
					body = new URLSearchParams();
					data.forEach( ( value, key ) => {
						if ( 'string' === typeof value ) {
							body.append( key, value );
						}
					} );
				}
				// Login-gated forms render a wp_rest nonce (_bl_rest): riding
				// it as the header lets REST cookie auth identify the user
				// for the server's login re-check. Absent on public forms —
				// deliberately, so page caches can't serve stale nonces.
				const restNonce = form.querySelector(
					'input[name="_bl_rest"]'
				)?.value;
				const response = await fetch( form.dataset.blEndpoint, {
					method: 'POST',
					body,
					...( restNonce
						? { headers: { 'X-WP-Nonce': restNonce } }
						: {} ),
				} );
				payload = await response.json();
			} catch ( error ) {
				payload = null;
			}
			setBusy( form, false );
			resetTurnstile( form );

			if ( payload && payload.success ) {
				if ( 'redirect' === payload.action && payload.redirectUrl ) {
					window.location.assign( payload.redirectUrl );
					return;
				}
				form.reset();
				const success = setNotification( form, 'success', true );
				if ( payload.replace && success ) {
					// The form is gone for good — no re-armed time trap, no
					// second submit. Without an authored success message to
					// keep, fall through to the standard reveal instead.
					replaceWithMessage( form, success );
				} else {
					armTimeTrap( form );
					// reset() restores the DEFAULT values but fires neither
					// 'input' nor 'change', so the conditional and step state
					// still describe the submission that just went out: fields
					// stay hidden (and disabled) for answers no longer present,
					// and a stepped form sits on its last step. The next
					// visitor on the same page then fills a different form
					// than the one the server will validate.
					stepState.delete( form );
					applyConditions( form );
					applyStepView( form );
					// Snap first (fresh conditions and defaults), then pin:
					// a success message the author placed inside a step
					// stays on screen instead of being hidden by the snap
					// (#747). No-op outside a step or on a non-stepped form.
					if ( success ) {
						activateStepOf( form, success );
					}
				}
				if ( success ) {
					success.focus();
				}
				return;
			}

			if ( payload && payload.errors ) {
				let firstServerInvalid = null;
				Object.entries( payload.errors ).forEach(
					( [ name, message ] ) => {
						const control = controlForName( form, name );
						if ( control ) {
							showFieldError( form, control, message );
							if ( ! firstServerInvalid ) {
								firstServerInvalid = control;
							}
						}
					}
				);
				revealErrors( form, firstServerInvalid );
				return;
			}

			// An expired REST nonce. requireLogin forms bake wp_create_nonce
			// into the markup, and a nonce is session-bound and lives 12-24h —
			// so a page left open overnight, or a logout in another tab, fails
			// with a message about cookies that tells the visitor nothing they
			// can act on. Say the actionable thing instead.
			if ( payload && 'rest_cookie_invalid_nonce' === payload.code ) {
				revealErrors(
					form,
					null,
					form.dataset.blExpiredMessage ||
						'Your session expired. Please reload the page and try again.'
				);
				return;
			}

			// A server message without field errors (the honest 429, or an
			// unexpected server error) — surface the actual message.
			if ( payload && payload.message ) {
				revealErrors( form, null, payload.message );
				return;
			}

			// Network failure or unexpected payload: the authored error
			// message carries the moment.
			revealErrors( form, null );
		},
	},
} );
