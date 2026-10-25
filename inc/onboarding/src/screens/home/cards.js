/**
 * The Dashboard's card parts (the C-home spec (2026-10-13) §3; design spec
 * D2, Amendment R, D34, R5): core's Card, CardBody and CardFooter, sized the
 * way the frames draw them (`BL/Home/ToolCard`, `BL/Home/InfoCard`, the
 * section header `BL/Home/SectionHeader`), and nothing of our own beyond the
 * grid they sit in.
 *
 * Core files: they name no feature and no edition. A tool card's copy comes
 * from the unit that owns it (home/tool-cards/), so a card for a screen this
 * edition lacks is never built.
 */
import { Button, Card, CardBody, CardFooter } from '@wordpress/components';
import { Icon, external } from '@wordpress/icons';

/** A card body: padding-2xl (24) on every side (core's `medium` step). */
export const BODY_SIZE = Object.freeze( {
	blockStart: 'medium',
	blockEnd: 'medium',
	inlineStart: 'medium',
	inlineEnd: 'medium',
} );

/** A card footer: padding-lg (16) above and below, padding-2xl (24) at the sides. */
const FOOTER_SIZE = Object.freeze( {
	blockStart: 'small',
	blockEnd: 'small',
	inlineStart: 'medium',
	inlineEnd: 'medium',
} );

/**
 * A section of the page: its heading and one sentence over a grid of cards.
 * A section with no cards is not drawn at all (§8 #17: an empty "Essential
 * tools" was a heading over nothing).
 *
 * @param {Object}        props
 * @param {string}        props.slug     The section's name (data-section, data-grid).
 * @param {string}        props.title    The heading.
 * @param {string}        props.subtitle The sentence under it.
 * @param {JSX.Element[]} props.cards    The cards.
 * @return {JSX.Element|null} The section.
 */
export const HomeSection = ( { slug, title, subtitle, cards } ) =>
	cards.length ? (
		<section className="blocklane-home__section" data-section={ slug }>
			<header className="blocklane-home__section-header">
				<h2 className="blocklane-home__section-title">{ title }</h2>
				<p className="blocklane-home__section-subtitle">{ subtitle }</p>
			</header>
			<div className="blocklane-home__grid" data-grid={ slug }>
				{ cards }
			</div>
		</section>
	) : null;

/**
 * A tool card: the screen's icon in a tile, its name and sentence, and one
 * secondary button that opens it (R5: a repeated card action is secondary).
 *
 * @param {Object}     props
 * @param {Object}     props.card     { slug, title, description, action, icon }.
 * @param {() => void} props.onAction Opens the screen.
 * @return {JSX.Element} The card.
 */
export const ToolCard = ( { card, onAction } ) => (
	<Card className="blocklane-home-card" data-tool={ card.slug }>
		<CardBody size={ BODY_SIZE } className="blocklane-home-card__body">
			<span className="blocklane-home-card__icon" aria-hidden="true">
				<Icon icon={ card.icon } />
			</span>
			<div className="blocklane-home-card__text">
				<h3 className="blocklane-home-card__title">{ card.title }</h3>
				<p
					className="blocklane-home-card__description"
					data-tool-description=""
				>
					{ card.description }
				</p>
			</div>
		</CardBody>
		<CardFooter size={ FOOTER_SIZE }>
			<Button
				variant="secondary"
				__next40pxDefaultSize
				onClick={ onAction }
			>
				{ card.action }
			</Button>
		</CardFooter>
	</Card>
);

/**
 * A resource card: a name, a sentence, and one link. A link that leaves the
 * site opens a new tab and says so with core's external glyph; a link into
 * wp-admin (the installed theme's Activate) stays in this tab.
 *
 * @param {Object}  props
 * @param {string}  props.slug       The card's name (data-resource).
 * @param {string}  props.title      The heading.
 * @param {string}  props.body       The sentence.
 * @param {string}  props.href       Where the link goes.
 * @param {string}  props.label      The link's text.
 * @param {boolean} [props.internal] A wp-admin link (same tab, no glyph).
 * @return {JSX.Element} The card.
 */
export const ResourceCard = ( {
	slug,
	title,
	body,
	href,
	label,
	internal,
} ) => (
	<Card className="blocklane-home-card" data-resource={ slug }>
		<CardBody size={ BODY_SIZE } className="blocklane-home-card__body">
			<div className="blocklane-home-card__text">
				<h3 className="blocklane-home-card__title">{ title }</h3>
				<p className="blocklane-home-card__description">{ body }</p>
			</div>
		</CardBody>
		<CardFooter size={ FOOTER_SIZE }>
			<Button
				variant="tertiary"
				__next40pxDefaultSize
				href={ href }
				target={ internal ? undefined : '_blank' }
				rel={ internal ? undefined : 'noopener noreferrer' }
				icon={ internal ? undefined : external }
				iconPosition="right"
			>
				{ label }
			</Button>
		</CardFooter>
	</Card>
);
