/**
 * useSerialWrites — an in-flight guard for a loop of dependent writes
 * (Extensions' Enable/Disable All: one REST write per row, in order).
 *
 * `run( items, write )` refuses to start while a loop is in flight: it
 * returns null synchronously, before any await, so a second click cannot
 * start a second loop interleaved with the first (the optimistic flip of
 * the first click would have sent the second the OPPOSITE way). Otherwise
 * it sets `busy`, awaits `write( item )` for each item in order, and
 * resolves with the last result. A throw stops the loop at that item,
 * clears `busy`, and rejects with the same error — the caller decides what
 * to do (Extensions refetches). null vs Promise is a type distinction, not
 * a sentinel of the success type: `if ( ! loop ) return;` is the guard.
 *
 * A screen whose save is one whole-object write per store with its own
 * dirty-flag re-flush (Advanced) does not need this — a row toggled during
 * its Enable All merges into the store ref and is re-sent.
 *
 * Unmount mid-loop: the remaining writes still go out (a fetch cannot be
 * cancelled) and the trailing setBusy is a no-op.
 */
import { useState, useRef, useCallback } from '@wordpress/element';

/**
 * @return {{busy: boolean, run: Function}} busy, and run( items, write ).
 */
export function useSerialWrites() {
	const busyRef = useRef( false );
	const [ busy, setBusy ] = useState( false );

	const run = useCallback( ( items, write ) => {
		if ( busyRef.current ) {
			return null;
		}
		busyRef.current = true;
		setBusy( true );
		return ( async () => {
			try {
				let last = null;
				for ( const item of items ) {
					last = await write( item );
				}
				return last;
			} finally {
				busyRef.current = false;
				setBusy( false );
			}
		} )();
	}, [] );

	return { busy, run };
}
