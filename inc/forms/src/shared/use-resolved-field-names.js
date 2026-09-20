/**
 * Resolved field names, from the server.
 *
 * The submit name a field gets is decided by
 * blocklane_pro_forms_field_name() in inc/forms/runtime.php — it names the
 * rendered input AND the schema key the server validates against. The editor
 * used to re-derive it with a hand-maintained JS twin built from different
 * primitives (cleanForSlug vs sanitize_title), which disagreed on every
 * non-Latin label and on German: 名前 became %e5%90%8d%e5%89%8d on the server
 * and 名前 in the editor, so a Visibility rule authored here referenced a
 * field the schema does not have.
 *
 * There is no copy now. The editor asks, which is the house rule for values
 * both sides need: defined once, in PHP.
 *
 * Asking costs a round trip, so results are cached by request shape and shared
 * process-wide — the five field blocks showing their own name and the form
 * block resolving its whole list all hit the same cache. Until the first
 * answer arrives a row resolves to '', which every consumer already treats as
 * "not known yet" (the pickers filter falsy names, the heal skips them).
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Shape key -> resolved names. Module scope so every block shares it: a form
// with twenty fields asks once, not twenty-one times.
const cache = new Map();
const inflight = new Map();

const keyFor = ( rows ) => JSON.stringify( rows );

const resolve = ( rows ) => {
	const key = keyFor( rows );
	if ( cache.has( key ) ) {
		return Promise.resolve( cache.get( key ) );
	}
	if ( inflight.has( key ) ) {
		return inflight.get( key );
	}

	const request = apiFetch( {
		path: '/blocklane-pro/v1/forms/field-names',
		method: 'POST',
		data: { fields: rows },
	} )
		.then( ( response ) => {
			const names = Array.isArray( response?.names )
				? response.names
				: [];
			cache.set( key, names );
			inflight.delete( key );
			return names;
		} )
		.catch( () => {
			// A failed resolve must never invent a name: a wrong one is worse
			// than none, because it would be stored into a condition and point
			// at a field the schema does not have. Empty means "unknown", and
			// nothing is cached so the next render retries.
			inflight.delete( key );
			return [];
		} );

	inflight.set( key, request );
	return request;
};

/**
 * @param {Array<{name: string, label: string, fallback?: string}>} rows Field
 *                                                                       inputs,
 *                                                                       in
 *                                                                       order.
 * @return {string[]} Resolved names, same order; empty until the first answer.
 */
export default function useResolvedFieldNames( rows ) {
	const key = keyFor( rows );
	const [ names, setNames ] = useState( () => cache.get( key ) || [] );
	const latest = useRef( key );

	useEffect( () => {
		latest.current = key;
		const cached = cache.get( key );
		if ( cached ) {
			setNames( cached );
			return;
		}
		let cancelled = false;
		resolve( JSON.parse( key ) ).then( ( resolved ) => {
			// Ignore an answer for a shape the caller has already moved on
			// from — typing a label fires several of these in a row.
			if ( ! cancelled && latest.current === key ) {
				setNames( resolved );
			}
		} );
		return () => {
			cancelled = true;
		};
	}, [ key ] );

	return names;
}

/**
 * Single-field convenience — the five field blocks each show their own name.
 *
 * @param {Object} attributes         Block attributes.
 * @param {string} [attributes.name]  Explicit name override.
 * @param {string} [attributes.label] Field label.
 * @param {string} [fallback]         Name used when both are empty.
 * @return {string} Resolved name, '' until the first answer.
 */
export function useResolvedFieldName( { name, label }, fallback = 'field' ) {
	const rows = [ { name: name || '', label: label || '', fallback } ];

	return useResolvedFieldNames( rows )[ 0 ] || '';
}
