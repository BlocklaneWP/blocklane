/**
 * Message (textarea) field — registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { commentContent } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: commentContent,
	edit,
	save: () => null,
} );
