/**
 * Dashboard shell, embedded in wp-admin. The admin bar and admin menu stay
 * visible (like core's Appearance → Fonts screen); the app's left nav IS the
 * wp-admin submenu — the Settings PHP class registers one item per screen and
 * adminMenu.js intercepts their clicks so switching screens never reloads.
 *
 * Routing is entirely data-driven from the screen registry (../screens/registry):
 * the active screen, its component (code-split), and whether it owns a full-bleed
 * header all come from that one list. Add a screen there, not here.
 *
 * The active screen is mirrored to a `?screen=` URL param (and a per-screen deep
 * link, e.g. `?ext=`) via router.js, so every view is bookmarkable and editor
 * links can land on a specific screen instead of Home. Legacy URLs are
 * rewritten to their current form on load (legacy-routes.js).
 */

import { useState, useEffect, useCallback, Suspense } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

import { CanvasHeader } from './CanvasHeader';
import { ErrorBoundary } from './ErrorBoundary';
import { SCREENS, getScreen, HOME_SLUG } from '../screens/registry';
import { getRouteParam, setRouteParam } from '../router';
import { bindAdminMenu, syncAdminMenu } from '../adminMenu';
import { normalizeLegacyRoute } from './legacy-routes';

const isKnownScreen = ( slug ) => SCREENS.some( ( s ) => s.slug === slug );

// The route the page loaded on: the screen and its one-shot sub-view deep
// link (each screen that supports one declares its own URL param, e.g.
// ?ext= / ?adv=). A legacy URL (?screen=security&sec=…) is REWRITTEN to
// its current form here, before anything renders from it, so every later
// reader — the tab shell's URL sync, the tab links, a reload of the
// resulting address — sees one canonical URL (legacy-routes.js explains
// the defect that in-memory aliasing caused). replaceState is idempotent,
// so StrictMode's double-run of the state initializer is harmless.
const initialRoute = () => {
	const legacy = normalizeLegacyRoute(
		getRouteParam,
		setRouteParam,
		( slug ) => getScreen( slug ).deepLinkParam
	);
	const raw = legacy ? legacy.screen : getRouteParam( 'screen' );
	const screen = raw && isKnownScreen( raw ) ? raw : HOME_SLUG;
	if ( legacy ) {
		return { screen, deepLink: legacy.deepLink };
	}
	const entry = getScreen( screen );
	return {
		screen,
		deepLink: entry.deepLinkParam
			? getRouteParam( entry.deepLinkParam ) || null
			: null,
	};
};

export const Shell = () => {
	// Resolved exactly once, on mount.
	const [ route ] = useState( initialRoute );
	const [ active, setActive ] = useState( route.screen );
	// One-shot deep link into a screen sub-view (e.g. ?ext=… / ?adv=…).
	// Cleared on any manual navigation so it doesn't re-trigger.
	const [ deepLink, setDeepLink ] = useState( route.deepLink );

	const entry = getScreen( active );
	const Screen = entry.Component;
	const showHeader = ! entry.fullBleed;

	const navigate = useCallback(
		( screen ) => {
			// Same-screen navigation (clicking the active screen's own submenu
			// item): the screen isn't remounted, so its sub-view — and the
			// deep-link param mirroring it (e.g. ?seotab=) — must survive.
			// There's nothing to change; clearing params here would leave the
			// URL claiming a different sub-view than the one still rendered.
			if ( screen === active ) {
				return;
			}
			setActive( screen );
			setRouteParam( 'screen', screen === HOME_SLUG ? null : screen );
			// Each screen owns its own sub-view deep-link and tab params; clear
			// them all on manual navigation so a stale param doesn't linger
			// after switching screens.
			SCREENS.forEach( ( s ) => {
				if ( s.deepLinkParam ) {
					setRouteParam( s.deepLinkParam, null );
				}
				if ( s.tabParam ) {
					setRouteParam( s.tabParam, null );
				}
			} );
			setDeepLink( null );
			// Keep wp-admin's submenu highlight on the active screen's item.
			syncAdminMenu( screen );
		},
		[ active ]
	);

	// Clicks on our wp-admin submenu items route client-side instead of
	// reloading the page.
	useEffect( () => bindAdminMenu( navigate ), [ navigate ] );

	// Props a screen may receive: Home gets onNavigate; the deep-link screen gets
	// its deep-link value under the prop name it declares.
	const screenProps = {};
	if ( entry.isHome ) {
		screenProps.onNavigate = navigate;
	}
	if ( entry.deepLinkParam && entry.deepLinkProp ) {
		screenProps[ entry.deepLinkProp ] = deepLink;
	}

	return (
		<div className="blocklane-pro-layout">
			<div
				className={ `blocklane-pro-canvas${
					showHeader ? '' : ' is-headerless'
				}` }
			>
				{ showHeader ? <CanvasHeader /> : null }
				{ /* The app's primary content landmark within wp-admin. */ }
				<main className="blocklane-pro-canvas__body">
					{ /* Keyed by the active screen: navigating remounts the
					     boundary, clearing a prior screen's error. */ }
					<ErrorBoundary key={ active } screen={ active }>
						<Suspense
							fallback={
								<div className="blocklane-pro-screen-loading">
									<Spinner />
								</div>
							}
						>
							<Screen { ...screenProps } />
						</Suspense>
					</ErrorBoundary>
				</main>
			</div>
		</div>
	);
};
