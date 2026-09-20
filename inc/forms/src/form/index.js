/**
 * Form wrapper — registration. Dynamic block: render.php owns the front
 * markup; save persists only the inner blocks.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { envelope } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	icon: envelope,
	edit,
	save: () => <InnerBlocks.Content />,
} );
