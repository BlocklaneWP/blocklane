/**
 * Bridge to the wp-admin sidebar submenu.
 *
 * The Settings PHP class registers one submenu item per dashboard screen
 * (composite slugs: `{page}&screen={slug}`), so wp-admin's own submenu is the
 * app's left nav. These helpers make that menu behave like an in-app rail:
 *
 * - bindAdminMenu( onNavigate ) intercepts plain left-clicks on our items and
 *   routes client-side — no page reload. Modified clicks (new tab/window)
 *   fall through to the real link.
 * - syncAdminMenu( active ) moves wp-admin's `current` highlight to the active
 *   screen's item after a client-side navigation.
 *
 * The page slug is read from the current URL's ?page= param (the app only
 * ever runs on its own page), so the PHP menu slug is never hardcoded here.
 */

import { HOME_SLUG } from './screens/registry';
import { isModifiedClick } from './router';

const pageSlug = () =>
	new URLSearchParams( window.location.search ).get( 'page' );

/**
 * Our links inside #adminmenu (top-level + submenu), each keyed by the
 * screen its ?screen= param targets (absent = Home).
 *
 * @return {Array<{link: HTMLAnchorElement, screen: string}>} Menu links.
 */
const menuLinks = () => {
	const page = pageSlug();
	const links = [];

	if ( ! page ) {
		return links;
	}

	document.querySelectorAll( '#adminmenu a[href]' ).forEach( ( link ) => {
		let url;
		try {
			url = new URL( link.href, window.location.origin );
		} catch ( error ) {
			return;
		}

		if (
			url.searchParams.get( 'page' ) !== page ||
			! url.pathname.endsWith( '/admin.php' )
		) {
			return;
		}

		links.push( {
			link,
			screen: url.searchParams.get( 'screen' ) || HOME_SLUG,
		} );
	} );

	return links;
};

/**
 * Route clicks on our admin-menu items through the app's client-side
 * navigation instead of a full page load.
 *
 * @param {(screen: string) => void} onNavigate Called with the target screen slug.
 * @return {() => void} Cleanup function removing the listener.
 */
export const bindAdminMenu = ( onNavigate ) => {
	const menu = document.getElementById( 'adminmenu' );

	if ( ! menu ) {
		return () => {};
	}

	const handler = ( event ) => {
		// Let modified/secondary clicks reach the real link (new tab etc.).
		if ( isModifiedClick( event ) ) {
			return;
		}

		const anchor = event.target.closest( 'a[href]' );

		if ( ! anchor ) {
			return;
		}

		const match = menuLinks().find( ( item ) => item.link === anchor );

		if ( ! match ) {
			return;
		}

		event.preventDefault();
		onNavigate( match.screen );
	};

	menu.addEventListener( 'click', handler );

	return () => menu.removeEventListener( 'click', handler );
};

/**
 * Move wp-admin's submenu highlight to the active screen's item. Mirrors the
 * markup the menu walker emits server-side: `current` on the <li> and <a>,
 * aria-current="page" on the <a>. The top-level link is skipped — its <li>
 * carries wp-has-current-submenu, which is correct on every screen.
 *
 * @param {string} active Active screen slug.
 */
export const syncAdminMenu = ( active ) => {
	menuLinks().forEach( ( { link, screen } ) => {
		const item = link.closest( '.wp-submenu li' );

		if ( ! item ) {
			return;
		}

		const isActive = screen === active;

		item.classList.toggle( 'current', isActive );
		link.classList.toggle( 'current', isActive );

		if ( isActive ) {
			link.setAttribute( 'aria-current', 'page' );
		} else {
			link.removeAttribute( 'aria-current' );
		}
	} );
};
