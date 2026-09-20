/**
 * Screen slug => its component. ONE SCREEN PER LINE, deliberately.
 *
 * This file is a line grammar that bin/generate-edition.php filters: the free
 * build simply does not have a line for a screen whose unit it does not carry,
 * so webpack never reaches the import and the chunk is never emitted. Without
 * this split the free bundle would carry ContentTypesScreen, DynamicValuesScreen
 * and AiMcpScreen — UI for features the artifact does not contain, which is
 * both dead weight and the shape guideline 5 objects to.
 *
 * registry.js keeps the ORDER, the labels and the deep-link params for every
 * screen; it filters itself down to the slugs present here. Adding a screen
 * means a line in both.
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
	scripts: lazyScreen( () => import( './ScriptsScreen' ), 'ScriptsScreen' ),
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
