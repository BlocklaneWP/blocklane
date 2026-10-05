/**
 * "Can this block carry a required value?" — for the form wrapper's canvas.
 *
 * The answer comes from the server bridge (window.blocklaneProForms.fieldBlocks,
 * localized by blocklane_pro_forms_editor_state() from
 * blocklane_pro_forms_field_blocks()) on every core editor screen, so the
 * canvas's required notice and the front's agree by construction.
 *
 * WITHOUT THE BRIDGE the fallback is DERIVED, not listed: a block is
 * required-capable exactly when its registered type declares a `required`
 * attribute. That is true today for the five suite field blocks
 * (form-input, form-textarea, form-select, form-group, form-file) and for any
 * type a seam registrant adds that declares one — so there is no list to
 * drift out of date, and the answer still comes from the server's own
 * registration rather than from a literal in this file (#1037). The bridge is
 * absent only where the form script is enqueued outside a core editor screen,
 * so this is a narrow path; failing toward the server's truth is the same
 * philosophy as KNOWN_SUITE's `null` = the whole suite.
 */

/**
 * Build the predicate.
 *
 * @param {Array<string>|null|undefined}       bridgeList   The localized field-block list, or null/undefined when the bridge did not run.
 * @param {(name: string) => Object|undefined} getBlockType `getBlockType` from `@wordpress/blocks`.
 * @return {(name: string) => boolean} Whether the named block can be required.
 */
export function makeRequiredCapable( bridgeList, getBlockType ) {
	return ( name ) =>
		Array.isArray( bridgeList )
			? bridgeList.includes( name )
			: !! getBlockType?.( name )?.attributes?.required;
}
