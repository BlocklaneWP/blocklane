/**
 * File upload field — registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { upload } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: upload,
	edit,
	save: () => null,
} );
