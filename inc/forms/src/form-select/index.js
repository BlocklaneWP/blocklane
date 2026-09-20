/**
 * Dropdown field — registration. Options are form-option inner blocks, so
 * save serializes the children (the group precedent).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { chevronDown } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: chevronDown,
	edit,
	save: () => <InnerBlocks.Content />,
} );
