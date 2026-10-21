/**
 * Registers the carousel (the shell) in the block editor. The server
 * registers the block from its block.json; this adds the icon, the editor
 * and the save, and brings in the block's two stylesheets.
 */

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { carouselIcon } from '../shared/icons';
import Edit from './edit';
import './editor.scss';
import './style.scss';

registerBlockType( 'blocklane/carousel', {
	icon: carouselIcon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
