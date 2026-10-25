/**
 * Home slots — ONE ENTRY PER LINE, deliberately.
 *
 * Same line grammar as screens/components.js, and filtered by the same pass in
 * bin/generate-edition.php: a slot whose unit this edition does not carry has
 * no line here, so webpack never reaches the import and the chunk is never
 * emitted. HomeScreen branches on SLOT PRESENCE — the filtered file IS the
 * single source of truth — and never on an edition flag, because a flag in
 * front of present code leaves the code present.
 *
 * Adding a Home slot means a file under screens/home/, a line here, and the
 * slot's key in its unit's manifest `slots` list.
 */

import { lazy, Suspense } from '@wordpress/element';

/* Slot components are named exports, and every slot is code-split: a lazy
   import is what lets the generator remove the module by removing its line
   (a static import would survive the filter and webpack would still bundle
   it). Each slot carries its own Suspense so a slow chunk suspends the slot
   alone and not the whole screen; the fallback is empty because a slot is an
   optional piece of a page that is already painted. */
const lazySlot = ( importer, name ) => {
	const Lazy = lazy( () =>
		importer().then( ( m ) => ( { default: m[ name ] } ) )
	);
	const Slot = ( props ) => (
		<Suspense fallback={ null }>
			<Lazy { ...props } />
		</Suspense>
	);
	Slot.displayName = name;
	return Slot;
};

export const SLOTS = {
};
