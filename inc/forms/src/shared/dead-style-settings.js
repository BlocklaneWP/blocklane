/**
 * Per-instance style-setting kill switch for the hidden input variation: it
 * renders as a bare <input type="hidden"> — no wrapper, no visible output —
 * so every style support is a dead control there. Supports are declared per
 * block TYPE, so block.json cannot express this; the useSetting.before
 * filter is the per-instance mechanism. (The consent checkbox is NOT here:
 * its styles target the row wrapper — see field_render_base's
 * $style_on_wrapper — so its style controls are live.)
 */
import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

const DEAD_SETTINGS = /^(color|border|typography|spacing)\./;

addFilter(
	'blockEditor.useSetting.before',
	'blocklane-pro/forms-dead-style-settings',
	( value, path, clientId, blockName ) => {
		if ( 'blocklane/form-input' !== blockName ) {
			return value;
		}
		const type =
			select( blockEditorStore ).getBlockAttributes( clientId )?.type;
		return 'hidden' === type && DEAD_SETTINGS.test( path ) ? false : value;
	}
);
