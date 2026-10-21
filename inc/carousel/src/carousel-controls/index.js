/**
 * Registers the carousel controls block in the editor. The server registers
 * the block from its block.json; the editor adds its icon, its edit
 * component and its save.
 */

import { registerBlockType } from '@wordpress/blocks';
import { queryPagination } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';
import './editor.scss';

/**
 * The block saves no markup: the server renders it from its attributes.
 *
 * @return {null} Nothing to save.
 */
function save() {
	return null;
}

registerBlockType( metadata.name, {
	icon: queryPagination,
	edit: Edit,
	save,
} );
