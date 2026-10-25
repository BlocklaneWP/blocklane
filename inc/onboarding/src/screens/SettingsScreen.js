/**
 * Settings — the one screen where Extensions and Advanced used to be (spec
 * the C-settings spec (2026-10-12); design spec Amendments F, G, D/E).
 *
 * Composition only. The facts live elsewhere, each once:
 *   screens/meta/settings.js        tabs, sections, nouns, the place name;
 *   screens/settings/layout.js      LAYOUT: every row's tab, section, order;
 *   screens/settings/rows.js        the row index (one line per row; the
 *                                   generator turns an absent unit's line
 *                                   into a Pro row);
 *   screens/settings/rows/<slug>.js a row's copy, store, About and options;
 *   screens/settings/overview.js    the screen's own About;
 *   screens/settings/use-settings-stores.js  every read and write;
 *   components/FeatureList, FeatureRow, About, ListToolbar, LoadError.
 *
 * What the screen decides: per tab, loading (a spinner in the content area,
 * the header and tabs stay), failed (LoadError, Retry reloads only the
 * failed stores) or the list (toolbar, the save-error notice, sections with
 * live rows first). One toast path and one save-error path for row writes,
 * bulk writes and options. The details card shows the selected row's About,
 * or the screen's overview when nothing is selected.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';

import {
	BlocklanePage,
	detailsIdOf,
	useDetailsPreference,
	useToasts,
} from '../components/BlocklanePage';
import { FeatureList } from '../components/FeatureList';
import { FeatureRow } from '../components/FeatureRow';
import { About } from '../components/About';
import { ListToolbar } from '../components/ListToolbar';
import { LoadError } from '../components/LoadError';
import { NoticeDetail } from '../components/NoticeDetail';
import { useFeatureTabs } from '../components/use-feature-tabs';
import {
	groupBySection,
	liveFirst,
	liveFirstTab,
} from '../components/feature-tabs';
import { isToolScreenOn } from '../components/tool-screens';
import { routeUrl } from '../router';
import { ENTRIES, requireScreen } from './registry';
import { isProRow } from './pro-row';
import { LAYOUT } from './settings/layout';
import { OVERVIEW } from './settings/overview';
import { useSettingsStores } from './settings/use-settings-stores';
// Every row, one re-export per line — the index the generator rewrites.
// Namespace import: the object is whatever the index exports after the
// rewrite (live rows, proRow() rows, or null for a Pro row the catalog has
// no entry for), and THIS file names no unit.
import * as ROW_MODULES from './settings/rows';

/** The rows this build carries, keyed by each row's own slug. */
export const ROWS = Object.fromEntries(
	Object.values( ROW_MODULES )
		.filter( Boolean )
		.map( ( row ) => [ row.slug, row ] )
);

/** The rows this build lists, in LAYOUT order. */
const PRESENT = LAYOUT.map( ( p ) => p.slug ).filter( ( s ) => s in ROWS );

const textOf = ( slug ) => [ ROWS[ slug ].title, ROWS[ slug ].short ];

const DETAILS_ID = detailsIdOf( 'settings' );

/**
 * The sentences that depend on a tab's noun (meta `noun`): a sentence never
 * composes a noun, so each set is whole.
 */
const NOUN = {
	extensions: {
		search: __( 'Search extensions', 'blocklane' ),
		empty: __( 'No extensions match your search.', 'blocklane' ),
		on: ( n ) =>
			sprintf(
				/* translators: %d: how many extensions were turned on. */
				_n(
					'%d extension turned on.',
					'%d extensions turned on.',
					n,
					'blocklane'
				),
				n
			),
		off: ( n ) =>
			sprintf(
				/* translators: %d: how many extensions were turned off. */
				_n(
					'%d extension turned off.',
					'%d extensions turned off.',
					n,
					'blocklane'
				),
				n
			),
	},
	settings: {
		search: __( 'Search settings', 'blocklane' ),
		empty: __( 'No settings match your search.', 'blocklane' ),
		on: ( n ) =>
			sprintf(
				/* translators: %d: how many settings were turned on. */
				_n(
					'%d setting turned on.',
					'%d settings turned on.',
					n,
					'blocklane'
				),
				n
			),
		off: ( n ) =>
			sprintf(
				/* translators: %d: how many settings were turned off. */
				_n(
					'%d setting turned off.',
					'%d settings turned off.',
					n,
					'blocklane'
				),
				n
			),
	},
};

/**
 * What a bulk change did, in the toast's words (SD2): the count that
 * changed, then one sentence per kind of row it left alone. Rows that belong
 * to Blocklane Pro are not counted aloud (W6).
 *
 * @param {Object}  noun   The tab's sentence set.
 * @param {Object}  counts setRows()'s counts.
 * @param {boolean} next   ON or OFF.
 * @return {string} The toast.
 */
const bulkMessage = ( noun, counts, next ) =>
	[
		next ? noun.on( counts.changed ) : noun.off( counts.changed ),
		counts.skipped.forced
			? sprintf(
					/* translators: %d: how many rows the server configuration sets. */
					_n(
						'%d is set by your server configuration.',
						'%d are set by your server configuration.',
						counts.skipped.forced,
						'blocklane'
					),
					counts.skipped.forced
				)
			: null,
		counts.skipped.solo && next
			? sprintf(
					/* translators: %d: how many rows are only ever turned on one at a time. */
					_n(
						'%d needs to be turned on by itself.',
						'%d need to be turned on by itself.',
						counts.skipped.solo,
						'blocklane'
					),
					counts.skipped.solo
				)
			: null,
		counts.skipped.solo && ! next
			? sprintf(
					/* translators: %d: how many rows are only ever turned off one at a time. */
					_n(
						'%d needs to be turned off by itself.',
						'%d need to be turned off by itself.',
						counts.skipped.solo,
						'blocklane'
					),
					counts.skipped.solo
				)
			: null,
	]
		.filter( Boolean )
		.join( ' ' );

/**
 * The details card's badge for a row: Pro, Enforced, Active (ON); none when
 * a live row is OFF (SD3, the frames).
 *
 * @param {string}  kind The row's kind.
 * @param {boolean} on   Whether it is ON.
 * @return {Object|undefined} { label, status }.
 */
const badgeFor = ( kind, on ) => {
	if ( 'pro' === kind ) {
		return { label: __( 'Pro', 'blocklane' ), status: 'pro' };
	}
	if ( 'enforced' === kind ) {
		return { label: __( 'Enforced', 'blocklane' ), status: 'enforced' };
	}
	return on
		? { label: __( 'Active', 'blocklane' ), status: 'active' }
		: undefined;
};

/**
 * @param {Object} about An About ({ intro, groups, note, learnMoreUrl }).
 * @param {string} title The feature's name, for the link's label.
 * @return {JSX.Element} The About body.
 */
const aboutOf = ( about, title ) => (
	<About
		intro={ about.intro }
		groups={ about.groups }
		note={ about.note }
		learnMore={
			about.learnMoreUrl
				? {
						url: about.learnMoreUrl,
						label: sprintf(
							/* translators: %s: the feature's name. */
							__( 'Learn more about %s', 'blocklane' ),
							title
						),
					}
				: null
		}
	/>
);

export const SettingsScreen = ( { initialItem } = {} ) => {
	const { show: showToast, clear: clearToast, ...toasts } = useToasts();
	const stores = useSettingsStores( { onRefusal: clearToast } );
	const [ entry ] = useState( () => requireScreen( 'settings' ) );

	// SD16: the default tab is derived, once, from what this build lists.
	const [ defaultTab ] = useState( () =>
		'live-first' === entry.tabDefault
			? liveFirstTab( entry.tabs, LAYOUT, PRESENT, ( s ) =>
					isProRow( ROWS[ s ] )
				)
			: undefined
	);

	const ft = useFeatureTabs( {
		screen: 'settings',
		placement: LAYOUT,
		knownSlugs: PRESENT,
		deepLink: initialItem,
		textOf,
		defaultTab,
	} );

	// The details card: the stored preference, or open while a row is
	// selected; closing it unselects (the card's ✕ hands focus back to ?).
	const [ helpPref, setHelpPref ] = useDetailsPreference( 'settings' );
	const helpOpen = helpPref || Boolean( ft.selected );
	const setDetailsOpen = useCallback(
		( next ) => {
			setHelpPref( next );
			if ( ! next ) {
				ft.unselect();
			}
		},
		[ setHelpPref, ft ]
	);

	// An option's own failure (a breakpoint save, a load) joins the one
	// save-error path; the next accepted write of any kind clears it.
	const [ optionError, setOptionError ] = useState( null );
	const [ dismissed, setDismissed ] = useState( null );
	const shownError = stores.saveError || optionError;

	const listed = ( slug ) => stores.isListed( ROWS[ slug ] );
	// The tab's state reads every row placed on it: a row its store has not
	// listed yet (or could not) still decides whether the tab is loading or
	// failed. Only the list itself is narrowed to what the store offers.
	const tabState = stores.tabStatus( ft.tabSlugs.map( ( s ) => ROWS[ s ] ) );
	const tabRows = ft.tabSlugs.filter( listed ).map( ( s ) => ROWS[ s ] );
	const noun = NOUN[ ft.tabMeta.noun ] || NOUN.settings;

	const toggle = ( row, next ) => {
		setOptionError( null );
		stores.setRows( [ row ], next );
		showToast(
			next
				? sprintf(
						/* translators: %s: the feature's name. */
						__( '%s turned on.', 'blocklane' ),
						row.title
					)
				: sprintf(
						/* translators: %s: the feature's name. */
						__( '%s turned off.', 'blocklane' ),
						row.title
					)
		);
	};

	const bulkRows = tabRows.filter( ( r ) =>
		stores.writable( r, { bulk: true } )
	);
	const allOn =
		bulkRows.length > 0 && bulkRows.every( ( r ) => stores.isOn( r ) );
	const runBulk = () => {
		setOptionError( null );
		const next = ! allOn;
		const out = stores.setRows( tabRows, next, { bulk: true } );
		if ( out ) {
			showToast( bulkMessage( noun, out.counts, next ) );
		}
	};

	const optionsContext = useMemo(
		() => ( {
			sub: stores.sub,
			setSub: ( key, value ) => {
				setOptionError( null );
				return stores.setSub( key, value );
			},
			postTypes: stores.extra.postTypes,
			saved: ( message ) => {
				setOptionError( null );
				showToast( message );
			},
			failed: ( detail ) => {
				clearToast();
				setOptionError( { detail } );
			},
		} ),
		[ stores, showToast, clearToast ]
	);

	// A deep-linked row is scrolled into view once its list has rendered.
	// Focus stays on the h1 (BlocklanePage).
	const scrolled = useRef( false );
	useEffect( () => {
		if ( scrolled.current || ! ft.selected || 'ready' !== tabState ) {
			return;
		}
		scrolled.current = true;
		document
			.querySelector( `[data-row="${ ft.selected }"]` )
			?.scrollIntoView?.( { block: 'nearest' } );
	}, [ ft.selected, tabState ] );

	const rowElement = ( slug ) => {
		const row = ROWS[ slug ];
		const kind = stores.kindOf( row );
		const on = stores.isOn( row );
		const screen = row.screen && ENTRIES[ row.screen ];
		const Options = row.options;
		return (
			<FeatureRow
				key={ slug }
				slug={ slug }
				kind={ kind }
				title={ row.title }
				description={ row.short }
				checked={ on }
				busy={ stores.busy( row ) }
				selected={ ft.selected === slug }
				openHref={
					screen && isToolScreenOn( row.screen )
						? routeUrl( { screen: row.screen } )
						: undefined
				}
				openLabel={
					screen
						? sprintf(
								/* translators: %s: a dashboard screen's name. */
								__( 'Open %s', 'blocklane' ),
								screen.label
							)
						: undefined
				}
				reason={ __(
					'Set by your server configuration (wp-config.php), so it can’t be changed here.',
					'blocklane'
				) }
				onToggle={ ( next ) => toggle( row, next ) }
				onSelect={ ft.select }
				detailsId={ DETAILS_ID }
			>
				{ Options ? <Options { ...optionsContext } /> : null }
			</FeatureRow>
		);
	};

	let body;
	if ( 'loading' === tabState ) {
		body = (
			<div className="blocklane-feature-list__loading" data-loading>
				<Spinner />
			</div>
		);
	} else if ( tabState.failed ) {
		body = (
			<LoadError
				message={ __(
					'Blocklane Settings could not be loaded. Your settings are unchanged.',
					'blocklane'
				) }
				detail={ tabState.detail }
				onRetry={ () => stores.retry( tabState.failed ) }
			/>
		);
	} else {
		const visible = ft.visibleSlugs.filter( listed );
		const sections = groupBySection( visible, ft.tabMeta, LAYOUT ).map(
			( section ) => ( {
				slug: section.slug,
				label: section.label,
				rows: liveFirst(
					section.slugs,
					( s ) => ! isProRow( ROWS[ s ] )
				).map( rowElement ),
			} )
		);
		body = (
			<>
				<ListToolbar
					bulkLabel={
						allOn
							? __( 'Disable all', 'blocklane' )
							: __( 'Enable all', 'blocklane' )
					}
					onBulk={ runBulk }
					bulkDisabled={ ! bulkRows.length }
					busy={ stores.loopBusy( 'extensions' ) }
					search={ ft.search }
					onSearch={ ft.setSearch }
					searchLabel={ noun.search }
					searchPlaceholder={ __( 'Search', 'blocklane' ) }
				/>
				{ shownError && shownError !== dismissed ? (
					<div
						className="blocklane-settings__notice"
						data-notice="save-error"
					>
						<Notice
							status="error"
							onRemove={ () => setDismissed( shownError ) }
						>
							<p>
								{ __(
									'Your change could not be saved. Your other settings are unchanged.',
									'blocklane'
								) }
							</p>
							<NoticeDetail>{ shownError.detail }</NoticeDetail>
						</Notice>
					</div>
				) : null }
				<FeatureList sections={ sections } empty={ noun.empty } />
			</>
		);
	}

	const selectedRow = ft.selected ? ROWS[ ft.selected ] : null;
	let details;
	if ( selectedRow ) {
		details = {
			title: sprintf(
				/* translators: %s: the feature's name. */
				__( 'About %s', 'blocklane' ),
				selectedRow.title
			),
			badge: badgeFor(
				stores.kindOf( selectedRow ),
				stores.isOn( selectedRow )
			),
			about: selectedRow.slug,
			content: aboutOf( selectedRow.about, selectedRow.title ),
		};
	} else {
		const loaded = [ 'advanced', 'security', 'extensions' ].every(
			( id ) => 'ready' === stores.status( id )
		);
		const active = PRESENT.filter(
			( s ) =>
				listed( s ) &&
				'live' === stores.kindOf( ROWS[ s ] ) &&
				stores.isOn( ROWS[ s ] )
		).length;
		details = {
			// SD4: the count is hidden while the stores load.
			badge: loaded
				? {
						label: sprintf(
							/* translators: %d: how many features are on. */
							_n( '%d active', '%d active', active, 'blocklane' ),
							active
						),
						status: 'count',
					}
				: undefined,
			content: aboutOf( OVERVIEW, entry.title ),
		};
	}

	return (
		<BlocklanePage
			screen="settings"
			tabs={ {
				label: __( 'Settings sections', 'blocklane' ),
				shell: ft.shell,
			} }
			details={ details }
			detailsOpen={ helpOpen }
			onDetailsOpenChange={ setDetailsOpen }
			toasts={ toasts }
		>
			{ body }
		</BlocklanePage>
	);
};
