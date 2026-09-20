/**
 * Submit button — registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { button } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: button,
	edit,
	save: () => null,
} );
