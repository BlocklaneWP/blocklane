/**
 * Form Step block registration. Dynamic block: render.php owns the front
 * markup — but save MUST persist the inner blocks (the form wrapper's own
 * pattern); a null save silently DROPS every field inside the step on the
 * first editor save.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
