/**
 * ScreenHeader — the dashboard's page header, one markup for every screen
 * that has one (SEO, Forms, Content Types, Extensions, Advanced): core's
 * admin-ui page arrangement — a full-width title section with a hairline
 * bottom border, then the screen's tab row, then content. Title and
 * subtitle come from the screen registry so the copy has one home; a
 * screen may override either (SEO adds its brand mark as the icon). An
 * unknown slug throws (requireScreen) rather than rendering Home's copy.
 *
 * The h1 is the screen's roving focus target: screens focus it on mount
 * (and on view changes) so keyboard and screen-reader users land on the
 * new view and hear it announced. It is not a Tab stop.
 */

import { requireScreen } from '../screens/registry';

/**
 * @param {Object}      props
 * @param {string}      props.screen      Screen slug (registry key).
 * @param {Object}      [props.pageStart] Ref for the h1 (the focus target).
 * @param {JSX.Element} [props.icon]      Optional glyph before the title.
 * @param {string}      [props.title]     Override the registry title.
 * @param {string}      [props.subtitle]  Override the registry subtitle.
 */
export const ScreenHeader = ( {
	screen,
	pageStart,
	icon,
	title,
	subtitle,
} ) => {
	const entry = requireScreen( screen );
	const heading = title ?? entry.title;
	const lede = subtitle ?? entry.subtitle;

	return (
		<header className="blocklane-pro-page__header">
			<h1
				className="blocklane-pro-page__title"
				ref={ pageStart }
				tabIndex={ -1 }
			>
				{ icon ? (
					<span className="blocklane-pro-page__title-icon">
						{ icon }
					</span>
				) : null }
				{ heading }
			</h1>
			{ lede ? (
				<p className="blocklane-pro-page__subtitle">{ lede }</p>
			) : null }
		</header>
	);
};
