/**
 * ProBadge — the ONE definition of the pair every Pro surface renders: the
 * visible badge and the state sentence for assistive technology.
 *
 * The visible badge is for sighted users; it is `aria-hidden`, because the
 * single word "Pro" names an owner and says nothing about state. The
 * visually hidden sentence is the STATE for a screen reader — it names WHICH
 * PRODUCT has the feature and says, honestly, that it is not in this plugin
 * (guideline 9's line: never imply payment unlocks something already
 * installed; never say "locked"). Until package 7 the docblocks promised the
 * badge "carried the state to a screen reader" while the accessibility tree
 * held only the word "Pro" (#1001), and the one explanatory sentence lived in
 * a panel that was aria-hidden whenever the help drawer was closed and gone
 * the moment a row was pinned.
 *
 * Used by FeatureItem (the row) and nowhere else. The pinned detail beside a
 * row (ProDetailHeading) carries neither half: the row already says it, and a
 * second copy read "Pro" twice (#985). The pair is the point, and one
 * component keeps its two halves together. CHARTER.md's amended "will not
 * ship" bullet names the hidden sentence and the bulk-action count as the
 * accessibility and honesty forms of the badge — a Pro surface may not say
 * more than this.
 *
 * @param {Object} props
 * @param {string} [props.label] The badge text; defaults to "Pro".
 */

import { __ } from '@wordpress/i18n';
import { VisuallyHidden } from '@wordpress/components';

export const ProBadge = ( { label } ) => (
	<>
		<span
			className="blocklane-pro-feature-item__badge is-pro"
			aria-hidden="true"
		>
			{ label || __( 'Pro', 'blocklane' ) }
		</span>
		<VisuallyHidden as="span">
			{ __(
				'Part of Blocklane Pro. Not included in this plugin.',
				'blocklane'
			) }
		</VisuallyHidden>
	</>
);

export default ProBadge;
