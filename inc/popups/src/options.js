/**
 * The popup editor's option helpers — pure, so they can be tested.
 *
 * Everything here reads the localized option payload
 * (window.blocklaneProPopupsOptions, from blocklane_pro_popups_editor_options()
 * — the third registry of the popups vocabularies, edition-manifest rule 6).
 * The payload carries three keys: `trigger` and `condition` rows, and
 * `qualifier`, the ONE label map for the qualifier vocabulary. This file
 * quotes no trigger, condition or qualifier VALUE of its own, so the free
 * bundle names no Pro option and a renamed value cannot leave a stale literal
 * behind. bin/popups-mirror-check.php scans this directory for exactly that.
 *
 * WHAT A DISABLED OPTION MEANS. A SelectControl whose value is not among its
 * options renders the FIRST option — a display that is not the stored state.
 * So a stored value with no option of its own always gets one, disabled; the
 * question is what it says, and there are two honest answers, which the old
 * code collapsed into one (#1026):
 *
 *   "Not available in this edition" — nothing in this build labels the value.
 *       True for a trigger or condition this edition does not carry.
 *   the value's own label — this build knows the value, the CONDITION just
 *       does not offer it. Under Pro, `page` + `contains` is exactly this, and
 *       it is storable (the sanitizer checks the whole qualifier vocabulary,
 *       not the condition's subset). Saying "not available in this edition"
 *       there was false.
 *
 * And a condition with NO ROW here offers only its stored qualifier, disabled
 * (D4). It used to offer a switchable is/is-not pair, which let an author
 * rewrite the qualifier of a rule this edition cannot evaluate at all.
 *
 * Left alone, every stored value saves back verbatim; choosing another option
 * is the author's own change. No badge, no link, no "Pro" (CHARTER.md, "will
 * not ship").
 */
import { __ } from '@wordpress/i18n';

/**
 * Build the helpers over one option payload.
 *
 * @param {Object} options The localized payload, or {} when the bridge did not run.
 * @return {Object} { triggerOptions, conditionOptions, qualifierOptions, defaultQualifier, triggerRow, conditionRow, isValueless, triggerHelp }
 */
export function makeOptionHelpers( options ) {
	const payload = options || {};
	const rowsOf = ( kind ) =>
		[ ...( Array.isArray( payload[ kind ] ) ? payload[ kind ] : [] ) ].sort(
			( a, b ) => ( a.order || 0 ) - ( b.order || 0 )
		);
	const triggerRows = rowsOf( 'trigger' );
	const conditionRows = rowsOf( 'condition' );
	const qualifierLabels =
		payload.qualifier && 'object' === typeof payload.qualifier
			? payload.qualifier
			: {};
	const rowFor = ( rows, value ) =>
		rows.find( ( row ) => row.value === value );

	const unavailable = () =>
		__( 'Not available in this edition', 'blocklane' );

	/* A stored value with no option of its own rides along, disabled. */
	const withCurrent = ( list, value ) =>
		! value || list.some( ( option ) => option.value === value )
			? list
			: [ ...list, { value, label: unavailable(), disabled: true } ];

	const triggerRow = ( type ) => rowFor( triggerRows, type ) || {};
	const conditionRow = ( condition ) =>
		rowFor( conditionRows, condition ) || {};

	const triggerOptions = ( current ) =>
		withCurrent(
			triggerRows.map( ( { value, label } ) => ( { value, label } ) ),
			current
		);
	const conditionOptions = ( current ) =>
		withCurrent(
			conditionRows.map( ( { value, label } ) => ( { value, label } ) ),
			current
		);

	/* The qualifier values a condition offers: a LIST of vocabulary values on
	   its row. A condition with no row offers none — not a guessed pair. */
	const offered = ( condition ) => {
		const list = conditionRow( condition ).qualifiers;
		return Array.isArray( list ) ? list : [];
	};

	/**
	 * The qualifier a fresh rule for this condition starts on: the first the
	 * condition offers. A rowless condition has none to offer, so the caller's
	 * own fallback stands.
	 *
	 * @param {string} condition Rule condition slug.
	 * @return {string|undefined} A qualifier value, or undefined.
	 */
	const defaultQualifier = ( condition ) => offered( condition )[ 0 ];

	/**
	 * Qualifier select options: what the condition offers, plus the stored
	 * value when the condition does not offer it — labeled with its own name
	 * when this build knows it, and with the edition sentence only when
	 * nothing here labels it.
	 *
	 * @param {string} condition Rule condition slug.
	 * @param {string} current   The stored qualifier.
	 * @return {Array} Select options.
	 */
	const qualifierOptions = ( condition, current ) => {
		const list = offered( condition ).map( ( value ) => ( {
			value,
			label: qualifierLabels[ value ] || value,
		} ) );
		if (
			! current ||
			list.some( ( option ) => option.value === current )
		) {
			return list;
		}
		return [
			...list,
			{
				value: current,
				label: qualifierLabels[ current ] || unavailable(),
				disabled: true,
			},
		];
	};

	/**
	 * Whether a condition is a pure state check — no value to assign. A
	 * condition with no row here (stored under Pro) shows the value field, so
	 * whatever it carries stays visible.
	 *
	 * @param {string} condition Rule condition slug.
	 * @return {boolean} True for a valueless condition.
	 */
	const isValueless = ( condition ) => !! conditionRow( condition ).valueless;

	/**
	 * Contextual help for the trigger select, from the type's row.
	 *
	 * @param {string} type Trigger type.
	 * @return {string|undefined} Help text.
	 */
	const triggerHelp = ( type ) => triggerRow( type ).help || undefined;

	return {
		triggerOptions,
		conditionOptions,
		qualifierOptions,
		defaultQualifier,
		triggerRow,
		conditionRow,
		isValueless,
		triggerHelp,
	};
}
