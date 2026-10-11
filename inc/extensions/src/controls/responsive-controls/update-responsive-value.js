/**
 * Sets or clears one property's value at one breakpoint in a block's
 * responsive bag, and hands the resulting bag to the block in one call.
 *
 * The bag is the `blocklaneProResponsive` attribute: an object that maps a
 * property name to a breakpoint map, which in turn maps a breakpoint name to
 * the value stored for that property at that breakpoint. A value of
 * `undefined`, `null` or `''` clears the breakpoint; any other value is
 * stored as given. A breakpoint map left with no breakpoints is removed from
 * the bag, and a bag left with no properties is passed on as `{}`. Nothing
 * the function receives is changed: the next bag, and the changed property's
 * breakpoint map, are always new objects.
 *
 * @param {Object}                 attributes    The block's current attributes.
 * @param {(next: Object) => void} setAttributes The block's attribute setter; it is called
 *                                               exactly once with `{ blocklaneProResponsive }`.
 * @param {string}                 property      The property name to change.
 * @param {string}                 breakpoint    The breakpoint name to change.
 * @param {unknown}                [value]       The value to store, or `undefined`, `null`
 *                                               or `''` to clear the breakpoint.
 * @return {undefined} Nothing; the result goes to `setAttributes`.
 */
export function updateResponsiveValue(
	attributes,
	setAttributes,
	property,
	breakpoint,
	value
) {
	/* Reads an own entry of an object, treating a missing or null one as empty. */
	const ownOrEmpty = ( source, key ) =>
		source !== undefined &&
		source !== null &&
		Object.hasOwn( source, key ) &&
		source[ key ] !== undefined &&
		source[ key ] !== null
			? source[ key ]
			: {};

	const currentBag = ownOrEmpty( attributes, 'blocklaneProResponsive' );
	const currentMap = ownOrEmpty( currentBag, property );
	const clears = value === undefined || value === null || value === '';

	/* Copy the bag without the property being changed. */
	const nextBag = { ...currentBag };
	delete nextBag[ property ];

	/* Copy the property's breakpoint map, then store or clear the breakpoint. */
	const nextMap = { ...currentMap };
	if ( clears ) {
		delete nextMap[ breakpoint ];
	} else {
		Object.defineProperty( nextMap, breakpoint, {
			value,
			writable: true,
			enumerable: true,
			configurable: true,
		} );
	}

	/* Keep the property only while its breakpoint map holds something. */
	if ( Object.keys( nextMap ).length > 0 ) {
		Object.defineProperty( nextBag, property, {
			value: nextMap,
			writable: true,
			enumerable: true,
			configurable: true,
		} );
	}

	setAttributes( { blocklaneProResponsive: nextBag } );
}
