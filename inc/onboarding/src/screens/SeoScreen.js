/**
 * SEO screen — the shell.
 *
 * Three tabs, Jetpack-SEO-dashboard shaped: Overview (status cards +
 * content coverage), Settings (site-level SEO settings), and Content
 * (per-page SEO across the whole site). Each tab is self-contained in
 * ./seo/ and fetches its own data; this shell owns tab state, the disabled
 * notice, and the help panel. Per-page SEO also lives in the editor's SEO
 * sidebar — the Content tab edits the same post meta.
 */

import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';
import {
	Flex,
	FlexItem,
	__experimentalDivider as Divider,
} from '@wordpress/components';

import { HelpTab, useHelpPreference } from '../components/HelpTab';
import { ScreenHeader } from '../components/ScreenHeader';
import { ScreenTabs, ScreenTabPanel } from '../components/ScreenTabs';
import { useTabShell } from '../components/use-tab-shell';
import {
	ScreenDisabledNotice,
	isToolScreenOn,
} from '../components/ScreenDisabledNotice';
import { BlocklaneMark } from './seo/logo';
import { OverviewTab } from './seo/OverviewTab';
import { SettingsTab } from './seo/SettingsTab';
import { ContentTab } from './seo/ContentTab';
import { EditSeoPanel } from './seo/EditSeoPanel';

const TABS = [
	{ slug: 'overview', label: __( 'Overview', 'blocklane' ) },
	{ slug: 'content', label: __( 'Content', 'blocklane' ) },
	{ slug: 'settings', label: __( 'Settings', 'blocklane' ) },
];

export const SeoScreen = ( { initialTab } ) => {
	const [ helpOpen, setHelpOpen ] = useHelpPreference( 'seo' );
	// The Content tab's Edit SEO panel: { item, onSaved }. Its own column,
	// deliberately separate from the help sidebar — the help rail and panel
	// hide while it's open and come back untouched when it closes.
	const [ editing, setEditing ] = useState( null );
	const pageStart = useRef( null );
	const editorColumn = useRef( null );

	// The shared tab shell (components/use-tab-shell.js): anchor tabs, URL
	// sync, Space-key restore, and one-shot Settings-accordion targeting
	// for the Overview CTAs ('visibility', 'verification'). Leaving a tab
	// dismisses the row editor along with it (onSwitch).
	const shell = useTabShell( {
		screen: 'seo',
		param: 'seotab',
		tabs: TABS,
		defaultTab: 'overview',
		initialTab,
		onSwitch: () => setEditing( null ),
	} );
	const { tab, targetPanel: settingsPanel, switchTab } = shell;

	useEffect( () => {
		pageStart.current?.focus();
	}, [] );

	// Stacked layout (the CSS breakpoint at 1250px): the drawer renders below
	// the whole table, out of sight — so when a row starts editing (or the
	// edited row changes), bring the panel into view and move focus into it.
	// The classic Quick-Edit affordance, without reaching into DataViews'
	// rows. Desktop needs neither: the drawer is beside the table and sticky.
	useEffect( () => {
		if ( ! editing || ! editorColumn.current ) {
			return;
		}
		if ( ! window.matchMedia( '(max-width: 1250px)' ).matches ) {
			return;
		}
		editorColumn.current.focus( { preventScroll: true } );
		editorColumn.current.scrollIntoView( {
			behavior: 'smooth',
			block: 'start',
		} );
	}, [ editing ] );

	if ( ! isToolScreenOn( 'seo' ) ) {
		return (
			<ScreenDisabledNotice
				title={ __( 'SEO', 'blocklane' ) }
				body={ __(
					'Turn on Basic SEO to manage site-wide search settings here. Anything already configured keeps working on the front end.',
					'blocklane'
				) }
				slug="seo"
			/>
		);
	}

	return (
		<div className="blocklane-pro-seo-page">
			{ /* Everything except the Edit SEO drawer lives in this page column,
			     so the drawer — a root-level flex sibling — runs the full page
			     height and pushes the header and tabs left along with the
			     content, the Site Editor's full-height-drawer arrangement. */ }
			<div className="blocklane-pro-page">
				<ScreenHeader
					screen="seo"
					pageStart={ pageStart }
					icon={ <BlocklaneMark size={ 24 } /> }
				/>
				<ScreenTabs
					screen="seo"
					label={ __( 'SEO sections', 'blocklane' ) }
					tabs={ TABS }
					shell={ shell }
				/>

				<Flex
					align="stretch"
					gap="0"
					className={
						'blocklane-pro-extensions blocklane-pro-seo-screen' +
						( helpOpen ? '' : ' is-help-closed' ) +
						( editing ? ' is-editing-seo' : '' )
					}
				>
					<FlexItem className="blocklane-pro-extensions__sidebar">
						<ScreenTabPanel
							screen="seo"
							slug={ tab }
							className={
								'blocklane-pro-extensions__section' +
								( tab === 'content' ? ' is-full-bleed' : '' )
							}
						>
							{ tab === 'overview' && (
								<OverviewTab onNavigate={ switchTab } />
							) }
							{ tab === 'settings' && (
								<SettingsTab initialPanel={ settingsPanel } />
							) }
							{ tab === 'content' && (
								<ContentTab
									onEdit={ ( item, onSaved, stepper, site ) =>
										setEditing( {
											item,
											onSaved,
											stepper,
											site,
										} )
									}
								/>
							) }
						</ScreenTabPanel>
					</FlexItem>

					<HelpTab
						isOpen={ helpOpen }
						onToggle={ () => setHelpOpen( ! helpOpen ) }
						panelId="blocklane-pro-help-panel-seo"
					/>
					<FlexItem
						className="blocklane-pro-extensions__preview"
						id="blocklane-pro-help-panel-seo"
						aria-hidden={ ! helpOpen }
					>
						{ ! editing && (
							<section className="blocklane-pro-extensions__preview-content">
								<p>
									<strong>
										{ __( 'What is this?', 'blocklane' ) }
									</strong>
								</p>
								<p>
									{ __(
										'The site-wide half of Basic SEO: an overview of your search state, indexing and the XML sitemap, verification, Organization schema, your homepage description — and every page’s SEO in one table. Nothing here needs an extra SEO plugin; it’s the baseline every site should ship with.',
										'blocklane'
									) }
								</p>
								<Divider />
								<p>
									<strong>
										{ __(
											'Per-page SEO lives in the editor too',
											'blocklane'
										) }
									</strong>
								</p>
								<p>
									{ __(
										'The Content tab edits the same fields as the editor’s SEO sidebar — open any page and click the SEO icon in the editor’s top bar, or edit right here from the table.',
										'blocklane'
									) }
								</p>
								<Divider />
								<p>
									<strong>
										{ __(
											'Plays fair with SEO plugins',
											'blocklane'
										) }
									</strong>
								</p>
								<p>
									{ __(
										'If a dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress…) is active, Blocklane stops printing its own tags automatically so nothing is doubled. Your settings are kept for when you switch back.',
										'blocklane'
									) }
								</p>
							</section>
						) }
					</FlexItem>
				</Flex>
			</div>

			{ editing && (
				<div
					className="blocklane-pro-seo-screen__editor-column"
					ref={ editorColumn }
					tabIndex={ -1 }
				>
					<EditSeoPanel
						key={ editing.item.id }
						item={ editing.item }
						onClose={ () => setEditing( null ) }
						onSaved={ editing.onSaved }
						stepper={ editing.stepper }
						site={ editing.site }
					/>
				</div>
			) }
		</div>
	);
};
