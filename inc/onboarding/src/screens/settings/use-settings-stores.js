/**
 * useSettingsStores — THE read and write door over the Settings screen's
 * three stores (the C-settings spec (2026-10-12) §3.5; Amendment 1
 * F4, F5, F8). Nothing else in the screen reads or writes a store.
 *
 * The stores, as the server keeps them:
 *   advanced   one option row, `/advanced` (GET { settings, postTypes };
 *              POST { settings } with any subset of keys);
 *   security   one option row, `/security` (GET/POST { settings, forced });
 *   extensions one option row per slug, `/extensions` (GET { enabled };
 *              POST { slug, enabled } → { enabled }).
 *
 * The rules every write keeps:
 * - A payload carries only the keys the action changed (F4: a flip sends one
 *   key), and every one of them is a key the store's last GET handed out
 *   (ST5). Anything else throws NotWritable before a request is made, so a
 *   free build can never send a Pro key: the server's views (Advanced::known,
 *   Security::known) keep those keys out of the GET.
 * - The confirmed state is the RESPONSE, never the request (F5): a 200 whose
 *   body does not carry what was sent is a refusal.
 * - An object store has one save in flight; a change made meanwhile is
 *   merged and sent alone when the save lands (ST6). A refusal reverts that
 *   store to its last confirmed state at once, drops what was queued behind
 *   it, cancels a pending reload, raises the save error and calls onRefusal
 *   (the screen clears its toast there) (ST3, ST4, ST13).
 * - The key store flips one slug optimistically and reverts that slug on a
 *   refusal. A bulk change on it is a serial loop: one loop at a time, every
 *   extension write refused while it runs (ST10), and a failure stops the
 *   loop and reads the server's truth again (ST8).
 * - A bulk change writes only writable rows: never a Pro row, an enforced
 *   row, or a row marked `bulk: false` (F8: one whose ON widens what others
 *   may do or what Delete destroys); each skip is counted (ST9).
 * - A change to a row with a `screen` or `chrome` reloads the page once every
 *   store is idle and nothing was refused (ST12): wp-admin's menu is drawn by
 *   the server.
 *
 * The REST client is the module's own (api/client.js); `reload` and
 * `onRefusal` are the screen's.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useReducer, useRef } from '@wordpress/element';

import {
	advanced as advancedApi,
	security as securityApi,
	extensions as extensionsApi,
} from '../../api/client';
import { errorMessage } from '../../api/errors';
import { isProRow } from '../pro-row';

/** A write the door refuses before any request: a programming error. */
export class NotWritable extends Error {
	/**
	 * @param {string} why What was not writable.
	 */
	constructor( why ) {
		super( why );
		this.name = 'NotWritable';
	}
}

/** The stores, in the order a write over several goes out. */
export const STORE_IDS = Object.freeze( [
	'advanced',
	'security',
	'extensions',
] );

const OBJECT_APIS = { advanced: advancedApi, security: securityApi };

/** A response that does not carry what was sent (F5). */
class NotKept extends Error {}

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

const fresh = () => ( {
	status: 'loading',
	values: null,
	confirmed: null,
	keys: new Set(),
	forced: {},
	inflight: null,
	queued: null,
	pending: {},
	loop: false,
} );

/**
 * @param {Object}     [options]
 * @param {() => void} [options.onRefusal] Called once per refused write.
 * @param {() => void} [options.reload]    Reloads the page (the default does).
 * @return {Object} The door; see the docblock above.
 */
export function useSettingsStores( { onRefusal, reload } = {} ) {
	const [ , rerender ] = useReducer( ( n ) => n + 1, 0 );
	const state = useRef( null );
	if ( ! state.current ) {
		state.current = {
			stores: Object.fromEntries(
				STORE_IDS.map( ( id ) => [ id, fresh() ] )
			),
			postTypes: [],
			saveError: null,
			reloadWanted: false,
		};
	}
	const hooks = useRef( {} );
	hooks.current = {
		onRefusal: onRefusal || ( () => {} ),
		reload: reload || ( () => window.location.reload() ),
	};
	const S = state.current;
	const store = ( id ) => S.stores[ id ];

	// ---- reads ------------------------------------------------------------

	const load = useCallback( ( id ) => {
		const st = state.current.stores[ id ];
		st.status = 'loading';
		const read =
			'extensions' === id
				? extensionsApi.list()
				: OBJECT_APIS[ id ].get();
		return read.then(
			( res ) => {
				if ( 'extensions' === id ) {
					st.values = res?.enabled || {};
				} else {
					st.values = res?.settings || {};
					st.confirmed = st.values;
					if ( 'advanced' === id ) {
						state.current.postTypes = res?.postTypes || [];
					}
					if ( 'security' === id ) {
						st.forced = res?.forced || {};
					}
				}
				st.keys = new Set( Object.keys( st.values ) );
				st.status = 'ready';
				rerender();
			},
			( error ) => {
				st.status = { failed: error };
				rerender();
			}
		);
	}, [] );

	useEffect( () => {
		STORE_IDS.forEach( load );
	}, [ load ] );

	const isForced = ( row ) =>
		'security' === row.store &&
		Boolean( store( 'security' ).forced[ row.slug ] );

	const isOn = ( row ) => {
		if ( isProRow( row ) ) {
			return false;
		}
		if ( isForced( row ) ) {
			return true;
		}
		return Boolean( store( row.store )?.values?.[ row.slug ] );
	};

	const kindOf = ( row ) => {
		if ( isProRow( row ) ) {
			return 'pro';
		}
		return isForced( row ) ? 'enforced' : 'live';
	};

	/**
	 * THE predicate for "this row may be written": not Pro, not enforced, and
	 * in a bulk change not a row marked `bulk: false`.
	 *
	 * @param {Object}  row            The row.
	 * @param {Object}  [options]
	 * @param {boolean} [options.bulk] Whether the write is a bulk change.
	 * @return {boolean} Whether it may be written.
	 */
	const writable = ( row, { bulk = false } = {} ) =>
		! isProRow( row ) &&
		! isForced( row ) &&
		! ( bulk && false === row.bulk );

	/**
	 * A tab's load state, from the stores its rows read (Pro rows read none).
	 *
	 * @param {Array} rows The tab's rows.
	 * @return {string|Object} 'loading' | 'ready' | { failed: storeIds, detail }:
	 *                         `detail` is the first failed store's server
	 *                         message, the load error's second line (SD13).
	 */
	const tabStatus = ( rows ) => {
		const ids = [
			...new Set(
				rows.filter( ( r ) => ! isProRow( r ) ).map( ( r ) => r.store )
			),
		];
		const failed = ids.filter( ( id ) => store( id ).status?.failed );
		if ( failed.length ) {
			return {
				failed,
				detail: errorMessage( store( failed[ 0 ] ).status.failed ),
			};
		}
		return ids.some( ( id ) => 'loading' === store( id ).status )
			? 'loading'
			: 'ready';
	};

	// ---- the outcome of a write ------------------------------------------

	const idle = () =>
		STORE_IDS.every( ( id ) => {
			const st = store( id );
			return (
				! st.inflight &&
				! st.queued &&
				! st.loop &&
				! Object.keys( st.pending ).length
			);
		} );

	const maybeReload = () => {
		if ( S.reloadWanted && idle() ) {
			S.reloadWanted = false;
			hooks.current.reload();
		}
	};

	const refused = ( error ) => {
		S.saveError = { detail: errorMessage( error ) };
		S.reloadWanted = false;
		hooks.current.onRefusal( error );
	};

	const accepted = () => {
		S.saveError = null;
	};

	// ---- the object stores ------------------------------------------------

	const send = ( id, payload ) => {
		const st = store( id );
		const flight = OBJECT_APIS[ id ]
			.save( payload )
			.then( ( res ) => {
				const kept = res?.settings;
				const lost = Object.keys( payload ).find(
					( k ) => ! kept || ! same( kept[ k ], payload[ k ] )
				);
				if ( lost ) {
					throw new NotKept(
						sprintf(
							/* translators: %s: a setting's key. */
							__( 'The server did not keep "%s".', 'blocklane' ),
							lost
						)
					);
				}
				st.confirmed = kept;
				if ( res.forced ) {
					st.forced = res.forced;
				}
				st.inflight = null;
				accepted();
				if ( st.queued ) {
					const next = st.queued;
					st.queued = null;
					st.values = { ...kept, ...next };
					rerender();
					return send( id, next );
				}
				st.values = kept;
				rerender();
				maybeReload();
			} )
			.catch( ( error ) => {
				st.inflight = null;
				st.queued = null;
				st.values = st.confirmed;
				refused( error );
				rerender();
			} );
		st.inflight = flight;
		return flight;
	};

	const assertKeys = ( id, keys ) => {
		const st = store( id );
		const stray = keys.filter( ( k ) => ! st.keys.has( k ) );
		if ( 'ready' !== st.status || stray.length ) {
			throw new NotWritable(
				`the ${ id } store did not hand out ${
					stray.join( ', ' ) || 'anything yet'
				}`
			);
		}
	};

	const writeObject = ( id, patch ) => {
		const st = store( id );
		st.values = { ...st.values, ...patch };
		if ( st.inflight ) {
			st.queued = { ...( st.queued || {} ), ...patch };
			rerender();
			return st.inflight;
		}
		rerender();
		return send( id, patch );
	};

	// ---- the key store ----------------------------------------------------

	const toggleOne = ( slug, next ) => {
		const st = store( 'extensions' );
		const previous = st.values[ slug ];
		st.values = { ...st.values, [ slug ]: next };
		st.pending = { ...st.pending, [ slug ]: true };
		rerender();
		return extensionsApi
			.toggle( slug, next )
			.then( ( res ) => {
				if ( ! res?.enabled || res.enabled[ slug ] !== next ) {
					throw new NotKept(
						sprintf(
							/* translators: %s: a setting's key. */
							__( 'The server did not keep "%s".', 'blocklane' ),
							slug
						)
					);
				}
				st.values = res.enabled;
				accepted();
			} )
			.catch( ( error ) => {
				st.values = { ...st.values, [ slug ]: previous };
				refused( error );
			} )
			.finally( () => {
				const rest = { ...st.pending };
				delete rest[ slug ];
				st.pending = rest;
				rerender();
				maybeReload();
			} );
	};

	const loopAll = async ( slugs, next ) => {
		const st = store( 'extensions' );
		st.loop = true;
		st.values = {
			...st.values,
			...Object.fromEntries( slugs.map( ( s ) => [ s, next ] ) ),
		};
		rerender();
		try {
			for ( const slug of slugs ) {
				const res = await extensionsApi.toggle( slug, next );
				if ( ! res?.enabled || res.enabled[ slug ] !== next ) {
					throw new NotKept(
						sprintf(
							/* translators: %s: a setting's key. */
							__( 'The server did not keep "%s".', 'blocklane' ),
							slug
						)
					);
				}
				st.values = { ...res.enabled, ...st.values };
			}
			accepted();
		} catch ( error ) {
			refused( error );
			st.loop = false;
			await load( 'extensions' );
		} finally {
			st.loop = false;
			rerender();
			maybeReload();
		}
	};

	// ---- the one write ----------------------------------------------------

	/**
	 * Set rows ON or OFF: one row (a switch) or a tab (bulk).
	 *
	 * @param {Array}   rows           The rows.
	 * @param {boolean} next           ON or OFF.
	 * @param {Object}  [options]
	 * @param {boolean} [options.bulk] A bulk change: skip what may not be written.
	 * @return {{counts: Object, done: Promise}|null} What it did, or null when
	 *         an Extensions loop is running and refuses the change.
	 */
	const setRows = ( rows, next, { bulk = false } = {} ) => {
		if (
			store( 'extensions' ).loop &&
			rows.some( ( r ) => 'extensions' === r.store )
		) {
			return null;
		}
		const counts = { changed: 0, skipped: { pro: 0, forced: 0, solo: 0 } };
		const targets = [];
		for ( const row of rows ) {
			if ( ! bulk && ! writable( row ) ) {
				throw new NotWritable(
					`the row "${ row.slug }" is not writable`
				);
			}
			if ( isProRow( row ) ) {
				counts.skipped.pro++;
			} else if ( isForced( row ) ) {
				counts.skipped.forced++;
			} else if ( bulk && false === row.bulk ) {
				counts.skipped.solo++;
			} else if ( isOn( row ) !== next ) {
				targets.push( row );
			}
		}
		counts.changed = targets.length;
		const patches = { advanced: {}, security: {} };
		const slugs = [];
		for ( const row of targets ) {
			if ( 'extensions' === row.store ) {
				slugs.push( row.slug );
			} else {
				patches[ row.store ][ row.slug ] = next;
			}
		}
		// Every key is judged before anything is sent (ST5, ST11).
		for ( const id of [ 'advanced', 'security' ] ) {
			assertKeys( id, Object.keys( patches[ id ] ) );
		}
		if ( slugs.length ) {
			assertKeys( 'extensions', slugs );
		}
		if ( targets.some( ( r ) => r.screen || r.chrome ) ) {
			S.reloadWanted = true;
		}
		const flights = [ 'advanced', 'security' ]
			.filter( ( id ) => Object.keys( patches[ id ] ).length )
			.map( ( id ) => writeObject( id, patches[ id ] ) );
		if ( slugs.length ) {
			flights.push(
				bulk ? loopAll( slugs, next ) : toggleOne( slugs[ 0 ], next )
			);
		}
		return { counts, done: Promise.all( flights ) };
	};

	/**
	 * An advanced sub-setting (revisions to keep, heartbeat interval, the
	 * reorder post types), read and written through the same object writer.
	 *
	 * @param {string} key The key.
	 * @return {boolean|number|string[]|undefined} Its value.
	 */
	const sub = ( key ) => store( 'advanced' ).values?.[ key ];

	const setSub = ( key, value ) => {
		assertKeys( 'advanced', [ key ] );
		return writeObject( 'advanced', { [ key ]: value } );
	};

	return {
		status: ( id ) => store( id ).status,
		tabStatus,
		isOn,
		isForced,
		kindOf,
		writable,
		setRows,
		sub,
		setSub,
		saveError: S.saveError,
		extra: { postTypes: S.postTypes },
		/**
		 * Whether a row's store lists it: the server's extension map names
		 * every extension this build offers; a Pro row is always listed.
		 *
		 * @param {Object} row The row.
		 * @return {boolean} Whether to list it.
		 */
		isListed: ( row ) =>
			isProRow( row ) ||
			'extensions' !== row.store ||
			( Boolean( store( 'extensions' ).values ) &&
				row.slug in store( 'extensions' ).values ),
		busy: ( row ) =>
			'extensions' === row.store &&
			( store( 'extensions' ).loop ||
				Boolean( store( 'extensions' ).pending[ row.slug ] ) ),
		loopBusy: ( id ) => Boolean( store( id ).loop ),
		retry: ( ids ) => Promise.all( ids.map( load ) ),
	};
}
