/**
 * Form notification — registration. Success and error messages are
 * variations of one container block.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { published, caution } from '@wordpress/icons';

import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	icon: published,
	edit,
	save: () => <InnerBlocks.Content />,
	variations: [
		{
			name: 'success',
			title: __( 'Success Message', 'blocklane' ),
			description: __(
				'Shown after a successful submission.',
				'blocklane'
			),
			icon: published,
			attributes: { type: 'success' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: ( blockAttributes, variationAttributes ) =>
				blockAttributes.type === variationAttributes.type,
		},
		{
			name: 'error',
			title: __( 'Error Message', 'blocklane' ),
			description: __( 'Shown when a submission fails.', 'blocklane' ),
			icon: caution,
			attributes: { type: 'error' },
			scope: [ 'inserter', 'transform' ],
			isActive: ( blockAttributes, variationAttributes ) =>
				blockAttributes.type === variationAttributes.type,
		},
	],
} );
