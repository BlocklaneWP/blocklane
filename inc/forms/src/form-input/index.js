/**
 * Input field — registration. One canonical block; the input types are
 * variations (inserter tiles), core/embed style.
 */
import { registerBlockType } from '@wordpress/blocks';
import { paragraph } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';
import variations from './variations';
import '../shared/dead-style-settings';

registerBlockType( metadata.name, {
	icon: paragraph,
	edit,
	save: () => null,
	variations,
} );
