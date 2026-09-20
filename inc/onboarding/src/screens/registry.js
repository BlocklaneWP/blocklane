/**
 * Screen registry — the single source of truth for dashboard screens.
 *
 * To add a dashboard screen you add ONE entry here (and drop the screen file in
 * ../screens), plus its slug => label in Settings::screens_manifest()
 * (inc/class-blocklane-pro-settings.php) — the nav is wp-admin's own submenu,
 * registered server-side, so PHP mirrors this list. The Shell derives routing,
 * full-bleed, and code-splitting from here, in order.
 *
 * Home is eager (bundled in the main chunk) for instant first paint; every other
 * screen is code-split via `Component: lazyScreen(...)` and rendered inside the
 * Shell's Suspense boundary, so the dashboard stays light as features grow.
 *
 * Entry shape:
 *   slug          URL ?screen= value + nav key (unique)
 *   label         sidebar nav label
 *   title         sidebar header title for the active screen
 *   subtitle      sidebar header subtitle for the active screen
 *   icon          @wordpress/icons glyph
 *   Component      the screen component (eager import, or lazyScreen(...))
 *   fullBleed      hide the CanvasHeader (screen owns its own header) — default true
 *   isHome         receives the onNavigate prop; used as the default route
 *   deepLinkParam  optional URL param this screen consumes (e.g. 'ext')
 *   deepLinkProp   prop name the screen receives the deep-link value as
 *   tabParam       optional URL param a tabbed screen reads its own tab from
 *                  (e.g. 'exttab'); listed so the Shell clears it, like
 *                  deepLinkParam, when navigating away. SEO and Forms carry
 *                  theirs as deepLinkParam because the tab IS their deep link.
 *                  A feature-tab screen (Extensions, Advanced) must declare
 *                  BOTH deepLinkParam and tabParam — useFeatureTabs refuses
 *                  an entry without them.
 */

import { __ } from '@wordpress/i18n';
import {
	home as homeIcon,
	settings as extensionsIcon,
	code as scriptsIcon,
	tag as dynamicValuesIcon,
	layout as contentTypesIcon,
	lock as sitePrivacyIcon,
	tool as advancedIcon,
	archive as childThemeIcon,
	plugins as aiMcpIcon,
	envelope as formsIcon,
} from '@wordpress/icons';
// The SEO mark — same glyph as the editor sidebar's toolbar icon, from the
// shared module so the two can't drift.
import { seoIcon } from '../../../shared/seo-ui';

import { COMPONENTS } from './components';

/* Every screen this PRODUCT has, in nav order — including ones this artifact
   may not carry. Exported so registry.test.js can hold it in lockstep with
   COMPONENTS: a key in one list and not the other is silent otherwise (a
   typo'd COMPONENTS key drops the real screen out of SCREENS, and wp-admin's
   submenu item then lands on the Dashboard with no error anywhere). */
export const ALL = [
	{
		slug: 'home',
		label: __( 'Dashboard', 'blocklane' ),
		title: __( 'Dashboard', 'blocklane' ),
		subtitle: __(
			'Quickly access the tools and resources you need to build with FSE.',
			'blocklane'
		),
		icon: homeIcon,
		fullBleed: false,
		isHome: true,
	},
	{
		slug: 'dynamic-values',
		label: __( 'Dynamic Values', 'blocklane' ),
		title: __( 'Dynamic Values', 'blocklane' ),
		subtitle: __(
			'Define reusable values once and bind blocks to them across the site.',
			'blocklane'
		),
		icon: dynamicValuesIcon,
		fullBleed: true,
	},
	{
		slug: 'content-types',
		label: __( 'Content Types', 'blocklane' ),
		title: __( 'Content Types', 'blocklane' ),
		subtitle: __(
			'Build custom post types with fields and taxonomies — locations, team, testimonials — that survive deactivation.',
			'blocklane'
		),
		icon: contentTypesIcon,
		fullBleed: true,
		deepLinkParam: 'ct',
		deepLinkProp: 'initialType',
		tabParam: 'cttab',
	},
	{
		slug: 'seo',
		label: __( 'SEO', 'blocklane' ),
		title: __( 'SEO', 'blocklane' ),
		subtitle: __(
			'Visibility tools for your site — sitemaps, search-engine settings, and more, in one place.',
			'blocklane'
		),
		icon: seoIcon,
		fullBleed: true,
		deepLinkParam: 'seotab',
		deepLinkProp: 'initialTab',
	},
	{
		slug: 'forms',
		label: __( 'Forms', 'blocklane' ),
		title: __( 'Forms', 'blocklane' ),
		subtitle: __(
			'Submissions from your forms — every entry is kept here even when email fails.',
			'blocklane'
		),
		icon: formsIcon,
		fullBleed: true,
		deepLinkParam: 'formstab',
		deepLinkProp: 'initialTab',
	},
	{
		slug: 'scripts',
		label: __( 'Scripts', 'blocklane' ),
		title: __( 'Scripts', 'blocklane' ),
		subtitle: __(
			'Add custom header, body, and footer code — analytics, pixels, and more.',
			'blocklane'
		),
		icon: scriptsIcon,
		fullBleed: true,
	},
	{
		slug: 'site-privacy',
		label: __( 'Site Visibility', 'blocklane' ),
		title: __( 'Site Visibility', 'blocklane' ),
		subtitle: __(
			'Control who can see the site — a password or maintenance page for visitors, plus search-engine indexing.',
			'blocklane'
		),
		icon: sitePrivacyIcon,
		fullBleed: true,
	},
	{
		slug: 'extensions',
		label: __( 'Extensions', 'blocklane' ),
		title: __( 'Extensions', 'blocklane' ),
		subtitle: __(
			'Toggle block-editor enhancements for the core blocks you already use.',
			'blocklane'
		),
		icon: extensionsIcon,
		fullBleed: true,
		deepLinkParam: 'ext',
		deepLinkProp: 'initialExtension',
		tabParam: 'exttab',
	},
	{
		slug: 'advanced',
		label: __( 'Advanced', 'blocklane' ),
		title: __( 'Advanced', 'blocklane' ),
		subtitle: __(
			'Opt-in admin, content, and security enhancements that work right inside core WordPress.',
			'blocklane'
		),
		icon: advancedIcon,
		fullBleed: true,
		deepLinkParam: 'adv',
		deepLinkProp: 'initialFeature',
		tabParam: 'advtab',
	},
	{
		slug: 'child-theme',
		label: __( 'Create Child Theme', 'blocklane' ),
		title: __( 'Create Child Theme', 'blocklane' ),
		subtitle: __(
			'Generate a child theme so you can fork without touching the parent.',
			'blocklane'
		),
		icon: childThemeIcon,
		fullBleed: true,
	},
	{
		slug: 'ai-mcp',
		label: __( 'Blocklane AI MCP', 'blocklane' ),
		title: __( 'Blocklane AI MCP', 'blocklane' ),
		subtitle: __(
			'Connect your site to an external AI tool through the WordPress Abilities API and MCP.',
			'blocklane'
		),
		icon: aiMcpIcon,
		fullBleed: true,
	},
];

/**
 * The screens this artifact actually carries, in order.
 *
 * A screen survives only if components.js still has a line for it — which is
 * how the free build ends up without Content Types, Dynamic Values or AI MCP
 * without registry.js needing to know anything about editions.
 */
export const SCREENS = ALL.filter(
	( screen ) => screen.slug in COMPONENTS
).map( ( screen ) => ( { ...screen, Component: COMPONENTS[ screen.slug ] } ) );

/**
 * Soft lookup for the Shell's ROUTING only: an unknown slug falls back to
 * Home, because wp-admin's submenu (registered in PHP) hands the Shell
 * whatever ?screen= it carries and a PHP/JS registry drift must not
 * white-screen the dashboard. Components use requireScreen.
 *
 * @param {string} slug Screen slug.
 * @return {Object} The entry, or Home.
 */
export const getScreen = ( slug ) =>
	SCREENS.find( ( s ) => s.slug === slug ) || SCREENS[ 0 ];

/**
 * Strict lookup for components: a typo throws, so it lands in the screen's
 * ErrorBoundary with a message instead of silently rendering the Dashboard
 * title or a dangling aria-labelledby.
 *
 * @param {string} slug      Screen slug.
 * @param {Array}  [screens] The registry (injectable for tests).
 * @return {Object} The entry.
 */
export const requireScreen = ( slug, screens = SCREENS ) => {
	const entry = screens.find( ( s ) => s.slug === slug );
	if ( ! entry ) {
		throw new Error( `Unknown dashboard screen: ${ slug }` );
	}
	return entry;
};

export const HOME_SLUG = 'home';
