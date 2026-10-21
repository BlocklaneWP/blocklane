/**
 * StatusBadge — the ONE map from a status to a badge's intent, for every
 * badge the Settings screen draws: a row's badge (Pro, Enforced) and the
 * details card's (Active, Pro, the overview's count). Spec
 * the C-settings spec (2026-10-12) §3.3; the intents follow the design
 * spec's Amendment D (Pro is the informational blue, Active the stable
 * green) and E (the runtime's own badge metrics: no override here).
 *
 * A status the map does not name throws: a badge whose meaning nobody
 * declared must fail where it is drawn, never fall back to a look.
 */
import { Badge } from '@wordpress/ui';

/** Status → `@wordpress/ui` Badge intent. */
export const BADGE_INTENT = Object.freeze( {
	active: 'stable',
	pro: 'informational',
	enforced: 'none',
	count: 'none',
} );

/**
 * @param {Object}      props
 * @param {string}      props.status   One of BADGE_INTENT's keys.
 * @param {JSX.Element} props.children The badge text.
 * @return {JSX.Element} The badge; any other prop (aria-hidden, className) passes through.
 */
export const StatusBadge = ( { status, children, ...rest } ) => {
	const intent = BADGE_INTENT[ status ];
	if ( ! intent ) {
		throw new Error( `StatusBadge: no intent for status "${ status }"` );
	}
	return (
		<Badge intent={ intent } { ...rest }>
			{ children }
		</Badge>
	);
};

export default StatusBadge;
