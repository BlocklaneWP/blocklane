/**
 * The detail panel's title line for a row whose feature is not in this build.
 *
 * Stands where the live row's ToggleControl stands, so the panel keeps its
 * shape: the feature's name and ONE plain link. No badge: the pinned row sits
 * beside this panel in the same split view and already carries the pair
 * (ProBadge — the aria-hidden "Pro" badge and the visually hidden sentence
 * that tells assistive technology which product has the feature), so a second
 * copy here put "Pro" in front of every reader twice (#985). The link's own
 * words say whose feature it is.
 * Shared by the Advanced and Extensions drawers — one definition, because the
 * rule about what a Pro surface may contain is easier to keep in one file
 * than to remember in two.
 *
 * What the markup does: a PARAGRAPH, not a heading (D4). The screen's only
 * heading is ScreenHeader's <h1>; this used to be a level-three heading with
 * no level two anywhere on the screen, a skipped level for anyone navigating
 * by heading (#984). The live path's counterpart is a ToggleControl's <label>, which is
 * not a heading either, so a paragraph is the honest parity; the CSS keys on
 * the class, not the element.
 *
 * WHAT THIS MAY NOT BECOME. CHARTER.md's amended "will not ship" bullet allows
 * a Pro row on the free edition's own settings screens and nothing more: no
 * badge art, no color beyond the badge, no repeated call to action, no price,
 * no countdown, no second link. Guideline 11 asks that upgrade prompts be
 * "limited in scope and used sparingly", and the whole reason this is one
 * quiet line is that the row already said what the feature is. If a future
 * change wants more here, that is a charter amendment, not a component edit.
 *
 * The link carries whatever `pro_url()` carries — a static URL with a static
 * UTM and nothing else. Guideline 11 permits the ad and forbids tracking
 * referrals through it, so no per-install identifier and no click reporting
 * may ever be added, here or in Edition::pro_url(). That is the detail that
 * gets plugins pulled AFTER approval rather than at review.
 */

import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

import { edition } from '../edition.js';

export const ProDetailHeading = ( { feature } ) => {
	if ( ! feature ) {
		return null;
	}

	const proUrl = edition().proUrl;

	return (
		<div className="blocklane-pro-extensions__detail-pro">
			<p className="blocklane-pro-extensions__detail-pro-title">
				{ feature.title }
			</p>
			{ proUrl ? (
				<ExternalLink href={ proUrl }>
					{ __( 'Learn about Blocklane Pro', 'blocklane' ) }
				</ExternalLink>
			) : null }
		</div>
	);
};

export default ProDetailHeading;
