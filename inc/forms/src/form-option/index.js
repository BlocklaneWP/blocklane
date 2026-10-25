/**
 * Choice — registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { blockDefault } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: blockDefault,
	edit,
	save: () => null,
} );
