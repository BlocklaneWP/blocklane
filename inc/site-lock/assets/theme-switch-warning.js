/**
 * Confirm before activating a classic theme drops Site Visibility.
 *
 * Every Activate control on Appearance → Themes is a plain nonced link —
 * in the grid, in the no-JS fallback markup, and in the theme details
 * overlay — so one delegated listener in the capture phase covers all three
 * without touching core's own handlers.
 *
 * The block-theme roster comes from the server (WP_Theme::is_block_theme()).
 * Anything not on it is treated as classic and warned about: silence is the
 * exact failure this dialog exists to prevent, so an unknown theme errs loud.
 */
( function () {
	'use strict';

	const data = window.blocklaneProThemeSwitchWarning;
	if ( ! data || ! Array.isArray( data.blockThemes ) ) {
		return;
	}

	let dialog = null;

	/**
	 * The theme a given Activate link targets, or '' when the link isn't one.
	 *
	 * @param {HTMLAnchorElement} link Candidate link.
	 * @return {string} Stylesheet, or '' when this isn't a theme activation.
	 */
	function targetStylesheet( link ) {
		let url;
		try {
			url = new URL( link.href, window.location.origin );
		} catch ( e ) {
			return '';
		}
		// Network admin's Themes screen uses action=enable on the same markup;
		// only a real activation is ours to interrupt.
		if ( 'activate' !== url.searchParams.get( 'action' ) ) {
			return '';
		}
		return url.searchParams.get( 'stylesheet' ) || '';
	}

	/**
	 * The theme's display name, read from whichever card or overlay holds
	 * the link. Falls back to a generic noun rather than guessing.
	 *
	 * @param {HTMLAnchorElement} link Activate link.
	 * @return {string} Theme name.
	 */
	function themeName( link ) {
		const card = link.closest( '.theme, .theme-overlay' );
		const name = card ? card.querySelector( '.theme-name' ) : null;
		return ( name && name.textContent.trim() ) || data.fallbackName;
	}

	/**
	 * Fill the template's numbered placeholders.
	 *
	 * @param {string}   template Localized string with %1$s-style tokens.
	 * @param {string[]} args     Replacements, in order.
	 * @return {string} Formatted string.
	 */
	function format( template, args ) {
		return template.replace( /%(\d+)\$s/g, function ( match, index ) {
			const value = args[ parseInt( index, 10 ) - 1 ];
			return undefined === value ? match : value;
		} );
	}

	/**
	 * Build the dialog once; its copy is refreshed per opening.
	 *
	 * @return {HTMLElement} The dialog element.
	 */
	function buildDialog() {
		const el = document.createElement( 'dialog' );
		el.className = 'blocklane-pro-theme-switch-warning';
		el.setAttribute( 'aria-labelledby', 'blocklane-pro-tsw-title' );
		el.setAttribute( 'aria-describedby', 'blocklane-pro-tsw-body' );

		const title = document.createElement( 'h2' );
		title.id = 'blocklane-pro-tsw-title';
		title.className = 'blocklane-pro-tsw__title';
		title.textContent = data.title;

		const body = document.createElement( 'p' );
		body.id = 'blocklane-pro-tsw-body';
		body.className = 'blocklane-pro-tsw__body';

		const reassurance = document.createElement( 'p' );
		reassurance.className = 'blocklane-pro-tsw__reassurance';
		reassurance.textContent = data.reassurance;

		const actions = document.createElement( 'div' );
		actions.className = 'blocklane-pro-tsw__actions';

		// Cancel is the primary, focused button: the dialog exists to slow a
		// destructive switch down, not to wave it through.
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button button-primary';
		cancel.textContent = data.cancelLabel;

		const confirm = document.createElement( 'button' );
		confirm.type = 'button';
		confirm.className = 'button blocklane-pro-tsw__confirm';
		confirm.textContent = data.confirmLabel;

		actions.appendChild( cancel );
		actions.appendChild( confirm );
		el.appendChild( title );
		el.appendChild( body );
		el.appendChild( reassurance );
		el.appendChild( actions );
		document.body.appendChild( el );

		el.bodyEl = body;
		el.cancelEl = cancel;
		el.confirmEl = confirm;
		return el;
	}

	/**
	 * Ask, then follow the link only if the user says yes.
	 *
	 * @param {string} name Theme name.
	 * @param {string} href Activation URL.
	 * @return {void}
	 */
	function ask( name, href ) {
		const message = format( data.body, [
			name,
			data.pluginName,
			data.modeLabel,
		] );

		// <dialog> brings its own focus trap, Esc handling and inertness. If
		// it isn't available, a native confirm still beats silence.
		if (
			! window.HTMLDialogElement ||
			! HTMLDialogElement.prototype.showModal
		) {
			// eslint-disable-next-line no-alert
			const proceed = window.confirm(
				data.title + '\n\n' + message + '\n\n' + data.reassurance
			);
			if ( proceed ) {
				window.location.assign( href );
			}
			return;
		}

		if ( ! dialog ) {
			dialog = buildDialog();
		}
		dialog.bodyEl.textContent = message;

		const close = function () {
			dialog.close();
		};
		const go = function () {
			dialog.confirmEl.removeEventListener( 'click', go );
			dialog.cancelEl.removeEventListener( 'click', close );
			window.location.assign( href );
		};
		dialog.addEventListener(
			'close',
			function () {
				dialog.confirmEl.removeEventListener( 'click', go );
				dialog.cancelEl.removeEventListener( 'click', close );
			},
			{ once: true }
		);
		dialog.confirmEl.addEventListener( 'click', go );
		dialog.cancelEl.addEventListener( 'click', close );

		dialog.showModal();
		dialog.cancelEl.focus();
	}

	document.addEventListener(
		'click',
		function ( event ) {
			const link = event.target.closest
				? event.target.closest( 'a.activate' )
				: null;
			if ( ! link ) {
				return;
			}
			const stylesheet = targetStylesheet( link );
			if (
				! stylesheet ||
				-1 !== data.blockThemes.indexOf( stylesheet )
			) {
				return;
			}
			// Hold the navigation AND core's own handlers until the user answers.
			event.preventDefault();
			event.stopPropagation();
			ask( themeName( link ), link.href );
		},
		true
	);
} )();
