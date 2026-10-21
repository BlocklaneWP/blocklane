/**
 * Registers the carousel's slide in the block editor. The server registers
 * the block from its block.json; this adds the icon, the editor and the
 * save.
 */

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { contents } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Edit from './edit';

registerBlockType( 'blocklane/slide', {
	icon: contents,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
