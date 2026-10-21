/**
 * Form-level input styling — the JS mirror of
 * blocklane_pro_forms_input_style_vars() in inc/forms/runtime.php. The form
 * block writes these custom properties on its wrapper in BOTH the canvas and
 * the front render, so the cascade is 1:1 by construction.
 */

const VAR_OF = {
	background: '--blocklane-form--input-background',
	text: '--blocklane-form--input-text',
	border: '--blocklane-form--input-border',
	focusBorder: '--blocklane-form--input-focus-border',
	radius: '--blocklane-form--input-radius',
	asterisk: '--blocklane-form--asterisk',
};

/**
 * Build a React style object of input-style custom properties.
 *
 * @param {Object} inputStyles The form block's inputStyles attribute.
 * @return {Object} Style object ({} when nothing is set).
 */
export function inputStyleVars( inputStyles = {} ) {
	const style = {};
	Object.entries( VAR_OF ).forEach( ( [ key, cssVar ] ) => {
		const value = inputStyles?.[ key ];
		if ( typeof value === 'string' && value.trim() !== '' ) {
			style[ cssVar ] = value.trim();
		}
	} );
	return style;
}
