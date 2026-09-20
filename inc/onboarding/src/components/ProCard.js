/**
 * The free edition's single Pro card.
 *
 * ONE card, on the dashboard home, with one link. This is the only upsell
 * surface in the product and it is deliberately the whole of it: guideline 5
 * permits upselling, guideline 11 forbids hijacking the admin, and CHARTER.md
 * rules out "upsell screens, locked menu items, or greyed-out teasers inside
 * wp-admin". So there are no PRO badges on feature rows, no disabled toggles,
 * and no notices anywhere else.
 *
 * The link carries a static UTM and nothing else — no per-install identifier,
 * no click reporting. Guideline 11 permits the ad and forbids tracking
 * referrals through it, and that is the detail that gets plugins pulled AFTER
 * approval rather than at review.
 *
 * Renders nothing in Pro, where `absent` is empty.
 */

import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

export const ProCard = ( { edition } ) => {
	if ( ! edition || 'free' !== edition.edition || ! edition.absent?.length ) {
		return null;
	}

	return (
		<section className="blocklane-pro-home__pro-card">
			<h2>{ __( 'Blocklane Pro', 'blocklane' ) }</h2>
			<p>
				{ sprintf(
					/* translators: %d: how many additional tools Blocklane Pro adds. */
					__(
						'Pro adds %d more tools to the ones you already have — content modelling, dynamic values, a mega menu, a carousel, AI tools and a deeper set of editor controls.',
						'blocklane'
					),
					edition.absent.length
				) }
			</p>
			<ul>
				{ edition.absent.map( ( unit ) => (
					<li key={ unit.label }>
						<strong>{ unit.label }</strong>
						{ unit.blurb ? ` — ${ unit.blurb }` : null }
					</li>
				) ) }
			</ul>
			<p>
				{ __(
					'Your site keeps working when a license lapses. You stop receiving updates.',
					'blocklane'
				) }
			</p>
			{ edition.proUrl ? (
				<Button
					variant="primary"
					href={ edition.proUrl }
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'See what Pro adds', 'blocklane' ) }
				</Button>
			) : null }
		</section>
	);
};
