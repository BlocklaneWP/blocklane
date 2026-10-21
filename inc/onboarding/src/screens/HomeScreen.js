/**
 * The Dashboard (Home): a welcome, the tools that are switched on, and where
 * to get help, as core cards on the page card (design spec D2 and Amendment
 * R; the C-home spec (2026-10-13)). No brand fonts, no gradient, no art: the
 * logomark is the page's visual and Resources is its header action.
 *
 * The hero is a SLOT. The `license` unit owns screens/home/LicenseHero.js
 * and declares the `home-hero` slot, so an artifact that carries the unit
 * renders its license stories there; one that does not finds no slot and
 * renders the free welcome below. This screen reads no edition: the
 * generator-filtered slots.js is the whole of the test, so license copy
 * cannot reach an artifact that has no license.
 *
 * The tool cards follow the same rule: each lives under home/tool-cards/,
 * owned by the unit whose screen it opens, behind the one-line-per-card
 * index the generator filters (grammar B, drop mode). Whether a card SHOWS
 * is that screen's toggle (isToolScreenOn), and a section with no card is
 * not drawn (§8 #17).
 */
import { __, sprintf } from '@wordpress/i18n';

import { pluginName } from '../edition';
import { isToolScreenOn } from '../components/tool-screens';
import { BlocklanePage } from '../components/BlocklanePage';
import { BrandMark } from '../components/BrandMark';
import { ResourcesMenu } from '../components/ResourcesMenu';
import { About } from '../components/About';
import { SLOTS } from './home/slots';
import { WelcomeCard } from './home/WelcomeCard';
import { HomeSection, ToolCard, ResourceCard } from './home/cards';
import { HOME_ABOUT } from './home/about';
// Every tool card, one re-export per line: the index the generator filters.
// A namespace import keys them alphabetically, so the order lives below.
import * as TOOL_CARDS from './home/tool-cards';

/** The tool cards in display order; a slug this edition lacks is skipped. */
const TOOL_CARD_ORDER = [ 'site-privacy', 'dynamic-values', 'scripts' ];
const CARDS_BY_SLUG = Object.fromEntries(
	Object.values( TOOL_CARDS ).map( ( card ) => [ card.slug, card ] )
);

/**
 * The free edition's welcome: what this artifact is, and nothing about a
 * license, a key or a paid product.
 *
 * @return {JSX.Element} The card.
 */
const FreeWelcome = () => (
	<WelcomeCard
		eyebrow={ __( 'Welcome to Blocklane', 'blocklane' ) }
		title={ __( 'Build faster with Blocklane', 'blocklane' ) }
		body={ __(
			'SEO, forms, popups, site visibility and security tools, all on WordPress’s own blocks and settings. Turn each one on or off in Settings.',
			'blocklane'
		) }
	/>
);

/**
 * The resource cards: the companion theme until it is active, then help.
 *
 * @param {Object} theme The localized companionTheme: { active, installed, name, themesUrl }.
 * @return {JSX.Element[]} The cards.
 */
const resourceCards = ( theme ) =>
	[
		theme.active ? null : (
			<ResourceCard
				key="theme"
				slug="theme"
				title={ sprintf(
					/* translators: %s: the companion theme's name. */
					__( 'The %s theme', 'blocklane' ),
					theme.name || 'Blocklane'
				) }
				body={ sprintf(
					/* translators: %s: the plugin's name. */
					__(
						'The free companion theme these tools are designed around. %s works with any block theme — this one is just built to fit it.',
						'blocklane'
					),
					pluginName()
				) }
				href={
					theme.installed
						? theme.themesUrl
						: 'https://blocklanewp.com/'
				}
				internal={ Boolean( theme.installed ) }
				label={
					theme.installed
						? __( 'Activate the theme', 'blocklane' )
						: __( 'Get the theme', 'blocklane' )
				}
			/>
		),
		<ResourceCard
			key="help"
			slug="help"
			title={ __( 'Get help with Blocklane', 'blocklane' ) }
			body={ __(
				'Stuck on something or want to report an issue? Submit a request and we’ll help out.',
				'blocklane'
			) }
			href="https://blocklanewp.com/contact/"
			label={ __( 'Contact support', 'blocklane' ) }
		/>,
	].filter( Boolean );

export const HomeScreen = ( { onNavigate } ) => {
	const { companionTheme = {} } = window.blocklaneProAdmin || {};
	const HeroSlot = SLOTS[ 'home-hero' ];

	const tools = TOOL_CARD_ORDER.map( ( slug ) => CARDS_BY_SLUG[ slug ] )
		.filter( Boolean )
		.filter( ( card ) => isToolScreenOn( card.slug ) )
		.map( ( card ) => (
			<ToolCard
				key={ card.slug }
				card={ card }
				onAction={ () => onNavigate?.( card.slug ) }
			/>
		) );

	return (
		<BlocklanePage
			screen="home"
			visual={ <BrandMark /> }
			actions={ <ResourcesMenu /> }
			details={ { content: <About { ...HOME_ABOUT } /> } }
		>
			<div className="blocklane-home">
				<div className="blocklane-home__hero" data-hero="">
					{ HeroSlot ? <HeroSlot /> : <FreeWelcome /> }
				</div>
				<HomeSection
					slug="tools"
					title={ __( 'Essential tools', 'blocklane' ) }
					subtitle={ __(
						'Use these tools to get your site up and running in just a few minutes.',
						'blocklane'
					) }
					cards={ tools }
				/>
				<HomeSection
					slug="resources"
					title={ __(
						'Blocklane docs, guides, and videos',
						'blocklane'
					) }
					subtitle={ __(
						'Comprehensive tutorials and references to help you build faster and smarter.',
						'blocklane'
					) }
					cards={ resourceCards( companionTheme ) }
				/>
			</div>
		</BlocklanePage>
	);
};
