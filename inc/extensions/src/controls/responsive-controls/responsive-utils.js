/**
 * The editor half of the move to core 7.1's own formats:
 * migrateResponsiveAttributes() takes the keys core now stores itself out of
 * a block's blocklaneProResponsive attribute and writes them in core's
 * formats. (Writing one breakpoint's value into that attribute lives in
 * update-responsive-value.js.)
 *
 * @package
 */

// ─── Legacy-bag → core 7.1 idiom migration ──────────────────────────────

/**
 * blocklaneProResponsive keys that now live in core formats. `order` and
 * `maxWidth` stay Blocklane extras: core has no flex `order` property and its
 * style engine has no max-width.
 */
const MIGRATED_KEYS = [
	'fontSize',
	'textAlign',
	'padding',
	'margin',
	'blockGap',
	'minHeight',
	'justifyContent',
	'orientation',
	'hidden',
];

// Blocklane stored CSS justify values; core layout attributes use its own
// vocabulary (left/right + center/space-between/stretch).
const JUSTIFY_TO_CORE = {
	'flex-start': 'left',
	center: 'center',
	'flex-end': 'right',
	'space-between': 'space-between',
	stretch: 'stretch',
};

const TEXT_ALIGN_VALUES = [ 'left', 'center', 'right' ];
const ORIENTATION_VALUES = [ 'horizontal', 'vertical' ];

/**
 * Resolve the legacy tablet→mobile cascade into explicit per-breakpoint
 * values. Core's `@tablet` band is exclusive (mobile < width <= tablet), while
 * the legacy pipeline let a tablet-only override apply at mobile widths too —
 * so a tablet value is materialized at `@mobile` unless mobile set its own.
 *
 * @param {Object|undefined} data Legacy { tablet, mobile } values.
 * @return {Object} { tablet?, mobile? } with the cascade applied.
 */
function cascadePair( data ) {
	if ( ! data || typeof data !== 'object' ) {
		return {};
	}
	const tablet =
		data.tablet !== undefined && data.tablet !== ''
			? data.tablet
			: undefined;
	const mobile =
		data.mobile !== undefined && data.mobile !== '' ? data.mobile : tablet;
	const out = {};
	if ( tablet !== undefined ) {
		out.tablet = tablet;
	}
	if ( mobile !== undefined ) {
		out.mobile = mobile;
	}
	return out;
}

/**
 * Set a deep path inside a (mutable, deep-cloned) object only when the leaf
 * is currently absent — user-authored core-format values always win over the
 * migration.
 *
 * @param {Object}   target Object to write into (mutated).
 * @param {string[]} path   Key path.
 * @param {unknown}  value  Value to set.
 */
function setIfAbsent( target, path, value ) {
	let node = target;
	for ( let i = 0; i < path.length - 1; i++ ) {
		if (
			typeof node[ path[ i ] ] !== 'object' ||
			node[ path[ i ] ] === null
		) {
			node[ path[ i ] ] = {};
		}
		node = node[ path[ i ] ];
	}
	const leaf = path[ path.length - 1 ];
	if ( node[ leaf ] === undefined ) {
		node[ leaf ] = value;
	}
}

/**
 * Fold the legacy blocklaneProResponsive bag into core 7.1 formats:
 *
 *  - fontSize / textAlign            → style['@bp'].typography.*
 *  - padding / margin / blockGap     → style['@bp'].spacing.*
 *  - minHeight                       → style['@bp'].dimensions.minHeight
 *  - justifyContent / orientation    → style['@bp'].layout.* (core vocabulary)
 *  - hidden                          → metadata.blockVisibility.viewport.* = false
 *
 * `order` and `maxWidth` remain in the bag (genuine Blocklane extras).
 * Existing core-format values are never clobbered. Returns the same
 * attributes reference when there is nothing to migrate.
 *
 * @param {Object} attributes Parsed block attributes.
 * @return {Object} Migrated attributes.
 */
export function migrateResponsiveAttributes( attributes ) {
	const bag = attributes.blocklaneProResponsive;
	if (
		! bag ||
		typeof bag !== 'object' ||
		! MIGRATED_KEYS.some( ( key ) => bag[ key ] )
	) {
		return attributes;
	}

	const style = JSON.parse( JSON.stringify( attributes.style ?? {} ) );
	const state = ( bp ) => `@${ bp }`;

	Object.entries( cascadePair( bag.fontSize ) ).forEach( ( [ bp, v ] ) =>
		setIfAbsent( style, [ state( bp ), 'typography', 'fontSize' ], v )
	);
	Object.entries( cascadePair( bag.textAlign ) ).forEach( ( [ bp, v ] ) => {
		if ( TEXT_ALIGN_VALUES.includes( v ) ) {
			setIfAbsent( style, [ state( bp ), 'typography', 'textAlign' ], v );
		}
	} );
	[ 'padding', 'margin' ].forEach( ( prop ) => {
		const data = bag[ prop ];
		if ( ! data || typeof data !== 'object' ) {
			return;
		}
		[ 'top', 'right', 'bottom', 'left' ].forEach( ( side ) => {
			const pair = cascadePair( {
				tablet: data.tablet?.[ side ],
				mobile: data.mobile?.[ side ],
			} );
			Object.entries( pair ).forEach( ( [ bp, v ] ) =>
				setIfAbsent( style, [ state( bp ), 'spacing', prop, side ], v )
			);
		} );
	} );
	Object.entries( cascadePair( bag.blockGap ) ).forEach( ( [ bp, v ] ) =>
		setIfAbsent( style, [ state( bp ), 'spacing', 'blockGap' ], v )
	);
	Object.entries( cascadePair( bag.minHeight ) ).forEach( ( [ bp, v ] ) =>
		setIfAbsent( style, [ state( bp ), 'dimensions', 'minHeight' ], v )
	);
	Object.entries( cascadePair( bag.justifyContent ) ).forEach(
		( [ bp, v ] ) => {
			if ( JUSTIFY_TO_CORE[ v ] ) {
				setIfAbsent(
					style,
					[ state( bp ), 'layout', 'justifyContent' ],
					JUSTIFY_TO_CORE[ v ]
				);
			}
		}
	);
	Object.entries( cascadePair( bag.orientation ) ).forEach( ( [ bp, v ] ) => {
		if ( ORIENTATION_VALUES.includes( v ) ) {
			setIfAbsent( style, [ state( bp ), 'layout', 'orientation' ], v );
		}
	} );

	// Visibility: independent per-device booleans → core's
	// metadata.blockVisibility.viewport (false = hidden). Skip entirely when
	// blockVisibility is the boolean "hidden everywhere" form.
	let metadata = attributes.metadata;
	if ( bag.hidden && typeof bag.hidden === 'object' ) {
		const blockVisibility = metadata?.blockVisibility;
		if (
			blockVisibility === undefined ||
			typeof blockVisibility === 'object'
		) {
			const viewport = { ...( blockVisibility?.viewport ?? {} ) };
			[ 'desktop', 'tablet', 'mobile' ].forEach( ( device ) => {
				if (
					bag.hidden[ device ] &&
					viewport[ device ] === undefined
				) {
					viewport[ device ] = false;
				}
			} );
			if ( Object.keys( viewport ).length ) {
				metadata = {
					...( metadata ?? {} ),
					blockVisibility: {
						...( blockVisibility ?? {} ),
						viewport,
					},
				};
			}
		}
	}

	const newBag = { ...bag };
	MIGRATED_KEYS.forEach( ( key ) => delete newBag[ key ] );

	return {
		...attributes,
		style: Object.keys( style ).length ? style : undefined,
		metadata,
		blocklaneProResponsive: newBag,
	};
}
