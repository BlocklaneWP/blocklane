/**
 * Shown in place of a module screen whose Advanced "Site Tools" toggle is
 * off. The server hides the submenu item and unregisters the screen's REST
 * route, but the app router still resolves the slug (deep links, bookmarks,
 * Home tool cards), so without this the screen would mount and dead-end on a
 * raw rest_no_route Notice. Mirrors ChildThemeScreen's off-state pointer.
 */

import { __ } from '@wordpress/i18n';
import { Button, Flex, FlexItem, Notice } from '@wordpress/components';

import { buildRouteUrl } from '../router';

/**
 * Whether a Site Tools screen is enabled, read from the localized flag map.
 * Fails open (true) when the flag is absent — never strands a screen closed.
 *
 * @param {string} slug Toggle slug (matches the screen slug).
 * @return {boolean} Whether the screen is on.
 */
export const isToolScreenOn = ( slug ) => {
	const map = ( window.blocklaneProAdmin || {} ).toolScreens || {};
	return !! ( map[ slug ] ?? true );
};

/**
 * The off-state pointer: a title, an explanation, and a button that opens the
 * governing toggle's detail panel on the Advanced screen.
 *
 * @param {Object} props
 * @param {string} props.title Screen title (matches its normal <h1>).
 * @param {string} props.body  One-line explanation of what off means here.
 * @param {string} props.slug  Toggle slug for the ?adv= deep link.
 */
export const ScreenDisabledNotice = ( { title, body, slug } ) => {
	// Built from the current URL so the page slug isn't hardcoded.
	const advancedUrl = buildRouteUrl( { screen: 'advanced', adv: slug } );

	return (
		<Flex align="stretch" gap="0" className="blocklane-pro-extensions">
			<FlexItem className="blocklane-pro-extensions__sidebar">
				<section className="blocklane-pro-extensions__section">
					<header className="blocklane-pro-extensions__header">
						<h1 className="blocklane-pro-extensions__title">
							{ title }
						</h1>
					</header>
					<Notice status="info" isDismissible={ false }>
						{ __( 'This screen is turned off.', 'blocklane' ) }
					</Notice>
					<p className="blocklane-pro-child-theme__help">{ body }</p>
					<Button
						variant="primary"
						__next40pxDefaultSize
						href={ advancedUrl }
					>
						{ __( 'Open Advanced settings', 'blocklane' ) }
					</Button>
				</section>
			</FlexItem>
		</Flex>
	);
};
