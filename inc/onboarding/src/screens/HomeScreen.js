/**
 * Dashboard home — the hero, the Docs/Guides/Videos grid, Essential Tools,
 * and the "What's New" changelog cards.
 *
 * The hero is a SLOT. The `license` unit owns screens/home/LicenseHero.js and
 * declares the `home-hero` slot, so an artifact that carries the unit finds it
 * in SLOTS and renders the four-state license hero; one that does not finds
 * nothing there and renders FreeHero — the free copy alone.
 *
 * FreeHero carried a single aggregate "Pro" card until 2026-09-21. It was the
 * whole upsell surface when the only alternative was silence; now each absent
 * feature keeps its own row on the screen where its control would be
 * (screens/pro-row.js), so the card repeated what the rows already say. It was
 * retired rather than kept beside them: the charter's line is that a Pro
 * mention is contextual, and saying it twice is how "sparingly" (guideline 11)
 * stops being true.
 *
 * The branch is SLOT PRESENCE, which the generator-filtered slots.js simply IS.
 * This screen reads no edition id: one state with one reader, and no path on
 * which license copy can reach an artifact that has no license.
 *
 * The Essential Tools cards follow the same rule. Each card is a file under
 * home/tool-cards/ owned by the unit whose screen it opens, behind the
 * one-line-per-card index home/tool-cards.js the generator filters (grammar
 * B, drop mode): a card for a screen this edition does not carry has no line,
 * so its title and description are never in this edition's bundle. Until
 * 2026-09-25 the dynamic-values card was compiled into the free bundle and
 * switched off by a runtime flag — Pro UI behind a flag, the shape the
 * manifest forbids (#979). isToolScreenOn() now answers TOGGLE state alone,
 * for cards that exist.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon } from '@wordpress/icons';

import { pluginName } from '../edition';
import { InfoCard } from '../components/InfoCard';
import { isToolScreenOn } from '../components/ScreenDisabledNotice';
import { SLOTS } from './home/slots';
// Every Essential Tools card, one re-export per line — the index the
// generator filters (grammar B, drop mode). Namespace import on purpose: the
// object is whatever the index exports after the filter, and THIS file names
// no unit and no Pro feature; the index does.
import * as TOOL_CARDS from './home/tool-cards';

/* The cards in display order. Slugs are shared data (like AdvancedScreen's
   ROW_ORDER): a namespace import is keyed alphabetically, so the order lives
   here. A slug whose card this edition does not carry has no entry in
   TOOL_CARDS and is skipped — no flag, no branch, nothing compiled behind it. */
const TOOL_CARD_ORDER = [ 'site-privacy', 'dynamic-values', 'scripts' ];
const CARDS_BY_SLUG = Object.fromEntries(
	Object.values( TOOL_CARDS ).map( ( card ) => [ card.slug, card ] )
);

/* Icon art for a tool card (Extensions keeps its pill motif). */
const ToolIcon = ( { icon } ) => (
	<span className="blocklane-pro-tool-card__icon">
		<Icon icon={ icon } />
	</span>
);

/**
 * The hero when no unit owns it: what this artifact IS, then the one Pro card.
 *
 * Deliberately says nothing about licenses, keys or renewals — there is no
 * license in an artifact that renders this, and a free plugin whose front door
 * asks for a key is the most reviewer-visible defect there is.
 *
 */
const FreeHero = () => (
	<>
		<div className="blocklane-pro-hero__copy">
			<p className="blocklane-pro-hero__eyebrow">
				{ __( 'Welcome to Blocklane', 'blocklane' ) }
			</p>
			<h1 className="blocklane-pro-hero__title">
				{ __( 'Build faster with Blocklane', 'blocklane' ) }
			</h1>
			<p className="blocklane-pro-hero__body">
				{ __(
					'SEO, forms, popups, site visibility and security tools, all on WordPress’s own blocks and settings. Every tool is off until you turn it on.',
					'blocklane'
				) }
			</p>
		</div>
	</>
);

const ToolCard = ( {
	art,
	title,
	description,
	action,
	onAction,
	disabled,
} ) => (
	<article className="blocklane-pro-tool-card">
		<div className="blocklane-pro-tool-card__art" aria-hidden="true">
			{ art }
		</div>
		<div className="blocklane-pro-tool-card__body">
			<h3>{ title }</h3>
			<p>{ description }</p>
			<div className="blocklane-pro-tool-card__actions">
				<Button
					variant="primary"
					onClick={ onAction }
					disabled={ disabled }
					__next40pxDefaultSize
				>
					{ action }
				</Button>
			</div>
		</div>
	</article>
);

export const HomeScreen = ( { onNavigate } ) => {
	// Same access pattern the other screens use (ChildThemeScreen, CanvasHeader).
	const { companionTheme = {} } = window.blocklaneProAdmin || {};

	// Presence, not an edition id: the slot is here when the unit that owns it
	// is here, and that is the whole of the test.
	const HeroSlot = SLOTS[ 'home-hero' ];

	return (
		<div className="blocklane-pro-home">
			<section id="blocklane-pro-hero" className="blocklane-pro-hero">
				{ /* The hero grid has two columns for the slot's copy + panel.
				     FreeHero renders copy alone, so the wrapper says so: without
				     `is-single` the second column stayed reserved and empty on
				     every free install's landing screen (#998). */ }
				<div
					className={ `blocklane-pro-hero__inner${
						HeroSlot ? '' : ' is-single'
					}` }
				>
					{ HeroSlot ? <HeroSlot /> : <FreeHero /> }
				</div>
			</section>

			<section className="blocklane-pro-resources">
				<div className="blocklane-pro-resources__inner">
					<header className="blocklane-pro-resources__header">
						<h2 className="blocklane-pro-resources__title">
							{ __(
								'Blocklane Docs, Guides, and Videos',
								'blocklane'
							) }
						</h2>
						<p className="blocklane-pro-resources__subtitle">
							{ __(
								'Comprehensive tutorials and references to help you build faster and smarter.',
								'blocklane'
							) }
						</p>
					</header>
					<div className="blocklane-pro-resources__grid">
						<InfoCard
							title={ __(
								'Blocklane Documentation',
								'blocklane'
							) }
							badge={ __( 'Coming soon', 'blocklane' ) }
							disabled
							tagline={ __(
								'Everything you need to know about working with the Blocklane block theme — patterns, blocks, theme.json, and more.',
								'blocklane'
							) }
							buttonText={ __( 'Browse the Docs', 'blocklane' ) }
						/>
						<InfoCard
							title={ __(
								'Blocklane Video Library',
								'blocklane'
							) }
							badge={ __( 'Coming soon', 'blocklane' ) }
							disabled
							tagline={ __(
								'Step-by-step video walkthroughs of the extensions, child-theme generator, and other features.',
								'blocklane'
							) }
							buttonText={ __( 'Watch the Videos', 'blocklane' ) }
						/>
						{ ! companionTheme.active && (
							<InfoCard
								title={ sprintf(
									/* translators: %s: companion theme name. */
									__( 'The %s theme', 'blocklane' ),
									companionTheme.name || 'Blocklane'
								) }
								tagline={ sprintf(
									/* translators: %s: the plugin name. */
									__(
										'The free companion theme these tools are designed around. %s works with any block theme — this one is just built to fit it.',
										'blocklane'
									),
									pluginName()
								) }
								link={
									companionTheme.installed
										? companionTheme.themesUrl
										: 'https://blocklanewp.com/'
								}
								internal={ companionTheme.installed }
								buttonText={
									companionTheme.installed
										? __(
												'Activate the theme',
												'blocklane'
											)
										: __( 'Get the theme', 'blocklane' )
								}
							/>
						) }
						<InfoCard
							title={ __(
								'Get help with Blocklane',
								'blocklane'
							) }
							tagline={ __(
								'Stuck on something or want to report an issue? Submit a request and we’ll help out.',
								'blocklane'
							) }
							link="https://blocklanewp.com/contact/"
							buttonText={ __(
								'Get Help with Blocklane',
								'blocklane'
							) }
						/>
					</div>
				</div>
			</section>

			<section className="blocklane-pro-tools">
				<div className="blocklane-pro-tools__inner">
					<header className="blocklane-pro-tools__header">
						<h2 className="blocklane-pro-tools__title">
							{ __( 'Essential Tools', 'blocklane' ) }
						</h2>
						<p className="blocklane-pro-tools__subtitle">
							{ __(
								'Use these tools to get your site up and running in just a few minutes.',
								'blocklane'
							) }
						</p>
					</header>

					<div className="blocklane-pro-tools__grid">
						{ /* A card points at a Site Tools screen. Which cards
						     EXIST is the index's business (a screen this
						     edition lacks has no card, by construction);
						     whether one SHOWS is that screen's Advanced
						     toggle, so a card cannot route to a screen with
						     no editor. */ }
						{ TOOL_CARD_ORDER.map(
							( slug ) => CARDS_BY_SLUG[ slug ]
						)
							.filter( Boolean )
							.filter( ( card ) => isToolScreenOn( card.slug ) )
							.map( ( card ) => (
								<ToolCard
									key={ card.slug }
									title={ card.title }
									description={ card.description }
									action={ card.action }
									onAction={ () => onNavigate?.( card.slug ) }
									art={ <ToolIcon icon={ card.icon } /> }
								/>
							) ) }
					</div>
				</div>
			</section>

			<section className="blocklane-pro-changelog">
				<div className="blocklane-pro-changelog__inner">
					<header className="blocklane-pro-changelog__header">
						<h2 className="blocklane-pro-changelog__title">
							{ __( 'What’s New in Blocklane', 'blocklane' ) }
						</h2>
						<p className="blocklane-pro-changelog__subtitle">
							{ __(
								'Stay up to date with the latest improvements and features.',
								'blocklane'
							) }
						</p>
					</header>
					<div className="blocklane-pro-changelog__grid">
						<InfoCard
							title={ __( 'Blocklane Blocks', 'blocklane' ) }
							tagline={
								<ul className="blocklane-pro-changelog__list">
									<li>
										{ __(
											'Accessible, server-rendered breadcrumbs block',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'Renders automatically through the theme’s breadcrumbs template part',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'Stays with the site — works even with the theme suite removed',
											'blocklane'
										) }
									</li>
								</ul>
							}
						/>
						<InfoCard
							title={ pluginName() }
							tagline={
								<ul className="blocklane-pro-changelog__list">
									<li>
										{ __(
											'Every tool off by default — turn on only what you use',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'Opt-in block editor extensions suite',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'Full-bleed dashboard mirroring the WP Site Editor',
											'blocklane'
										) }
									</li>
								</ul>
							}
						/>
						<InfoCard
							title={ __( 'Blocklane', 'blocklane' ) }
							tagline={
								<ul className="blocklane-pro-changelog__list">
									<li>
										{ __(
											'0.3.0 refinement pass — token vocabulary, fluid typography',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'Native theme.json fluid typography',
											'blocklane'
										) }
									</li>
									<li>
										{ __(
											'8-slot brand-guide palette, 7-step spacing scale',
											'blocklane'
										) }
									</li>
								</ul>
							}
						/>
					</div>
				</div>
			</section>
		</div>
	);
};
