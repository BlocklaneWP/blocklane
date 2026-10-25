/**
 * Registers the carousel's track in the block editor. The server registers
 * the block from its block.json; this adds the icon, the editor and the
 * save, and brings in the editor stylesheet.
 */

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { trackIcon } from '../shared/icons';
import Edit from './edit';
import './editor.scss';

registerBlockType( 'blocklane/carousel-track', {
	icon: trackIcon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
