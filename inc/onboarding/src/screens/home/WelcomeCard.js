/**
 * The Dashboard's welcome card (`BL/Home/WelcomeCard`, design spec D2 and
 * Amendment R): an eyebrow, the hero's h2 and one sentence, on core's Card.
 * The h1 stays the page's ("Dashboard"). Core: it carries whatever copy its
 * caller hands it, so the free welcome and the license unit's stories share
 * one card without this file naming either.
 */
import { Card, CardBody } from '@wordpress/components';

import { BODY_SIZE } from './cards';

/**
 * @param {Object} props
 * @param {string} props.eyebrow The line over the title.
 * @param {string} props.title   The h2.
 * @param {string} props.body    One sentence (or two), 720 wide at most.
 * @return {JSX.Element} The card.
 */
export const WelcomeCard = ( { eyebrow, title, body } ) => (
	<Card className="blocklane-home-welcome" data-welcome="">
		<CardBody size={ BODY_SIZE } className="blocklane-home-welcome__body">
			<p className="blocklane-home-welcome__eyebrow" data-eyebrow="">
				{ eyebrow }
			</p>
			<h2 className="blocklane-home-welcome__title">{ title }</h2>
			<p className="blocklane-home-welcome__text" data-welcome-body="">
				{ body }
			</p>
		</CardBody>
	</Card>
);
