/**
 * The resolved submit names of every field inside a form — resolved by the
 * SERVER (see use-resolved-field-names.js), so a picked name always matches a
 * server schema key. Consumed by the form block's unique-entry picker and
 * every field's Visibility condition picker.
 */
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

import useResolvedFieldNames from './use-resolved-field-names';

/**
 * Walk a form's inner blocks and collect one resolver row per field.
 *
 * Kept separate from resolution because this half is synchronous store
 * reading and the other half is a round trip; splitting them is what lets the
 * names come from the one namer instead of a copy.
 *
 * @param {Object}  sel             block-editor store selectors.
 * @param {string}  formClientId    The blocklane/form block's clientId.
 * @param {string}  excludeClientId A field block to leave out (self).
 * @param {boolean} uniqueEligible  Offer only fields a visitor can vary.
 * @return {Array<{name: string, label: string, fallback: string}>} Rows.
 */
const collectRows = ( sel, formClientId, excludeClientId, uniqueEligible ) => {
	const rows = [];
	const walk = ( blocks ) => {
		( blocks || [] ).forEach( ( inner ) => {
			if ( inner.clientId !== excludeClientId ) {
				const block = inner.name || '';
				const attrs = inner.attributes || {};
				if (
					[
						'blocklane/form-input',
						'blocklane/form-textarea',
						'blocklane/form-select',
					].includes( block )
				) {
					// A hidden input's value is the AUTHORED constant, identical
					// for every visitor — so "one entry per" that field accepts
					// exactly one submission ever and then rejects the form,
					// permanently, with no way to see why. Never offer it as a
					// uniqueness key.
					const isHidden =
						'blocklane/form-input' === block &&
						'hidden' === attrs.type;
					if ( ! ( uniqueEligible && isHidden ) ) {
						rows.push( {
							name: attrs.name || '',
							label: attrs.label || '',
							fallback: 'field',
						} );
					}
				} else if ( 'blocklane/form-group' === block ) {
					// Same reasoning for a checkbox group: its value is a LIST,
					// so uniqueness compares a joined string and two visitors
					// ticking the same boxes collide. Radio groups are
					// single-valued and fine.
					const isMulti = 'checkbox' === attrs.type;
					if ( ! ( uniqueEligible && isMulti ) ) {
						rows.push( {
							name: attrs.name || '',
							label: attrs.legend || '',
							fallback: 'choices',
						} );
					}
				}
			}
			walk( inner.innerBlocks );
		} );
	};
	walk( sel.getBlock( formClientId )?.innerBlocks );

	return rows;
};

/**
 * @param {string}  formClientId              The blocklane/form block's clientId.
 * @param {Object}  [options]
 * @param {string}  [options.excludeClientId] A field block to leave out (self).
 * @param {boolean} [options.uniqueEligible]  Offer only fields a visitor can
 *                                            actually vary — for the
 *                                            "one entry per…" picker.
 * @return {string[]} Unique resolved field names, document order. Empty until
 *                    the server answers.
 */
export default function useFormFieldNames( formClientId, options = {} ) {
	const { excludeClientId = '', uniqueEligible = false } = options;

	const rows = useSelect(
		( select ) => {
			if ( ! formClientId ) {
				return [];
			}

			return collectRows(
				select( blockEditorStore ),
				formClientId,
				excludeClientId,
				uniqueEligible
			);
		},
		[ formClientId, excludeClientId, uniqueEligible ]
	);

	const resolved = useResolvedFieldNames( rows );

	return [ ...new Set( resolved.filter( Boolean ) ) ];
}

export { collectRows };
