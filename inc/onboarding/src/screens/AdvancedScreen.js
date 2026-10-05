/**
 * Advanced screen — opt-in admin, content, and security enhancements.
 *
 * Core admin-ui page arrangement (ScreenHeader + ScreenTabs, shared with
 * SEO, Forms, the content-types screen, and Extensions): a page header, then one tab
 * per category (Content & Editing, Site Tools, Updates & Performance,
 * Security & Hardening), then the tab's content — a toolbar (Enable/Disable
 * All for the tab, a search box scoped to the tab) above the tab's feature
 * rows (toggle · title · short description · chevron). Beside the rows, the
 * help drawer: overview copy by default; clicking a row pins its detail
 * there, where a feature's extra settings (revisions count, reorder post
 * types, heartbeat interval) also live. Hovering a row previews it while
 * nothing is pinned. Escape, re-clicking the pinned row, the drawer handle,
 * or switching tabs unpins. Every option is off by default and appears
 * through native WordPress surfaces, so it feels like core. Toggles save
 * immediately and optimistically; a refused save reverts that store — at
 * once, synchronously — to the last state the server confirmed and clears
 * the confirmation snackbar, so the screen never keeps or re-sends a state
 * the server rejected. A snackbar confirms each save that lands.
 *
 * URL: ?screen=advanced&advtab=<category> (the default tab clears the
 * param); ?adv=<slug> pins that feature on its own tab (the SEO overview,
 * the child-theme screen, and a disabled tool screen all link here); an
 * unknown slug pins nothing and is dropped from the URL. All of that — the
 * registry entry, the tab shell, the pinned item and its param, bucketing
 * and search — is useFeatureTabs (components/use-feature-tabs.js); this
 * screen types its registry slug once and never writes the URL. The tab
 * model (feature-tabs.js): an item's tab is its category; a slug with no
 * mapping lands on the last tab. Enable/Disable All needs no in-flight
 * guard here: each store saves as one whole object, serialized with a
 * dirty-flag re-flush, so a row toggled mid-save merges in and is re-sent.
 *
 * The feature rows come from two places on purpose. A row whose slug is a
 * manifest `toggle` is OWNED by that unit and lives in advanced/rows/<toggle>.js
 * behind the one-line-per-row index advanced/rows.js; an edition that does not
 * carry the unit carries neither the line nor the file, so its copy is never in
 * the bundle — the screen is not filtered at runtime, it is simply built from
 * fewer rows. Everything no unit owns stays inline in SHARED_ROW_LIST.
 * ROW_ORDER is the single display order across both halves.
 *
 * The former Security screen merged in here (2026-07) as the Security &
 * Hardening tab. It was a UI merge only: those six toggles keep their own
 * option and REST route (blocklane_pro_security), so each feature declares the
 * store it writes through and nothing migrated. Two of the six (the forced
 * plugin and theme updates) are contributed by Pro units and live in
 * advanced/rows/ like any unit-owned row: in an edition without those units
 * they are Pro rows, and the security store neither hands out nor accepts
 * their keys. Security toggles the
 * environment forces on (a wp-config constant) render locked with an
 * "Enforced" badge, exactly as they did on their own screen; the legacy
 * ?screen=security&sec=<slug> links still land on the same toggle — the
 * Shell rewrites them to ?screen=advanced&adv=<slug> on load
 * (components/legacy-routes.js), so this screen never sees `sec`.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
} from '@wordpress/element';
import {
	Flex,
	FlexItem,
	CheckboxControl,
	Notice,
	Snackbar,
	Spinner,
	RangeControl,
	ToggleControl,
	__experimentalDivider as Divider,
} from '@wordpress/components';

import {
	advanced as advancedApi,
	security as securityApi,
} from '../api/client';
import { errorMessage } from '../api/errors';
import { FeatureItem } from '../components/FeatureItem';
import { ProDetailHeading } from '../components/ProDetailHeading';
import { FeatureDescription } from '../components/FeatureDescription';
import { FeatureToolbar } from '../components/FeatureToolbar';
import { HelpTab, useHelpPreference } from '../components/HelpTab';
import { ScreenHeader } from '../components/ScreenHeader';
import { ScreenTabs, ScreenTabPanel } from '../components/ScreenTabs';
import { useFeatureTabs } from '../components/use-feature-tabs';
import { pluginName } from '../edition';
// The ONE predicate for "this row belongs to a product this build is not":
// it reads the factory's private mark, never a row's own `pro` property (#986).
import { isProRow } from './pro-row.js';
// Every Advanced row a UNIT owns, one re-export per line — the index the
// generator rewrites. Namespace import on purpose: the object is whatever the
// index exports after the rewrite — live rows for present units, proRow()
// stand-ins for absent ones — and THIS file names no unit; the index does.
import * as UNIT_ROW_MODULES from './advanced/rows';

/* Display + detail copy per feature. `short` shows in the row; `description`
   (string, or array of strings/heading/list blocks) shows in the detail panel.
   `store: 'security'` routes a toggle through the security option/REST route;
   everything else persists through the advanced one.

   A row whose slug is a manifest `toggle` belongs to that unit and lives in
   screens/advanced/rows/<toggle>.js, re-exported one line per row by
   screens/advanced/rows.js — an edition without the unit loses the line AND
   the file, so the copy for a tool the artifact does not carry never reaches
   the bundle. The rows BELOW are the ones no unit owns: they ship in every
   edition, so they stay inline. ROW_ORDER is the one display order for both
   halves and rows.test.js holds the three lists in lockstep. */
const SHARED_ROW_LIST = [
	{
		slug: 'duplicate-content',
		title: __( 'Duplicate Content', 'blocklane' ),
		short: __( 'Clone posts, pages, and custom post types.', 'blocklane' ),
		description: __(
			'Adds a “Clone” link to the row actions on Posts, Pages, and custom post types. Cloning copies the content, custom fields, and taxonomy terms into a new draft, ready to edit.',
			'blocklane'
		),
	},
	{
		slug: 'reorder-content',
		title: __( 'Reorder Content', 'blocklane' ),
		short: __(
			'Drag to set a manual order — and group pages.',
			'blocklane'
		),
		description: __(
			'Adds a “Sort by Order” view where you can drag rows into a manual order, and orders the chosen post types by that on the front end. Queries that ask for a specific order — like recent posts or events — still win, so use it for content like team members, testimonials, or services. Pages (and other nestable types) also become a collapsible tree: a chevron on any parent page hides or shows everything nested under it, and Indent/Outdent row actions move pages into or out of a group — the same parent/child structure as the page editor’s Parent setting, just faster to arrange. With this on, the Pages screen shows the whole tree on one page; very large sites (500+ pages) stay paginated instead, and “Sort by Order” opens the full tree on demand.',
			'blocklane'
		),
	},
	{
		slug: 'media-replacement',
		title: __( 'Replace Media', 'blocklane' ),
		short: __( 'Swap a file in place, keeping the same URL.', 'blocklane' ),
		description: __(
			'Adds a “Replace file” action in the media library, next to Download. Upload a new file of the same type and it swaps in place — same URL and filename — so every post and reference keeps working. Thumbnails regenerate automatically.',
			'blocklane'
		),
	},
	{
		slug: 'disable-comments',
		title: __( 'Disable Comments', 'blocklane' ),
		short: __(
			'Turn off the entire comment system site-wide.',
			'blocklane'
		),
		description: __(
			'Switches comments off everywhere: closes comments and pings on all content, hides the comment blocks and feeds on the front end, removes the Comments admin menu, metaboxes, dashboard widget, and toolbar item, and limits the comment REST endpoints to editor notes to cut spam and enumeration. Editor notes (WordPress 7.1) keep working — they are an editorial tool for logged-in editors, not public comments. Nothing is deleted — existing comments are just hidden and come back if you turn this off. Leave it off for blogs or news sites that need discussion.',
			'blocklane'
		),
	},
	{
		slug: 'disable-blog-features',
		title: __( 'Disable Blog Features', 'blocklane' ),
		short: __(
			'Strip rarely-used blogging features for non-blogs.',
			'blocklane'
		),
		description: __(
			'Cleans up blogging leftovers most sites never use. Disables Post Formats (drops post-format theme support — hiding the editor’s format panel where a theme offers one — and removes the Default Post Format setting), and on Settings → Writing hides Post via Email and stops the default Update Services (ping-o-matic) pings sent when you publish. Your default category stays. Nothing is deleted and it all reverts when turned off — leave it off for blogs or news sites.',
			'blocklane'
		),
	},
	// Updates & Performance.
	{
		slug: 'limit-revisions',
		title: __( 'Limit Post Revisions', 'blocklane' ),
		short: __( 'Cap stored revisions to keep the DB lean.', 'blocklane' ),
		description: __(
			'Caps how many revisions WordPress keeps per post so the database doesn’t grow without bound. Set the number below; use 0 to turn revisions off entirely.',
			'blocklane'
		),
	},
	{
		slug: 'limit-heartbeat',
		title: __( 'Limit the WordPress Heartbeat', 'blocklane' ),
		short: __(
			'Slow the admin polling that hits admin-ajax.',
			'blocklane'
		),
		description: __(
			'The WordPress Heartbeat polls admin-ajax.php every 15–60 seconds to power autosave and post-locking — steady background load, especially on shared hosting. This throttles it to the interval below and turns it off entirely on the front end (public pages don’t need it). Autosave and post-locking keep working, just less often. Set the interval below.',
			'blocklane'
		),
	},
	// Security & Hardening.
	{
		slug: 'limit-login-attempts',
		store: 'security',
		title: __( 'Limit Login Attempts', 'blocklane' ),
		short: __(
			'Slow down brute-force attacks on the login form.',
			'blocklane'
		),
		description: __(
			'Locks out an IP address for a few minutes after 5 failed logins, blunting password brute-forcing on wp-login. Behind a reverse proxy or CDN this keys on the proxy address; some hosts already rate-limit logins at the edge.',
			'blocklane'
		),
	},
	{
		slug: 'disable-xmlrpc',
		store: 'security',
		title: __( 'Disable XML-RPC', 'blocklane' ),
		short: __(
			'Turn off /xmlrpc.php to close a common attack surface.',
			'blocklane'
		),
		description: __(
			'Closes brute-force amplification and pingback abuse. Leave this off if you use the WordPress mobile app, Jetpack, or remote publishing, which rely on XML-RPC. Some managed hosts already block it.',
			'blocklane'
		),
	},
	{
		slug: 'disable-file-editing',
		store: 'security',
		title: __( 'Disable Theme & Plugin File Editing', 'blocklane' ),
		short: __( 'Remove the dashboard code editors.', 'blocklane' ),
		description: __(
			'Defines DISALLOW_FILE_EDIT so a compromised admin account can’t run PHP through the built-in Appearance and Plugins code editors. No effect on normal editing. Some managed hosts set this already.',
			'blocklane'
		),
	},
	{
		slug: 'block-user-enumeration',
		store: 'security',
		title: __( 'Block User Enumeration', 'blocklane' ),
		short: __( 'Stop bots from harvesting your usernames.', 'blocklane' ),
		description: __(
			'Redirects ?author= probes and removes the public REST user list, so bots can’t collect your usernames to target brute-force attacks. Doesn’t affect logged-in editing or the block editor. Usually not covered by hosts.',
			'blocklane'
		),
	},
	{
		slug: 'safe-svg-upload',
		title: __( 'Safe SVG Uploads', 'blocklane' ),
		short: __( 'Upload SVGs — sanitized automatically.', 'blocklane' ),
		description: __(
			'Allows SVG uploads in the media library and sanitizes every file on the way in, stripping scripts and other unsafe markup. Anything that can’t be cleaned is rejected, so a malicious SVG can’t carry code into your library.',
			'blocklane'
		),
	},
	{
		slug: 'last-login-column',
		title: __( 'Last Login Column', 'blocklane' ),
		short: __( 'See when each user last signed in.', 'blocklane' ),
		description: __(
			'Adds a “Last Login” column to the Users screen, showing when each account last signed in (and how long ago), so you can spot dormant or shared accounts.',
			'blocklane'
		),
	},
	{
		slug: 'clean-uninstall',
		title: __( 'Clean Uninstall', 'blocklane' ),
		short: __(
			'Delete removes every remaining Blocklane setting too.',
			'blocklane'
		),
		// A shared row, so the plugin it names is whichever artifact this is.
		description: sprintf(
			/* translators: %s: the plugin name. */
			__(
				'Deleting %s always stops what it renders — forms, popups, menus, content types, dynamic values — because everything runs from the plugin itself. By default it leaves two things behind, because they are yours rather than plugin state: your uploaded SVG icons and any header, body and footer code saved on this site. A reinstall picks both back up. Turn this on and Delete clears those as well, along with every remaining Blocklane setting. Your content records — pages, entries, terms, form submissions — are kept either way. Leave this off unless you are removing Blocklane for good.',
				'blocklane'
			),
			pluginName()
		),
	},
];

/* Slug => row, for each half. Exported for rows.test.js, which is what
   asserts the two halves are disjoint and together cover ROW_ORDER — a row
   that landed in both, or a slug in neither, is a silent hole otherwise. */
export const SHARED_ROWS = Object.fromEntries(
	SHARED_ROW_LIST.map( ( f ) => [ f.slug, f ] )
);

/* Whatever the index still re-exports — keyed by each row's OWN slug, so the
   key comes from the row data rather than from an export name that could
   drift from it. In free an absent unit's row is a proRow() stand-in, or null
   and dropped when the catalog has no entry for it. */
export const UNIT_ROWS = Object.fromEntries(
	// A proRow() entry is null when the localized catalog has no entry for
	// its unit — a build fault, a screen rendered outside wp-admin, a test.
	// Dropped here rather than crashing the whole screen on `.slug`: the
	// generator writes these lines, so the index is not hand-checkable.
	Object.values( UNIT_ROW_MODULES )
		.filter( Boolean )
		.map( ( f ) => [ f.slug, f ] )
);

const ROWS = { ...SHARED_ROWS, ...UNIT_ROWS };

/* Display order — every row this PRODUCT has, unit-owned or not, in the order
   the screen lists them. A slug whose unit this edition does not carry keeps
   its place as a Pro row (proRow), or drops out of FEATURES when the catalog
   has no entry for it; nothing here reads an edition. */
export const ROW_ORDER = [
	// Content & Editing.
	'seo',
	'forms',
	'carousel',
	'duplicate-content',
	'reorder-content',
	'media-replacement',
	'disable-comments',
	'disable-blog-features',
	// Site Tools. The four module-screen toggles are surface gates: off hides
	// the screen only — the front-end runtime keeps working, so a client's
	// content and behavior are never affected.
	'content-types',
	'dynamic-values',
	'scripts',
	'site-privacy',
	'ai-mcp',
	'ai-tools',
	'popups',
	'child-theme-tool',
	// Updates & Performance.
	'auto-update-plugins',
	'auto-update-themes',
	'limit-revisions',
	'limit-heartbeat',
	// Security & Hardening.
	'limit-login-attempts',
	'disable-xmlrpc',
	'disable-file-editing',
	'block-user-enumeration',
	'safe-svg-upload',
	'last-login-column',
	'clean-uninstall',
];

const FEATURES = ROW_ORDER.filter( ( s ) => s in ROWS ).map(
	( s ) => ROWS[ s ]
);

/* The tabs — same model as the Extensions screen: CATEGORIES is the tab
   order (the first is the default tab); CATEGORY_OF maps each slug to its
   tab. A slug with no mapping lands on the last tab (feature-tabs.js), so new
   features still appear without a code change here. */
const CATEGORIES = [
	{ slug: 'content', label: __( 'Content & Editing', 'blocklane' ) },
	{ slug: 'tools', label: __( 'Site Tools', 'blocklane' ) },
	{ slug: 'updates', label: __( 'Updates & Performance', 'blocklane' ) },
	{ slug: 'security', label: __( 'Security & Hardening', 'blocklane' ) },
];

const CATEGORY_OF = {
	seo: 'content',
	forms: 'content',
	carousel: 'content',
	'duplicate-content': 'content',
	'reorder-content': 'content',
	'media-replacement': 'content',
	'disable-comments': 'content',
	'disable-blog-features': 'content',
	'content-types': 'tools',
	'dynamic-values': 'tools',
	scripts: 'tools',
	'site-privacy': 'tools',
	'ai-mcp': 'tools',
	'ai-tools': 'tools',
	popups: 'tools',
	'child-theme-tool': 'tools',
	'auto-update-plugins': 'updates',
	'auto-update-themes': 'updates',
	'limit-revisions': 'updates',
	'limit-heartbeat': 'updates',
	'clean-uninstall': 'updates',
	'limit-login-attempts': 'security',
	'disable-xmlrpc': 'security',
	'disable-file-editing': 'security',
	'block-user-enumeration': 'security',
	'safe-svg-upload': 'security',
	'last-login-column': 'security',
};

// Toggles whose effect lands in the always-visible wp-admin chrome (rendered
// server-side), so the page must reload for the change to show — e.g. disabling
// comments removes the Comments menu, and the child-theme toggle adds/removes
// the Create Child Theme submenu item. Others only affect pages you'd navigate
// to anyway, so they don't need a reload.
const CHROME_CHANGING = [
	'disable-comments',
	'child-theme-tool',
	'popups',
	'content-types',
	'dynamic-values',
	'scripts',
	'site-privacy',
	'ai-mcp',
];

const FEATURE_SLUGS = FEATURES.map( ( f ) => f.slug );
const FEATURE_BY_SLUG = new Map( FEATURES.map( ( f ) => [ f.slug, f ] ) );

/* What the tab's search box matches. */
const textOf = ( slug ) => {
	const f = FEATURE_BY_SLUG.get( slug );
	return [ f.title, f.short ];
};

export const AdvancedScreen = ( { initialFeature } = {} ) => {
	// The deep link (?adv=slug; a legacy ?screen=security&sec= URL is
	// rewritten to it by the Shell before this screen mounts) is handed to
	// useFeatureTabs below, which validates it and opens its tab.
	const [ settings, setSettings ] = useState( null );
	const [ secSettings, setSecSettings ] = useState( null );
	// Security toggles the environment forces on (a wp-config constant);
	// rendered locked-on so the user isn't misled into thinking they can turn
	// them off here.
	const [ forced, setForced ] = useState( {} );
	const [ postTypes, setPostTypes ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ snackbar, setSnackbar ] = useState( '' );
	const pageStart = useRef( null );
	// Mouse-only enhancement: hovering a card previews it in the panel (pinning
	// via click/Enter is the hook's `pinned` below). A ~90ms hover-intent
	// delay keeps a quick sweep across cards from strobing the panel.
	const [ hovered, setHovered ] = useState( null );
	const hoverTimer = useRef( null );
	const previewOnHover = useCallback( ( slug ) => {
		clearTimeout( hoverTimer.current );
		hoverTimer.current = setTimeout( () => setHovered( slug ), 90 );
	}, [] );
	const clearHover = useCallback( () => {
		clearTimeout( hoverTimer.current );
		setHovered( null );
	}, [] );
	useEffect( () => () => clearTimeout( hoverTimer.current ), [] );

	useEffect( () => {
		pageStart.current?.focus();
	}, [] );

	// Each save persists the WHOLE settings object of its store, so concurrent
	// saves would race (on the client AND server). The refs hold the latest
	// full state per store; saves are serialized per store — one in flight at
	// a time, with a single follow-up save if changes pile up — so the last
	// write always reflects the latest state.
	const settingsRef = useRef( null );
	// The last state each store's server confirmed (the load, then every
	// accepted save). A refused save reverts to it — synchronously, so a
	// toggle made in the same instant builds on it, never on the rejected
	// state — instead of refetching, which left a window where the rejected
	// state could be re-sent and a late response could overwrite a newer one.
	const serverRef = useRef( null );
	const secServerRef = useRef( null );
	const savingRef = useRef( false );
	const dirtyRef = useRef( false );
	const secRef = useRef( null );
	const secSavingRef = useRef( false );
	const secDirtyRef = useRef( false );
	// Set when a chrome-changing toggle is flipped; the final save reloads so the
	// wp-admin sidebar (e.g. the Comments menu) reflects it.
	const reloadAfterSave = useRef( false );

	useEffect( () => {
		advancedApi
			.get()
			.then( ( res ) => {
				settingsRef.current = res?.settings || {};
				serverRef.current = settingsRef.current;
				setSettings( settingsRef.current );
				setPostTypes( res?.postTypes || [] );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );
		securityApi
			.get()
			.then( ( res ) => {
				secRef.current = res?.settings || {};
				secServerRef.current = secRef.current;
				setSecSettings( secRef.current );
				setForced( res?.forced || {} );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	}, [] );

	const loaded = null !== settings && null !== secSettings;

	// After the final save of a chrome-changing toggle, reload so the wp-admin
	// sidebar reflects it — but only once BOTH stores are idle. Enable/Disable
	// All saves the security and advanced stores in parallel, and reloading
	// from one store's then-handler could interrupt the other's in-flight PUT
	// (stale Security toggles after the reload, or an aborted save that never
	// commits). Any failed save clears the flag instead (error notice, no
	// reload).
	const maybeReloadAfterSave = useCallback( () => {
		if ( ! reloadAfterSave.current ) {
			return;
		}
		if (
			savingRef.current ||
			dirtyRef.current ||
			secSavingRef.current ||
			secDirtyRef.current
		) {
			return;
		}
		reloadAfterSave.current = false;
		window.location.reload();
	}, [] );

	// A refused save must not leave the screen showing — or re-sending — a
	// state the server rejected: revert that store to the last confirmed
	// state right now, drop the optimistic patch and anything queued behind
	// it (they did not land, and a retry would resend a state built on the
	// rejected one), and take the confirmation snackbar down.
	const revert = useCallback( ( err ) => {
		setError( errorMessage( err ) );
		setSnackbar( '' );
		reloadAfterSave.current = false; // don't reload after a failed save
		dirtyRef.current = false;
		settingsRef.current = serverRef.current || {};
		setSettings( settingsRef.current );
	}, [] );
	const revertSecurity = useCallback( ( err ) => {
		setError( errorMessage( err ) );
		setSnackbar( '' );
		reloadAfterSave.current = false;
		secDirtyRef.current = false;
		secRef.current = secServerRef.current || {};
		setSecSettings( secRef.current );
	}, [] );

	// Serialized save of the advanced store's ref state.
	const flush = useCallback( () => {
		if ( savingRef.current ) {
			dirtyRef.current = true;
			return;
		}
		savingRef.current = true;
		advancedApi
			.save( settingsRef.current )
			.then( ( res ) => {
				if ( res?.settings ) {
					serverRef.current = res.settings;
				}
				if ( res?.settings && ! dirtyRef.current ) {
					settingsRef.current = res.settings;
					setSettings( res.settings );
				}
			} )
			.catch( revert )
			.finally( () => {
				savingRef.current = false;
				if ( dirtyRef.current ) {
					dirtyRef.current = false;
					flush();
					return;
				}
				maybeReloadAfterSave();
			} );
	}, [ maybeReloadAfterSave, revert ] );

	// Serialized save of the security store's ref state (same shape; that
	// route also returns the environment-forced map).
	const flushSecurity = useCallback( () => {
		if ( secSavingRef.current ) {
			secDirtyRef.current = true;
			return;
		}
		secSavingRef.current = true;
		securityApi
			.save( secRef.current )
			.then( ( res ) => {
				if ( res?.settings ) {
					secServerRef.current = res.settings;
				}
				if ( res?.settings && ! secDirtyRef.current ) {
					secRef.current = res.settings;
					setSecSettings( res.settings );
				}
				if ( res?.forced ) {
					setForced( res.forced );
				}
			} )
			.catch( revertSecurity )
			.finally( () => {
				secSavingRef.current = false;
				if ( secDirtyRef.current ) {
					secDirtyRef.current = false;
					flushSecurity();
					return;
				}
				maybeReloadAfterSave();
			} );
	}, [ maybeReloadAfterSave, revertSecurity ] );

	// Apply a settings patch (used by toggles and sub-settings alike) to the
	// latest full state of the owning store, then serialize the save.
	const persist = useCallback(
		( patch, meta, store = 'advanced' ) => {
			setError( '' );
			if ( 'security' === store ) {
				secRef.current = { ...( secRef.current || {} ), ...patch };
				setSecSettings( secRef.current );
			} else {
				settingsRef.current = {
					...( settingsRef.current || {} ),
					...patch,
				};
				setSettings( settingsRef.current );
			}
			if ( meta ) {
				setSnackbar( meta );
			}
			if ( 'security' === store ) {
				flushSecurity();
			} else {
				flush();
			}
		},
		[ flush, flushSecurity ]
	);

	const isForced = useCallback(
		( feature ) =>
			'security' === feature.store && !! forced[ feature.slug ],
		[ forced ]
	);
	const isOn = useCallback(
		( feature ) => {
			// A row whose feature is not in this build has no state to read:
			// GET never hands its key out (Advanced::get returns only the keys
			// this edition owns), save refuses one sent and never fills one in,
			// so "off" is the only honest answer — and the whole-object PUT
			// this screen makes carries no such key by construction.
			if ( isProRow( feature ) ) {
				return false;
			}
			if ( isForced( feature ) ) {
				return true;
			}
			return 'security' === feature.store
				? !! secSettings?.[ feature.slug ]
				: !! settings?.[ feature.slug ];
		},
		[ isForced, secSettings, settings ]
	);

	// The one predicate for "this row is not writable here" — a Pro row (no
	// code in this build) or a row the server configuration pins. Every BULK
	// door reads this rather than testing the two conditions itself: the last
	// time a guard was added at one door and not its sibling, the sibling was
	// the defect (AGENTS.md, the half-applied class). Adding a third bulk
	// action means calling this, not repeating it.
	const isLocked = useCallback(
		( feature ) => isProRow( feature ) || isForced( feature ),
		[ isForced ]
	);

	// The row's badge: the Pro mark, the server-pinned mark, or none. Beside
	// isLocked because they answer the same question from two directions.
	const badgeOf = useCallback(
		( feature ) => {
			if ( isProRow( feature ) ) {
				return feature.badge;
			}
			return isForced( feature )
				? __( 'Enforced', 'blocklane' )
				: undefined;
		},
		[ isForced ]
	);

	const toggle = useCallback(
		( feature, value ) => {
			// Defense in depth behind FeatureItem, which draws no switch for a
			// Pro row: a Pro row has no code behind it, so writing its key would
			// store an ON for a feature that cannot run — and have it honored,
			// without anyone opting in, the day Pro is installed. The server refuses the same
			// key (Advanced::save, #844) and never hands it out (Advanced::get,
			// #969); this keeps the request from being made at all.
			if ( isProRow( feature ) ) {
				return;
			}
			if ( CHROME_CHANGING.includes( feature.slug ) ) {
				reloadAfterSave.current = true;
			}
			persist(
				{ [ feature.slug ]: value },
				value
					? sprintf(
							/* translators: %s: the feature or extension name. */
							__( '%s enabled', 'blocklane' ),
							feature.title
						)
					: sprintf(
							/* translators: %s: the feature or extension name. */
							__( '%s disabled', 'blocklane' ),
							feature.title
						),
				feature.store
			);
		},
		[ persist ]
	);

	const togglePostType = ( slug, checked ) => {
		const base = settingsRef.current || {};
		const current = Array.isArray( base[ 'reorder-post-types' ] )
			? base[ 'reorder-post-types' ]
			: [];
		const next = checked
			? [ ...new Set( [ ...current, slug ] ) ]
			: current.filter( ( s ) => s !== slug );
		persist( { 'reorder-post-types': next } );
	};

	// The tabbed composition: registry entry, tab shell, the pinned item
	// (?adv=) and its tab, this tab's rows and search. After isOn, which it
	// reads in render.
	const ft = useFeatureTabs( {
		screen: 'advanced',
		categories: CATEGORIES,
		categoryOf: CATEGORY_OF,
		knownSlugs: FEATURE_SLUGS,
		slugs: FEATURE_SLUGS,
		deepLink: initialFeature,
		textOf,
		isOn: ( s ) => {
			const f = FEATURE_BY_SLUG.get( s );
			// A locked row is excluded from "all on" rather than counted as
			// off: counting it off would pin the bulk button to "Enable all"
			// on every tab that shows a Pro row.
			return f && isLocked( f ) ? true : isOn( f );
		},
	} );
	const { pinned, pin, open, unpin, tab, tabLabel, search, setSearch } = ft;
	const tabFeatures = useMemo(
		() => ft.tabSlugs.map( ( s ) => FEATURE_BY_SLUG.get( s ) ),
		[ ft.tabSlugs ]
	);
	const filtered = useMemo(
		() => ft.visibleSlugs.map( ( s ) => FEATURE_BY_SLUG.get( s ) ),
		[ ft.visibleSlugs ]
	);

	// Pinning a row settles the panel on it; a hover preview in progress ends.
	useEffect( () => {
		if ( pinned ) {
			clearHover();
		}
	}, [ pinned, clearHover ] );

	// The help sidebar's core-style pull tab. The persisted preference is the
	// resting state; a pinned card always shows the panel (its sub-settings
	// live there), and closing the tab unpins.
	const [ helpPref, setHelpPref ] = useHelpPreference( ft.screen );
	const helpPanelId = `blocklane-pro-help-panel-${ ft.screen }`;
	const helpOpen = helpPref || !! pinned;
	const toggleHelp = useCallback( () => {
		if ( helpOpen ) {
			setHelpPref( false );
			clearHover();
			unpin();
		} else {
			setHelpPref( true );
		}
	}, [ helpOpen, setHelpPref, clearHover, unpin ] );

	// Enable/Disable All acts on the tab it sits in (sub-settings are left
	// alone). Environment-forced toggles are already on and can't change, so
	// they count as on for the button label and are skipped in the patches.
	// The page reloads after the final save only when a toggle that actually
	// flips is chrome-changing — the wp-admin sidebar has to redraw for those
	// and for nothing else. Each store is saved only when its patch has rows.
	const allOn = loaded && ft.allOn;
	const toggleAll = useCallback( () => {
		const next = ! allOn;
		setError( '' );
		const advPatch = {};
		const secPatch = {};
		let chromeChanges = false;
		tabFeatures.forEach( ( f ) => {
			if ( isLocked( f ) ) {
				return;
			}
			if ( isOn( f ) !== next && CHROME_CHANGING.includes( f.slug ) ) {
				chromeChanges = true;
			}
			if ( 'security' === f.store ) {
				secPatch[ f.slug ] = next;
			} else {
				advPatch[ f.slug ] = next;
			}
		} );
		if ( chromeChanges ) {
			reloadAfterSave.current = true;
		}
		// The snackbar says what the action DID (#1000): "All" only when no
		// row was skipped; otherwise the count that changed, then one
		// sentence per skipped kind — the rows that belong to Blocklane Pro,
		// the rows the server configuration enforces — `_n()` on each count
		// and no combinatorial strings (D6).
		const proSkipped = tabFeatures.filter( ( f ) => isProRow( f ) ).length;
		const forcedSkipped = tabFeatures.filter(
			( f ) => ! isProRow( f ) && isForced( f )
		).length;
		const changed = tabFeatures.length - proSkipped - forcedSkipped;
		let message;
		if ( 0 === proSkipped && 0 === forcedSkipped ) {
			message = next
				? sprintf(
						/* translators: %s: the tab (category) name. */
						__( 'All %s features enabled', 'blocklane' ),
						tabLabel
					)
				: sprintf(
						/* translators: %s: the tab (category) name. */
						__( 'All %s features disabled', 'blocklane' ),
						tabLabel
					);
		} else {
			message = next
				? sprintf(
						/* translators: 1: the number of features enabled, 2: the tab (category) name. */
						_n(
							'%1$d %2$s feature enabled.',
							'%1$d %2$s features enabled.',
							changed,
							'blocklane'
						),
						changed,
						tabLabel
					)
				: sprintf(
						/* translators: 1: the number of features disabled, 2: the tab (category) name. */
						_n(
							'%1$d %2$s feature disabled.',
							'%1$d %2$s features disabled.',
							changed,
							'blocklane'
						),
						changed,
						tabLabel
					);
			if ( proSkipped ) {
				message +=
					' ' +
					sprintf(
						/* translators: %d: the number of rows on the tab that belong to Blocklane Pro and were not changed. */
						_n(
							'%d is part of Blocklane Pro.',
							'%d are part of Blocklane Pro.',
							proSkipped,
							'blocklane'
						),
						proSkipped
					);
			}
			if ( forcedSkipped ) {
				message +=
					' ' +
					sprintf(
						/* translators: %d: the number of rows on the tab the server configuration enforces on and that were not changed. */
						_n(
							'%d is enforced by your server configuration.',
							'%d are enforced by your server configuration.',
							forcedSkipped,
							'blocklane'
						),
						forcedSkipped
					);
			}
		}
		if ( Object.keys( secPatch ).length ) {
			secRef.current = { ...( secRef.current || {} ), ...secPatch };
			setSecSettings( secRef.current );
			flushSecurity();
		}
		if ( Object.keys( advPatch ).length ) {
			persist( advPatch, message );
		} else {
			setSnackbar( message );
		}
	}, [
		allOn,
		tabFeatures,
		tabLabel,
		isLocked,
		isForced,
		isOn,
		flushSecurity,
		persist,
	] );

	const selectedTypes =
		( settings && settings[ 'reorder-post-types' ] ) || [];
	// The pinned card owns the panel; hover only previews while nothing is
	// pinned — otherwise drifting across the list would blank a pinned
	// card's sub-settings mid-edit. Hover is also inert while the sidebar
	// is closed (it must not yank the panel open).
	const hoverEnabled = helpOpen && ! pinned;
	const detailSlug = pinned || ( hoverEnabled ? hovered : null );
	const detailFeature = detailSlug ? FEATURE_BY_SLUG.get( detailSlug ) : null;

	// Extra controls shown in a feature's detail panel (once it's enabled).
	const renderDetailExtras = ( slug ) => {
		if ( 'limit-revisions' === slug ) {
			return (
				<div className="blocklane-pro-advanced__detail-setting">
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Revisions to keep', 'blocklane' ) }
						min={ 0 }
						max={ 100 }
						value={ settings[ 'revisions-to-keep' ] }
						onChange={ ( v ) =>
							persist( {
								'revisions-to-keep':
									typeof v === 'number' ? v : 0,
							} )
						}
					/>
				</div>
			);
		}
		if ( 'limit-heartbeat' === slug ) {
			return (
				<div className="blocklane-pro-advanced__detail-setting">
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Heartbeat interval (seconds)',
							'blocklane'
						) }
						min={ 15 }
						max={ 100 }
						step={ 5 }
						value={ settings[ 'heartbeat-interval' ] }
						onChange={ ( v ) =>
							persist( {
								'heartbeat-interval':
									typeof v === 'number' ? v : 60,
							} )
						}
					/>
				</div>
			);
		}
		if ( 'reorder-content' === slug ) {
			return (
				<div className="blocklane-pro-advanced__detail-setting">
					{ /* On with nothing selected does nothing — say so, or the feature looks broken. */ }
					{ selectedTypes.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Reorder Content is on, but it won’t do anything until you choose at least one post type below.',
								'blocklane'
							) }
						</Notice>
					) }
					<p className="blocklane-pro-advanced__sublabel">
						{ __( 'Enable manual ordering for:', 'blocklane' ) }
					</p>
					{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics when list-style:none, so restore the role explicitly. */ }
					<ul
						className="blocklane-pro-advanced__option-list"
						role="list"
					>
						{ postTypes.map( ( pt ) => (
							<li key={ pt.slug }>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ pt.label }
									checked={ selectedTypes.includes(
										pt.slug
									) }
									onChange={ ( checked ) =>
										togglePostType( pt.slug, checked )
									}
								/>
							</li>
						) ) }
					</ul>
				</div>
			);
		}
		return null;
	};

	return (
		<div className="blocklane-pro-page">
			<ScreenHeader screen={ ft.screen } pageStart={ pageStart } />
			<ScreenTabs
				screen={ ft.screen }
				label={ __( 'Advanced categories', 'blocklane' ) }
				tabs={ CATEGORIES }
				shell={ ft.shell }
			/>

			<Flex
				align="stretch"
				gap="0"
				className={
					helpOpen
						? 'blocklane-pro-extensions'
						: 'blocklane-pro-extensions is-help-closed'
				}
			>
				<FlexItem className="blocklane-pro-extensions__sidebar">
					<ScreenTabPanel
						screen={ ft.screen }
						slug={ tab }
						className="blocklane-pro-extensions__section"
					>
						{ /* Disabled when nothing on the tab can change: not
						     loaded, or every row environment-forced. */ }
						<FeatureToolbar
							allOn={ allOn }
							onToggleAll={ toggleAll }
							disabled={
								! loaded ||
								! tabFeatures.some( ( f ) => ! isLocked( f ) )
							}
							search={ search }
							onSearch={ setSearch }
							searchLabel={ __( 'Search features', 'blocklane' ) }
							searchPlaceholder={ __( 'Search…', 'blocklane' ) }
						/>

						{ error ? (
							<p
								className="blocklane-pro-extensions__error"
								role="alert"
							>
								{ error }
							</p>
						) : null }

						<div
							className="blocklane-pro-extensions__fields"
							onMouseLeave={ clearHover }
						>
							{ ! loaded && (
								<div className="blocklane-pro-loading">
									<Spinner />
								</div>
							) }
							{ loaded && filtered.length === 0 && (
								<p className="blocklane-pro-feature-list__empty">
									{ search.trim()
										? __(
												'No features in this tab match your search.',
												'blocklane'
											)
										: __(
												'No features in this tab.',
												'blocklane'
											) }
								</p>
							) }
							{ loaded && filtered.length > 0 && (
								<>
									{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari/VoiceOver drops list semantics when list-style:none, so restore the role explicitly. */ }
									<ul
										className="blocklane-pro-feature-list"
										role="list"
									>
										{ filtered.map( ( f ) => (
											<FeatureItem
												key={ f.slug }
												title={ f.title }
												description={ f.short }
												checked={ isOn( f ) }
												disabled={ isForced( f ) }
												pro={ isProRow( f ) }
												badge={ badgeOf( f ) }
												isActive={ pinned === f.slug }
												itemKey={ f.slug }
												onChange={ ( v ) =>
													toggle( f, v )
												}
												onLearnMore={ pin }
												onOpen={ open }
												onHover={
													hoverEnabled
														? previewOnHover
														: undefined
												}
											/>
										) ) }
									</ul>
								</>
							) }
						</div>
					</ScreenTabPanel>
				</FlexItem>

				<HelpTab
					isOpen={ helpOpen }
					onToggle={ toggleHelp }
					panelId={ helpPanelId }
				/>
				<FlexItem
					className="blocklane-pro-extensions__preview"
					id={ helpPanelId }
					aria-hidden={ ! helpOpen }
				>
					<section>
						{ detailFeature ? (
							<div className="blocklane-pro-extensions__preview-content">
								{ /* No close X: Escape, re-clicking the pinned
								     row, and the drawer handle all dismiss —
								     a third X crowded the toggle row. */ }
								{ isProRow( detailFeature ) ? (
									<ProDetailHeading
										feature={ detailFeature }
									/>
								) : (
									<ToggleControl
										label={ detailFeature.title }
										checked={ isOn( detailFeature ) }
										disabled={ isForced( detailFeature ) }
										onChange={ ( v ) =>
											toggle( detailFeature, v )
										}
										className="blocklane-pro-extensions__detail-toggle"
										__nextHasNoMarginBottom
									/>
								) }
								{ isForced( detailFeature ) ? (
									<p className="blocklane-pro-extensions__detail-note">
										{ __(
											'Enforced by your server configuration (wp-config.php) and can’t be changed here.',
											'blocklane'
										) }
									</p>
								) : null }
								<div className="blocklane-pro-extensions__detail-desc">
									<FeatureDescription
										description={
											detailFeature.description
										}
									/>
									{ /* Sub-settings only when pinned, not on a hover-peek. */ }
									{ detailSlug === pinned &&
									isOn( detailFeature )
										? renderDetailExtras(
												detailFeature.slug
											)
										: null }
								</div>
							</div>
						) : (
							<div className="blocklane-pro-extensions__preview-content">
								<p>
									<strong>
										{ __( 'Overview', 'blocklane' ) }
									</strong>
								</p>
								<p>
									{ __(
										'Each of these shows up where you’d expect in WordPress — a Users column, a Clone or Sort-by-Order action on list tables, the media uploader, and a Replace action in the media library.',
										'blocklane'
									) }
								</p>
								<Divider />
								<p>
									{ __(
										'Turn one on, then click it again to configure any extra options. The hardening switches are safe to leave on — where your host already covers one, the toggle simply does nothing.',
										'blocklane'
									) }
								</p>
							</div>
						) }
					</section>
				</FlexItem>

				{ snackbar ? (
					<Snackbar
						onRemove={ () => setSnackbar( '' ) }
						actions={ [] }
						className="blocklane-pro-extensions__snackbar"
					>
						{ snackbar }
					</Snackbar>
				) : null }
			</Flex>
		</div>
	);
};
