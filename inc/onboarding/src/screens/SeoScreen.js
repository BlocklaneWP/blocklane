/**
 * SEO screen — the shell.
 *
 * Three tabs: Overview (status cards + content coverage), Settings
 * (site-level SEO settings), and Content (per-page SEO across the whole
 * site). Each tab is self-contained in
 * ./seo/ and fetches its own data; this shell owns tab state, the disabled
 * notice, and the details card: the shared About (seo/about.js, design spec
 * Amendment AN 1), or the Edit SEO panel while a row is being edited.
 */

import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';

import {
	BlocklanePage,
	useDetailsPreference,
} from '../components/BlocklanePage';
import { useTabShell } from '../components/use-tab-shell';
import { isToolScreenOn } from '../components/tool-screens';
import { OverviewTab } from './seo/OverviewTab';
import { SettingsTab } from './seo/SettingsTab';
import { ContentTab } from './seo/ContentTab';
import { EditSeoPanel } from './seo/EditSeoPanel';
import { AboutSeo } from './seo/about';
import { seoCopy } from '../../../shared/seo-copy';

export const SeoScreen = ( { initialTab } ) => {
	const [ helpOpen, setHelpOpen ] = useDetailsPreference( 'seo' );
	// The Content tab's Edit SEO panel: { item, onSaved }. It takes the
	// details card's place while it is open (one right-hand card at a time),
	// and the About card comes back untouched when it closes.
	const [ editing, setEditing ] = useState( null );
	const editorColumn = useRef( null );

	// The shared tab shell (components/use-tab-shell.js): anchor tabs, URL
	// sync, Space-key restore, and one-shot Settings-accordion targeting
	// for the Overview CTAs ('visibility', 'verification'). Leaving a tab
	// dismisses the row editor along with it (onSwitch).
	const shell = useTabShell( {
		screen: 'seo',
		initialTab,
		onSwitch: () => setEditing( null ),
	} );
	const { tab, targetPanel: settingsPanel, switchTab } = shell;

	// Stacked layout (the CSS breakpoint at 1250px): the drawer renders below
	// the whole table, out of sight — so when a row starts editing (or the
	// edited row changes), bring the panel into view and move focus into it.
	// The classic Quick-Edit affordance, without reaching into DataViews'
	// rows. Desktop needs neither: the drawer is beside the table and sticky.
	useEffect( () => {
		if ( ! editing || ! editorColumn.current ) {
			return;
		}
		if ( ! window.matchMedia( '(max-width: 781px)' ).matches ) {
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
			<BlocklanePage
				screen="seo"
				disabled={ {
					slug: 'seo',
					// What off really leaves: inc/seo/runtime.php returns
					// at the toggle before it hooks anything (no tag,
					// sitemap filter, llms.txt or IndexNow), and nothing
					// deletes the blocklane_pro_seo option or the post
					// meta (SK3; the SEO About copy record, P1, approved).
					body: __(
						"Turning it off stops its search tags, sitemap changes, llms.txt and IndexNow on your site. Your settings and each page's search details are kept for when you turn it back on.",
						'blocklane'
					),
				} }
			/>
		);
	}

	return (
		<BlocklanePage
			screen="seo"
			tabs={ {
				label: __( 'SEO sections', 'blocklane' ),
				shell,
			} }
			hasPadding={ tab !== 'content' }
			details={
				editing
					? {
							header: false,
							// The card's region name while it edits a row
							// (BlocklanePage labels the aside with it).
							title: seoCopy().editSeo,
							content: (
								<div
									className="blocklane-seo-editor"
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
							),
						}
					: { content: <AboutSeo /> }
			}
			detailsOpen={ !! editing || helpOpen }
			onDetailsOpenChange={ ( next ) => {
				if ( editing && ! next ) {
					setEditing( null );
					return;
				}
				setHelpOpen( next );
			} }
		>
			<div className="blocklane-seo">
				{ tab === 'overview' && (
					<OverviewTab onNavigate={ switchTab } />
				) }
				{ tab === 'settings' && (
					<SettingsTab initialPanel={ settingsPanel } />
				) }
				{ tab === 'content' && (
					<ContentTab
						editingId={ editing ? editing.item.id : null }
						onStopEditing={ () => setEditing( null ) }
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
			</div>
		</BlocklanePage>
	);
};
