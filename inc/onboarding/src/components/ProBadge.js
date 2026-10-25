/**
 * ProBadge — the pair a Pro row carries: the visible "Pro" badge, which is
 * for sighted users and hidden from assistive technology (one word names an
 * owner, not a state), and the sentence a screen reader gets instead, which
 * names the product that has the feature and says plainly that this plugin
 * does not include it. Never "locked", never a verb that implies payment
 * unlocks something installed (wordpress.org guideline 9; CHARTER.md).
 *
 * The sentence carries an id derived from the row, so the row's info button
 * can point at it (`aria-describedby`, spec
 * the C-settings spec (2026-10-12) §3.3): the badge's meaning travels
 * with the control a keyboard reaches. FeatureRow is its caller; the pair is
 * one component so its two halves cannot be drawn apart.
 */
import { __ } from '@wordpress/i18n';
import { VisuallyHidden } from '@wordpress/components';

import { StatusBadge } from './StatusBadge';

/**
 * The id of a Pro row's sentence.
 *
 * @param {string} slug The row slug.
 * @return {string} The id.
 */
export const proSentenceId = ( slug ) => `blocklane-pro-sentence-${ slug }`;

/**
 * @param {Object} props
 * @param {string} props.slug The row slug (keys the sentence's id).
 * @return {JSX.Element} The badge and its sentence.
 */
export const ProBadge = ( { slug } ) => (
	<>
		<StatusBadge status="pro" aria-hidden="true">
			{ __( 'Pro', 'blocklane' ) }
		</StatusBadge>
		<VisuallyHidden as="span" id={ proSentenceId( slug ) }>
			{ __(
				'Part of Blocklane Pro. Not included in this plugin.',
				'blocklane'
			) }
		</VisuallyHidden>
	</>
);

export default ProBadge;
