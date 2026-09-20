/**
 * Choice group — registration. Radio and checkbox groups are variations of
 * one fieldset block.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { listView, check } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: listView,
	edit,
	save: () => <InnerBlocks.Content />,
	variations: [
		{
			name: 'radio',
			title: __( 'Radio Group', 'blocklane' ),
			description: __( 'Pick one of several choices.', 'blocklane' ),
			icon: listView,
			attributes: { type: 'radio' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: ( blockAttributes, variationAttributes ) =>
				blockAttributes.type === variationAttributes.type,
		},
		{
			name: 'checkbox',
			title: __( 'Checkbox Group', 'blocklane' ),
			description: __( 'Pick any of several choices.', 'blocklane' ),
			icon: check,
			attributes: { type: 'checkbox' },
			scope: [ 'inserter', 'transform' ],
			isActive: ( blockAttributes, variationAttributes ) =>
				blockAttributes.type === variationAttributes.type,
		},
	],
} );
