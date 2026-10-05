/**
 * Screen slug => its component. ONE SCREEN PER LINE, deliberately.
 *
 * This file is a line grammar that bin/generate-edition.php filters (grammar
 * A): the free build simply does not have a line for a screen whose unit it
 * does not carry, so webpack never reaches the import and the chunk is never
 * emitted. Without this split the free bundle would carry the module screens
 * for features the artifact does not contain, which is both dead weight and
 * the shape guideline 5 objects to.
 *
 * A screen's COPY — label, title, subtitle, icon, deep-link params — is not
 * here and not in registry.js: it lives in screens/meta/<slug>.js behind the
 * one-line-per-screen index screens/meta.js, owned by the same unit, so the
 * label leaves the bundle with the component (#979). registry.js keeps the
 * ORDER (SCREEN_ORDER) and joins the two indexes. Adding a screen means a
 * line here, a meta file and its line, the slug in SCREEN_ORDER, and the
 * manifest's `screen` plus the meta path on the unit (generator rule 16
 * refuses the manifest otherwise).
 *
 * Home is eager (bundled in the main chunk) for instant first paint; the rest
 * are code-split and rendered inside the Shell's Suspense boundary.
 */

import { lazy } from '@wordpress/element';

import { HomeScreen } from './HomeScreen';

/* Screens are named exports; map them to a default for React.lazy. */
const lazyScreen = ( importer, name ) =>
	lazy( () => importer().then( ( m ) => ( { default: m[ name ] } ) ) );

export const COMPONENTS = {
	home: HomeScreen,
	seo: lazyScreen( () => import( './SeoScreen' ), 'SeoScreen' ),
	forms: lazyScreen( () => import( './FormsScreen' ), 'FormsScreen' ),
	'site-privacy': lazyScreen(
		() => import( './SitePrivacyScreen' ),
		'SitePrivacyScreen'
	),
	extensions: lazyScreen(
		() => import( './ExtensionsScreen' ),
		'ExtensionsScreen'
	),
	advanced: lazyScreen(
		() => import( './AdvancedScreen' ),
		'AdvancedScreen'
	),
	'child-theme': lazyScreen(
		() => import( './ChildThemeScreen' ),
		'ChildThemeScreen'
	),
};
