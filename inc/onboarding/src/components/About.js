/**
 * About — the details card's body for a Settings row, and for the screen's
 * overview (the C-settings spec (2026-10-12) §3.3; design spec
 * Amendments D, E, G): the video slot, a one-line intro, labeled groups, an
 * optional muted note, and the one Learn more link to the feature's page.
 * Nothing else: the card is information only, no controls (Amendment F4).
 *
 * The link is drawn only when its URL passes the one predicate
 * (about-shape.js, Amendment 1 F11): a refused URL draws no link rather
 * than a link somewhere else. Core's ExternalLink marks it as leaving the
 * site.
 */
import { ExternalLink } from '@wordpress/components';

import { VideoPreview } from './VideoPreview';
import { isLearnMoreUrl } from './about-shape';

/**
 * @param {Object}      props
 * @param {string}      props.intro       One line.
 * @param {Array}       [props.groups]    [ { label, items } ].
 * @param {string}      [props.note]      One muted line.
 * @param {Object|null} [props.learnMore] { url, label }, or null for no link.
 * @return {JSX.Element} The About.
 */
export const About = ( { intro, groups = [], note, learnMore = null } ) => (
	<div className="blocklane-about">
		<div data-part="video">
			<VideoPreview />
		</div>
		<p className="blocklane-about__intro" data-part="intro">
			{ intro }
		</p>
		{ groups.map( ( group ) => (
			<div
				key={ group.label }
				className="blocklane-about__group"
				data-part="group"
			>
				<h3 className="blocklane-about__group-label">
					{ group.label }
				</h3>
				<ul className="blocklane-about__items">
					{ group.items.map( ( item ) => (
						<li key={ item }>{ item }</li>
					) ) }
				</ul>
			</div>
		) ) }
		{ note ? (
			<p className="blocklane-about__note is-muted" data-part="note">
				{ note }
			</p>
		) : null }
		{ learnMore && isLearnMoreUrl( learnMore.url ) ? (
			<p data-part="learn-more">
				<ExternalLink
					className="blocklane-about__learn-more"
					href={ learnMore.url }
				>
					{ learnMore.label }
				</ExternalLink>
			</p>
		) : null }
	</div>
);

export default About;
