/**
 * Input-type variations — the inserter tiles. One canonical field block
 * underneath (core/embed precedent): the schema and render code only ever
 * deal with `blocklane/form-input`.
 */
import { __ } from '@wordpress/i18n';
import {
	paragraph,
	atSymbol,
	mobile,
	link,
	chartBar,
	calendar,
	unseen,
	check,
} from '@wordpress/icons';

const variations = [
	{
		name: 'text',
		title: __( 'Text Field', 'blocklane' ),
		description: __( 'A single line of text.', 'blocklane' ),
		icon: paragraph,
		attributes: { type: 'text' },
		isDefault: true,
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'email',
		title: __( 'Email Field', 'blocklane' ),
		description: __( 'An email address.', 'blocklane' ),
		icon: atSymbol,
		attributes: { type: 'email', autocomplete: 'email' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'tel',
		title: __( 'Phone Field', 'blocklane' ),
		description: __( 'A phone number.', 'blocklane' ),
		icon: mobile,
		attributes: { type: 'tel', autocomplete: 'tel' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'url',
		title: __( 'URL Field', 'blocklane' ),
		description: __( 'A web address.', 'blocklane' ),
		icon: link,
		attributes: { type: 'url', autocomplete: 'url' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'number',
		title: __( 'Number Field', 'blocklane' ),
		description: __( 'A number, with optional bounds.', 'blocklane' ),
		icon: chartBar,
		attributes: { type: 'number' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'date',
		title: __( 'Date Field', 'blocklane' ),
		description: __( 'A date picker.', 'blocklane' ),
		icon: calendar,
		attributes: { type: 'date' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'hidden',
		title: __( 'Hidden Field', 'blocklane' ),
		description: __(
			'A fixed value submitted with the form, invisible to visitors.',
			'blocklane'
		),
		icon: unseen,
		attributes: { type: 'hidden' },
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'checkbox',
		title: __( 'Consent Checkbox', 'blocklane' ),
		description: __(
			'A single checkbox, e.g. consent or opt-in.',
			'blocklane'
		),
		icon: check,
		attributes: { type: 'checkbox' },
		scope: [ 'inserter', 'transform' ],
	},
];

variations.forEach( ( variation ) => {
	variation.isActive = ( blockAttributes, variationAttributes ) =>
		blockAttributes.type === variationAttributes.type;
} );

export default variations;
