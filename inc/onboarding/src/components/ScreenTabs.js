/**
 * ScreenTabs / ScreenTabPanel — the dashboard's tab row and the panel it
 * labels, one markup for every tabbed screen (SEO, Forms, Content Types,
 * Extensions, Advanced). State and behavior come from useTabShell; this
 * file owns only the markup and the ARIA wiring — tab ids, the selected
 * tab's aria-controls, the panel's role and aria-labelledby — so an a11y
 * fix lands on every screen at once instead of drifting between copies.
 *
 * Tabs render as real anchors: ⌘/middle-click opens the tab's own URL, a
 * plain click switches client-side (the shell's onTabClick), and Space
 * activates like the ARIA tabs pattern expects (onTabKeyDown); both
 * handlers are the shell's stable functions and read the tab's slug off
 * the anchor's data-slug attribute. The anchors are
 * plain <a> elements styled with core's components-button class rather
 * than the Button component: Button strips aria-selected (with
 * aria-pressed and aria-checked) whenever it renders an anchor, so a
 * Button-rendered tab never exposed its selected state to assistive tech.
 * Only the active panel is mounted, so only the selected tab carries
 * aria-controls — a reference to an unmounted id is invalid. The screen
 * slug is validated (requireScreen): a typo throws inside the screen's
 * ErrorBoundary instead of producing a dangling aria-labelledby.
 */

import { requireScreen } from '../screens/registry';

const tabId = ( screen, slug ) => `blocklane-pro-tab-${ screen }-${ slug }`;
const panelId = ( screen, slug ) =>
	`blocklane-pro-tabpanel-${ screen }-${ slug }`;

/**
 * The tab row.
 *
 * @param {Object} props
 * @param {string} props.screen      Screen slug — namespaces the ids.
 * @param {string} props.label       aria-label for the tablist.
 * @param {Array}  props.tabs        [ { slug, label } ] in display order.
 * @param {Object} props.shell       The useTabShell return value.
 * @param {string} [props.className] Extra class on the tablist.
 */
export const ScreenTabs = ( { screen, label, tabs, shell, className } ) => {
	requireScreen( screen );
	return (
		<div
			role="tablist"
			aria-label={ label }
			className={
				'blocklane-pro-page__tabs' +
				( className ? ' ' + className : '' )
			}
		>
			{ tabs.map( ( t ) => {
				const isActive = shell.tab === t.slug;
				return (
					<a
						key={ t.slug }
						id={ tabId( screen, t.slug ) }
						data-slug={ t.slug }
						role="tab"
						href={ shell.tabHref( t.slug ) }
						aria-selected={ isActive }
						aria-controls={
							isActive ? panelId( screen, t.slug ) : undefined
						}
						className={
							'components-button blocklane-pro-page__tab' +
							( isActive ? ' is-active' : '' )
						}
						onClick={ shell.onTabClick }
						onKeyDown={ shell.onTabKeyDown }
					>
						{ t.label }
					</a>
				);
			} ) }
		</div>
	);
};

/**
 * The active tab's panel — labelled by its tab. Extra props (className,
 * onMouseLeave, …) pass through to the element.
 *
 * @param {Object}      props
 * @param {string}      props.screen   Screen slug — must match ScreenTabs.
 * @param {string}      props.slug     The active tab's slug.
 * @param {JSX.Element} props.children Panel content.
 */
export const ScreenTabPanel = ( { screen, slug, children, ...rest } ) => {
	requireScreen( screen );
	return (
		<div
			role="tabpanel"
			id={ panelId( screen, slug ) }
			aria-labelledby={ tabId( screen, slug ) }
			{ ...rest }
		>
			{ children }
		</div>
	);
};
