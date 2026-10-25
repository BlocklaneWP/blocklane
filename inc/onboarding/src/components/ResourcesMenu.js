/**
 * The Dashboard's Resources menu — the page header's action on Home (Amendment
 * R: `BL/Home/PageHeader` carries a Resources DropdownMenu as `actions`;
 * Amendment AE: `BL/Home/ResourcesMenu`). Its links and their labels are
 * today's; the copy pass that makes them translatable sentences is the C0
 * copy package (spec D13), not this one.
 */

import { __ } from '@wordpress/i18n';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { external, help } from '@wordpress/icons';

const RESOURCES_LINKS = [
	{
		group: 'docs',
		items: [
			{
				label: 'Blocklane Documentation',
				href: 'https://blocklanewp.com/resources/',
			},
			{
				label: 'Blocklane Support',
				href: 'https://blocklanewp.com/contact/',
			},
		],
	},
	{
		group: 'site',
		items: [
			{
				label: 'Visit blocklanewp.com',
				href: 'https://blocklanewp.com/',
			},
		],
	},
];

export const ResourcesMenu = () => (
	<DropdownMenu
		text={ __( 'Resources', 'blocklane' ) }
		icon={ help }
		label={ __( 'Resources', 'blocklane' ) }
		toggleProps={ { size: 'compact' } }
		popoverProps={ { placement: 'bottom-end' } }
	>
		{ () => (
			<>
				{ RESOURCES_LINKS.map( ( group ) => (
					<MenuGroup key={ group.group }>
						{ group.items.map( ( item ) => (
							<MenuItem
								key={ item.href }
								icon={ external }
								onClick={ () =>
									window.open(
										item.href,
										'_blank',
										'noopener,noreferrer'
									)
								}
							>
								{ item.label }
							</MenuItem>
						) ) }
					</MenuGroup>
				) ) }
			</>
		) }
	</DropdownMenu>
);
