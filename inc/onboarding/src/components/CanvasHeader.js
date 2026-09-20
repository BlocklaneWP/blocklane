/**
 * Canvas top bar — brand left, Resources dropdown right. License management
 * lives in the Home hero (LicenseKeyForm / the license panel), so the header
 * carries no license entry point.
 */

import { __ } from '@wordpress/i18n';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { external, help } from '@wordpress/icons';

import { BrandLogo } from './BrandLogo';

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

export const CanvasHeader = () => {
	const settings = window.blocklaneProAdmin || {};

	return (
		<header className="blocklane-pro-canvas-header">
			<div className="blocklane-pro-canvas-header__brand">
				<span className="blocklane-pro-canvas-header__logo">
					<BrandLogo />
				</span>
				{ settings.version ? (
					<span className="blocklane-pro-canvas-header__version">
						v{ settings.version }
					</span>
				) : null }
			</div>

			<ul className="blocklane-pro-canvas-header__nav">
				<li>
					<DropdownMenu
						text={ __( 'Resources', 'blocklane' ) }
						icon={ help }
						label={ __( 'Resources', 'blocklane' ) }
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
				</li>
			</ul>
		</header>
	);
};
