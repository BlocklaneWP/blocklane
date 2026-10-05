/**
 * SEO screen — Settings tab.
 *
 * Site-level SEO settings in accordion sections: site visibility (search
 * indexing + core sitemap), search-engine verification codes, Organization
 * schema, and a live search/social preview of the homepage. Wired to
 * GET/POST blocklane-pro/v1/seo. Search-engine visibility mirrors core's
 * blog_public — the same option the Site Visibility screen manages.
 * The homepage description itself is per-page SEO (Content tab / editor
 * sidebar) — there's deliberately no site-level override here.
 *
 * All settings live in ONE `settings` object keyed exactly like the REST
 * payload, edited through setField() and sent back wholesale — so a new
 * setting is one PHP key plus one control, with no per-field plumbing to
 * forget (a key wired into load but missed in save would otherwise silently
 * never persist).
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
// media-utils' MediaUpload is the concrete wp.media implementation, so it
// works outside the block editor (same import the dynamic-values screen uses).
import { MediaUpload } from '@wordpress/media-utils';
import { closeSmall } from '@wordpress/icons';
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Flex,
	FlexItem,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

import { seo as seoApi } from '../../api/client';
import {
	errorMessage,
	StatusChip,
	SearchPreview,
	VERIFICATION_SERVICES,
} from './shared';

const WEEK_DAYS = [
	{ key: 'monday', label: __( 'Monday', 'blocklane' ) },
	{ key: 'tuesday', label: __( 'Tuesday', 'blocklane' ) },
	{ key: 'wednesday', label: __( 'Wednesday', 'blocklane' ) },
	{ key: 'thursday', label: __( 'Thursday', 'blocklane' ) },
	{ key: 'friday', label: __( 'Friday', 'blocklane' ) },
	{ key: 'saturday', label: __( 'Saturday', 'blocklane' ) },
	{ key: 'sunday', label: __( 'Sunday', 'blocklane' ) },
];

/**
 * One image picker: bordered thumb that doubles as the Replace button,
 * Replace + Remove beside it, a Select button when empty. Used by the
 * Organization logo and the default share image — one implementation, so
 * the two pickers can't drift.
 *
 * @param {Object}                            props              Component props.
 * @param {number}                            props.id           Attachment ID (0 = none).
 * @param {string}                            props.url          Preview URL for the thumb.
 * @param {(id: number, url: string) => void} props.onChange     ( id, url ) on select/remove.
 * @param {string}                            props.selectLabel  Empty-state button text.
 * @param {string}                            props.replaceLabel aria-label for the thumb button.
 * @param {string}                            props.removeLabel  Remove button label.
 * @return {JSX.Element} The picker.
 */
const ImageField = ( {
	id,
	url,
	onChange,
	selectLabel,
	replaceLabel,
	removeLabel,
} ) => (
	<MediaUpload
		allowedTypes={ [ 'image' ] }
		value={ id }
		onSelect={ ( media ) =>
			onChange(
				media?.id || 0,
				media?.sizes?.full?.url || media?.url || ''
			)
		}
		render={ ( { open } ) =>
			id && url ? (
				<Flex
					justify="flex-start"
					gap="2"
					className="blocklane-pro-seo-screen__logo-row"
				>
					<button
						type="button"
						className="blocklane-pro-seo-screen__logo-thumb"
						onClick={ open }
						aria-label={ replaceLabel }
					>
						<img src={ url } alt="" />
					</button>
					<Button variant="secondary" onClick={ open }>
						{ __( 'Replace', 'blocklane' ) }
					</Button>
					<Button
						icon={ closeSmall }
						label={ removeLabel }
						onClick={ () => onChange( 0, '' ) }
					/>
				</Flex>
			) : (
				<Button
					variant="secondary"
					__next40pxDefaultSize
					onClick={ open }
				>
					{ selectLabel }
				</Button>
			)
		}
	/>
);

// Homepage previews: Google result + a social share card. The description
// shown is the tagline — the fallback a posts homepage emits; a static front
// page's own SEO description is set per-page in the Content tab.
const HomePreviews = ( { site } ) => {
	const host = ( () => {
		try {
			return new URL( site.url ).host;
		} catch ( e ) {
			return site.url;
		}
	} )();

	const text =
		site.tagline ||
		__(
			'Search engines will pick a snippet from the homepage content.',
			'blocklane'
		);

	return (
		<div className="blocklane-pro-seo-screen__previews">
			<div className="blocklane-pro-seo-screen__preview-block">
				<div className="blocklane-pro-seo-screen__preview-caption">
					{ __( 'Google search result', 'blocklane' ) }
				</div>
				<SearchPreview
					title={ site.title }
					description={ text }
					crumb={ host }
					siteTitle={ site.title }
					siteIcon={ site.icon }
				/>
			</div>

			<div className="blocklane-pro-seo-screen__preview-block">
				<div className="blocklane-pro-seo-screen__preview-caption">
					{ __( 'Social share card', 'blocklane' ) }
				</div>
				<div className="blocklane-pro-seo-screen__card">
					<div className="blocklane-pro-seo-screen__card-image">
						{ site.ogImage || site.logo || site.icon ? (
							<img
								src={ site.ogImage || site.logo || site.icon }
								alt=""
								loading="lazy"
							/>
						) : (
							<span aria-hidden="true">🔗</span>
						) }
					</div>
					<div className="blocklane-pro-seo-screen__card-body">
						<div className="blocklane-pro-seo-screen__card-host">
							{ host }
						</div>
						<div className="blocklane-pro-seo-screen__card-title">
							{ site.title }
						</div>
						<div className="blocklane-pro-seo-screen__card-description">
							{ text }
						</div>
					</div>
				</div>
			</div>
		</div>
	);
};

/**
 * @param {Object}      props              Component props.
 * @param {string|null} props.initialPanel Accordion to open and scroll to on
 *                                         mount ('visibility'|'verification')
 *                                         — set when an Overview CTA targets
 *                                         a specific section.
 */
export const SettingsTab = ( { initialPanel = null } ) => {
	const [ loading, setLoading ] = useState( true );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );

	// The whole settings map, keyed like the REST payload. Null until the
	// first GET lands — the form never renders from guessed defaults.
	const [ settings, setSettings ] = useState( null );
	const setField = ( key ) => ( value ) =>
		setSettings( ( current ) => ( { ...current, [ key ]: value } ) );

	const [ searchVisible, setSearchVisible ] = useState( true );
	const [ siteLocked, setSiteLocked ] = useState( false );

	// When an Overview CTA targets a section, bring it into view once the
	// settings have loaded — the accordions all start collapsed, so without
	// this the CTA would land on a wall of closed headers. The targeted panel
	// itself starts open via its initialOpen below.
	useEffect( () => {
		if ( loading || ! initialPanel ) {
			return;
		}
		document
			.querySelector(
				`.blocklane-pro-seo-screen__panel-${ initialPanel }`
			)
			?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}, [ loading, initialPanel ] );

	const [ pages, setPages ] = useState( [] );
	const [ sitemapTypes, setSitemapTypes ] = useState( [] );
	const [ sitemapTaxes, setSitemapTaxes ] = useState( [] );
	// Preview URLs for the two image pickers — ephemeral (the IDs are the
	// stored values; the URLs re-resolve from the payload on load/save).
	const [ orgLogoUrl, setOrgLogoUrl ] = useState( '' );
	const [ ogImageUrl, setOgImageUrl ] = useState( '' );
	// Import section: sources with data (null until detected), the slug
	// currently importing, and the last run's counters for the notice.
	const [ importSources, setImportSources ] = useState( null );
	const [ importBusy, setImportBusy ] = useState( '' );
	const [ importResult, setImportResult ] = useState( null );
	const [ importError, setImportError ] = useState( '' );
	const [ site, setSite ] = useState( {
		title: '',
		tagline: '',
		url: '',
		icon: '',
		logo: '',
		sitemapUrl: '',
	} );

	const applyPayload = ( data ) => {
		setSettings( data?.settings || {} );
		setSearchVisible( data?.search_visible !== false );
		setSiteLocked( !! data?.site_locked );
		setPages( Array.isArray( data?.pages ) ? data.pages : [] );
		setSitemapTypes(
			Array.isArray( data?.sitemapTypes ) ? data.sitemapTypes : []
		);
		setSitemapTaxes(
			Array.isArray( data?.sitemapTaxes ) ? data.sitemapTaxes : []
		);
		setOrgLogoUrl( data?.site?.orgLogo || '' );
		setOgImageUrl( data?.site?.ogImage || '' );
		if ( data?.site ) {
			setSite( data.site );
		}
	};

	useEffect( () => {
		seoApi
			.get()
			.then( applyPayload )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setLoading( false ) );

		seoApi
			.importSources()
			.then( ( d ) => setImportSources( d?.sources || [] ) )
			.catch( () => setImportSources( [] ) );
	}, [] );

	const runImport = ( source ) => {
		setImportBusy( source );
		setImportError( '' );
		setImportResult( null );
		seoApi
			.runImport( source )
			.then( ( counts ) => setImportResult( { source, counts } ) )
			.catch( ( err ) => setImportError( errorMessage( err ) ) )
			.finally( () => setImportBusy( '' ) );
	};

	const save = () => {
		// A day with only one end filled would be silently dropped by the
		// server — surface it instead of losing the half-entered time under
		// a success notice.
		if ( settings.local_business ) {
			const half = WEEK_DAYS.filter( ( { key } ) => {
				const row = settings.local_hours?.[ key ] || {};
				return !! row.open !== !! row.close;
			} );
			if ( half.length ) {
				setError(
					sprintf(
						/* translators: %s: weekday name(s). */
						__(
							'Opening hours for %s need both times — set an opening and a closing time, or clear both.',
							'blocklane'
						),
						half.map( ( d ) => d.label ).join( ', ' )
					)
				);
				return;
			}
		}

		// The IndexNow key is server-minted — everything else goes back as-is.
		const { indexnow_key: omitted, ...payload } = settings;

		setIsBusy( true );
		setError( '' );
		setSaved( false );
		seoApi
			.save( { settings: payload, search_visible: searchVisible } )
			.then( ( data ) => {
				applyPayload( data );
				setSaved( true );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setIsBusy( false ) );
	};

	if ( loading ) {
		return (
			<div className="blocklane-pro-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error ||
					__( 'Could not load the SEO settings.', 'blocklane' ) }
			</Notice>
		);
	}

	const verification = settings.verification || {};
	const orgProfiles = Array.isArray( settings.org_profiles )
		? settings.org_profiles
		: [];
	const localHours = settings.local_hours || {};

	const setLocalHour = ( day, part ) => ( value ) =>
		setField( 'local_hours' )( {
			...localHours,
			[ day ]: { ...( localHours[ day ] || {} ), [ part ]: value },
		} );

	const verificationSet = VERIFICATION_SERVICES.filter(
		( { key } ) => verification[ key ]
	).length;
	const orgSet = [
		settings.org_name,
		settings.org_description,
		orgProfiles.length,
	].filter( Boolean ).length;

	return (
		<div className="blocklane-pro-seo-screen__sections">
			{ error ? (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) : null }
			{ saved ? (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'SEO settings saved.', 'blocklane' ) }
				</Notice>
			) : null }

			{ /* Collapsed like every other section — except when the lock
			     warning inside needs surfacing, or an Overview CTA targeted
			     it. siteLocked is settled before the panels first render
			     (same fetch that ends `loading`), so initialOpen is safe. */ }
			<PanelBody
				title={ __( 'Site visibility', 'blocklane' ) }
				className="blocklane-pro-seo-screen__panel-visibility"
				initialOpen={ siteLocked || initialPanel === 'visibility' }
			>
				{ siteLocked && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Site Visibility has this site locked, so crawlers can’t reach it right now regardless of these settings.',
							'blocklane'
						) }
					</Notice>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Allow search engines to index this site',
						'blocklane'
					) }
					help={ __(
						'The same switch as Settings → Reading and the Site Visibility screen. Turning it off asks search engines to skip the whole site — use only for staging or pre-launch.',
						'blocklane'
					) }
					checked={ searchVisible }
					onChange={ setSearchVisible }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Add canonical URLs on archives',
						'blocklane'
					) }
					help={ __(
						'Points search engines at the clean URL for category, tag, author, and date pages (paginated pages keep their own). Posts and pages already get one from WordPress.',
						'blocklane'
					) }
					checked={ settings.archive_canonicals !== false }
					onChange={ setField( 'archive_canonicals' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Hide author archives from search engines',
						'blocklane'
					) }
					help={ __(
						'Author pages mostly repeat your post lists, and their URLs expose usernames. Search engines still follow the links on them.',
						'blocklane'
					) }
					checked={ !! settings.noindex_author_archives }
					onChange={ setField( 'noindex_author_archives' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Hide date archives from search engines',
						'blocklane'
					) }
					help={ __(
						'Monthly and yearly archives repeat the same posts under many URLs. Search engines still follow the links on them.',
						'blocklane'
					) }
					checked={ !! settings.noindex_date_archives }
					onChange={ setField( 'noindex_date_archives' ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Sitemaps', 'blocklane' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Generate an XML sitemap', 'blocklane' ) }
					help={
						settings.sitemap !== false && site.sitemapUrl ? (
							<ExternalLink href={ site.sitemapUrl }>
								{ __( 'View the sitemap', 'blocklane' ) }
							</ExternalLink>
						) : (
							__(
								'WordPress builds the sitemap (wp-sitemap.xml) automatically from your published content.',
								'blocklane'
							)
						)
					}
					checked={ settings.sitemap !== false }
					onChange={ setField( 'sitemap' ) }
				/>
				{ settings.sitemap !== false && (
					<div className="blocklane-pro-seo-screen__sitemap-includes">
						<span className="blocklane-pro-seo-screen__profiles-label">
							{ __( 'Include in the sitemap', 'blocklane' ) }
						</span>
						<p className="blocklane-pro-seo-screen__section-intro">
							{ __(
								'Anything unchecked stays out of the XML sitemap. Individual items are hidden per page with “Hide from search engines”.',
								'blocklane'
							) }
						</p>
						{ sitemapTypes.map( ( { slug, label } ) => (
							<CheckboxControl
								key={ slug }
								__nextHasNoMarginBottom
								label={ label }
								checked={
									! (
										settings.sitemap_exclude_types || []
									).includes( slug )
								}
								onChange={ ( checked ) =>
									setField( 'sitemap_exclude_types' )(
										checked
											? (
													settings.sitemap_exclude_types ||
													[]
												).filter( ( t ) => t !== slug )
											: [
													...( settings.sitemap_exclude_types ||
														[] ),
													slug,
												]
									)
								}
							/>
						) ) }
						{ sitemapTaxes.map( ( { slug, label } ) => (
							<CheckboxControl
								key={ slug }
								__nextHasNoMarginBottom
								label={ label }
								checked={
									! (
										settings.sitemap_exclude_taxonomies ||
										[]
									).includes( slug )
								}
								onChange={ ( checked ) =>
									setField( 'sitemap_exclude_taxonomies' )(
										checked
											? (
													settings.sitemap_exclude_taxonomies ||
													[]
												).filter( ( t ) => t !== slug )
											: [
													...( settings.sitemap_exclude_taxonomies ||
														[] ),
													slug,
												]
									)
								}
							/>
						) ) }
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __( 'Author archives', 'blocklane' ) }
							help={ __(
								'Author pages are often thin content, and their URLs expose usernames.',
								'blocklane'
							) }
							checked={ settings.sitemap_authors !== false }
							onChange={ setField( 'sitemap_authors' ) }
						/>
					</div>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'HTML sitemap', 'blocklane' ) }
					help={ __(
						'A human-readable list of your published content — pages as a tree, other types grouped — added to a page of your choice.',
						'blocklane'
					) }
					checked={ !! settings.html_sitemap }
					onChange={ setField( 'html_sitemap' ) }
				/>
				{ !! settings.html_sitemap && (
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Sitemap page', 'blocklane' ) }
						help={
							settings.html_sitemap_page &&
							pages.find(
								( p ) => p.id === settings.html_sitemap_page
							) ? (
								<ExternalLink
									href={
										pages.find(
											( p ) =>
												p.id ===
												settings.html_sitemap_page
										).url
									}
								>
									{ __(
										'View the sitemap page',
										'blocklane'
									) }
								</ExternalLink>
							) : (
								__(
									'The sitemap renders after the page’s own content.',
									'blocklane'
								)
							)
						}
						value={ String( settings.html_sitemap_page || 0 ) }
						options={ [
							{
								label: __( 'Select a page…', 'blocklane' ),
								value: '0',
							},
							...pages.map( ( p ) => ( {
								label: p.title,
								value: String( p.id ),
							} ) ),
						] }
						onChange={ ( v ) =>
							setField( 'html_sitemap_page' )( Number( v ) || 0 )
						}
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'llms.txt for AI assistants', 'blocklane' ) }
					help={
						settings.llms_txt ? (
							<>
								{ __(
									'A curated index of your published content, so AI crawlers and assistants get a map instead of scraping menus.',
									'blocklane'
								) }{ ' ' }
								<ExternalLink href={ `${ site.url }llms.txt` }>
									{ __( 'View llms.txt', 'blocklane' ) }
								</ExternalLink>
							</>
						) : (
							__(
								'Serves a curated index of your published content at /llms.txt, so AI crawlers and assistants get a map instead of scraping menus.',
								'blocklane'
							)
						)
					}
					checked={ !! settings.llms_txt }
					onChange={ setField( 'llms_txt' ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Search titles', 'blocklane' ) }
				initialOpen={ false }
			>
				<p className="blocklane-pro-seo-screen__section-intro">
					{ __(
						'How titles appear in search results and the browser tab for posts and pages without a custom search title. Leave the format blank to keep WordPress’s own ("Title – Site").',
						'blocklane'
					) }
				</p>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Title format', 'blocklane' ) }
					help={ sprintf(
						/* translators: 1: the page-title token, 2: the site-title token, 3: the tagline token, 4: the separator token — the title format's own syntax, inserted untranslated. */
						__(
							'Tokens: %1$s (page title), %2$s (site title), %3$s, %4$s (separator).',
							'blocklane'
						),
						'%title%',
						'%site%',
						'%tagline%',
						'%sep%'
					) }
					placeholder="%title% %sep% %site%"
					value={ settings.title_format || '' }
					onChange={ setField( 'title_format' ) }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Separator', 'blocklane' ) }
					help={
						/* translators: %sep% is a literal token, not a placeholder. */
						__(
							'Used for %sep% and everywhere WordPress joins title parts (archives, search, the front page).',
							'blocklane'
						)
					}
					value={ settings.title_separator || '' }
					options={ [
						{
							label: __( 'Default (–)', 'blocklane' ),
							value: '',
						},
						{ label: '-', value: '-' },
						{ label: '—', value: '—' },
						{ label: '|', value: '|' },
						{ label: '•', value: '•' },
						{ label: '·', value: '·' },
						{ label: '»', value: '»' },
						{ label: '/', value: '/' },
					] }
					onChange={ setField( 'title_separator' ) }
				/>
				<p className="blocklane-pro-seo-screen__title-preview">
					<span className="blocklane-pro-seo-screen__preview-caption">
						{ __( 'Preview', 'blocklane' ) }
					</span>
					<span className="blocklane-pro-seo-screen__serp-title">
						{ ( settings.title_format || '%title% %sep% %site%' )
							.replaceAll(
								'%title%',
								__( 'Sample Page', 'blocklane' )
							)
							.replaceAll( '%site%', site.title )
							.replaceAll( '%tagline%', site.tagline )
							.replaceAll(
								'%sep%',
								settings.title_separator || '–'
							)
							.replace( /\s+/g, ' ' )
							.trim() }
					</span>
				</p>
			</PanelBody>

			<PanelBody
				title={ __( 'Search & social previews', 'blocklane' ) }
				initialOpen={ false }
			>
				<div className="blocklane-pro-seo-screen__logo">
					<span className="blocklane-pro-seo-screen__profiles-label">
						{ __( 'Default share image', 'blocklane' ) }
					</span>
					<p className="blocklane-pro-seo-screen__section-intro">
						{ __(
							'The card image links unfurl with when the shared page has no featured image of its own.',
							'blocklane'
						) }
					</p>
					<ImageField
						id={ settings.default_og_image_id || 0 }
						url={ ogImageUrl }
						onChange={ ( id, url ) => {
							setField( 'default_og_image_id' )( id );
							setOgImageUrl( url );
						} }
						selectLabel={ __( 'Select image', 'blocklane' ) }
						replaceLabel={ __(
							'Replace share image',
							'blocklane'
						) }
						removeLabel={ __( 'Remove share image', 'blocklane' ) }
					/>
				</div>
				<p className="blocklane-pro-seo-screen__section-intro">
					{ __(
						'How your homepage can look in search results and when shared. The description comes from your front page’s own SEO (Content tab) or, for a posts homepage, the site tagline.',
						'blocklane'
					) }
				</p>
				<HomePreviews site={ { ...site, ogImage: ogImageUrl } } />
			</PanelBody>

			<PanelBody
				title={ __( 'Organization schema', 'blocklane' ) }
				initialOpen={ false }
			>
				<p className="blocklane-pro-seo-screen__section-intro">
					{ __(
						'Structured data describing who is behind the site. Blank fields fall back to your site title, tagline, and logo.',
						'blocklane'
					) }{ ' ' }
					<StatusChip tone={ orgSet ? 'positive' : 'neutral' }>
						{ orgSet
							? `${ orgSet } / 3`
							: __( 'Defaults', 'blocklane' ) }
					</StatusChip>
				</p>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Organization name', 'blocklane' ) }
					placeholder={ site.title }
					value={ settings.org_name || '' }
					onChange={ setField( 'org_name' ) }
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					rows={ 2 }
					label={ __( 'Description', 'blocklane' ) }
					placeholder={ site.tagline }
					value={ settings.org_description || '' }
					onChange={ setField( 'org_description' ) }
				/>
				<div className="blocklane-pro-seo-screen__logo">
					<span className="blocklane-pro-seo-screen__profiles-label">
						{ __( 'Logo', 'blocklane' ) }
					</span>
					<p className="blocklane-pro-seo-screen__section-intro">
						{ __(
							'Shown next to your organization in search results. Leave empty to use the site logo.',
							'blocklane'
						) }
					</p>
					<ImageField
						id={ settings.org_logo_id || 0 }
						url={ orgLogoUrl }
						onChange={ ( id, url ) => {
							setField( 'org_logo_id' )( id );
							setOrgLogoUrl( url );
						} }
						selectLabel={ __( 'Select logo', 'blocklane' ) }
						replaceLabel={ __( 'Replace logo', 'blocklane' ) }
						removeLabel={ __( 'Remove logo', 'blocklane' ) }
					/>
				</div>
				<div className="blocklane-pro-seo-screen__profiles">
					<span className="blocklane-pro-seo-screen__profiles-label">
						{ __( 'Social profiles', 'blocklane' ) }
					</span>
					<p className="blocklane-pro-seo-screen__section-intro">
						{ __(
							'Links to this organization’s official profiles (Facebook, X, LinkedIn, …).',
							'blocklane'
						) }
					</p>
					{ orgProfiles.map( ( url, i ) => (
						<Flex
							key={ i }
							gap="2"
							className="blocklane-pro-seo-screen__profile-row"
						>
							<FlexItem isBlock>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Profile URL', 'blocklane' ) }
									hideLabelFromVision
									placeholder="https://"
									value={ url }
									onChange={ ( v ) =>
										setField( 'org_profiles' )(
											orgProfiles.map( ( u, j ) =>
												j === i ? v : u
											)
										)
									}
								/>
							</FlexItem>
							<FlexItem>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () =>
										setField( 'org_profiles' )(
											orgProfiles.filter(
												( u, j ) => j !== i
											)
										)
									}
								>
									{ __( 'Remove', 'blocklane' ) }
								</Button>
							</FlexItem>
						</Flex>
					) ) }
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () =>
							setField( 'org_profiles' )( [ ...orgProfiles, '' ] )
						}
					>
						{ __( 'Add profile', 'blocklane' ) }
					</Button>
				</div>
				<div className="blocklane-pro-seo-screen__local">
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'This is a local business', 'blocklane' ) }
						help={ __(
							'Adds the address, phone number, and opening hours to your structured data — the details search engines can show in local and map results.',
							'blocklane'
						) }
						checked={ !! settings.local_business }
						onChange={ setField( 'local_business' ) }
					/>
					{ !! settings.local_business && (
						<>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Street address', 'blocklane' ) }
								value={ settings.local_address || '' }
								onChange={ setField( 'local_address' ) }
							/>
							<Flex
								gap="2"
								align="flex-start"
								className="blocklane-pro-seo-screen__local-row"
							>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'City', 'blocklane' ) }
										value={ settings.local_city || '' }
										onChange={ setField( 'local_city' ) }
									/>
								</FlexItem>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __(
											'State / Region',
											'blocklane'
										) }
										value={ settings.local_region || '' }
										onChange={ setField( 'local_region' ) }
									/>
								</FlexItem>
							</Flex>
							<Flex
								gap="2"
								align="flex-start"
								className="blocklane-pro-seo-screen__local-row"
							>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __(
											'Postal code',
											'blocklane'
										) }
										value={
											settings.local_postal_code || ''
										}
										onChange={ setField(
											'local_postal_code'
										) }
									/>
								</FlexItem>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'Country', 'blocklane' ) }
										value={ settings.local_country || '' }
										onChange={ setField( 'local_country' ) }
									/>
								</FlexItem>
							</Flex>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								type="tel"
								label={ __( 'Phone', 'blocklane' ) }
								value={ settings.local_phone || '' }
								onChange={ setField( 'local_phone' ) }
							/>
							<div className="blocklane-pro-seo-screen__hours">
								<span className="blocklane-pro-seo-screen__profiles-label">
									{ __( 'Opening hours', 'blocklane' ) }
								</span>
								<p className="blocklane-pro-seo-screen__section-intro">
									{ __(
										'Leave a day blank if you’re closed.',
										'blocklane'
									) }
								</p>
								{ WEEK_DAYS.map( ( { key, label } ) => (
									<Flex
										key={ key }
										gap="2"
										align="center"
										justify="flex-start"
										className="blocklane-pro-seo-screen__hours-row"
									>
										<FlexItem className="blocklane-pro-seo-screen__hours-day">
											{ label }
										</FlexItem>
										<FlexItem>
											<TextControl
												__nextHasNoMarginBottom
												type="time"
												label={ sprintf(
													/* translators: %s: weekday name. */
													__(
														'%s opens at',
														'blocklane'
													),
													label
												) }
												hideLabelFromVision
												value={
													localHours[ key ]?.open ||
													''
												}
												onChange={ setLocalHour(
													key,
													'open'
												) }
											/>
										</FlexItem>
										<span aria-hidden="true">–</span>
										<FlexItem>
											<TextControl
												__nextHasNoMarginBottom
												type="time"
												label={ sprintf(
													/* translators: %s: weekday name. */
													__(
														'%s closes at',
														'blocklane'
													),
													label
												) }
												hideLabelFromVision
												value={
													localHours[ key ]?.close ||
													''
												}
												onChange={ setLocalHour(
													key,
													'close'
												) }
											/>
										</FlexItem>
									</Flex>
								) ) }
							</div>
							<Flex
								gap="2"
								align="flex-start"
								className="blocklane-pro-seo-screen__local-row"
							>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'Latitude', 'blocklane' ) }
										placeholder="35.7796"
										value={ settings.local_latitude || '' }
										onChange={ setField(
											'local_latitude'
										) }
									/>
								</FlexItem>
								<FlexItem isBlock>
									<TextControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'Longitude', 'blocklane' ) }
										placeholder="-78.6382"
										value={ settings.local_longitude || '' }
										onChange={ setField(
											'local_longitude'
										) }
									/>
								</FlexItem>
							</Flex>
							<p className="blocklane-pro-seo-screen__section-intro">
								{ __(
									'Coordinates are optional — they pin the business on the map when search engines can’t place the address.',
									'blocklane'
								) }
							</p>
						</>
					) }
				</div>
			</PanelBody>

			<PanelBody
				title={ __( 'Site verification', 'blocklane' ) }
				className="blocklane-pro-seo-screen__panel-verification"
				initialOpen={ initialPanel === 'verification' }
			>
				<p className="blocklane-pro-seo-screen__section-intro">
					{ __(
						'Paste the verification code — or the whole meta tag — each service gives you. The matching tag is printed on your homepage.',
						'blocklane'
					) }{ ' ' }
					<StatusChip
						tone={ verificationSet ? 'positive' : 'neutral' }
					>
						{ verificationSet
							? `${ verificationSet } / ${ VERIFICATION_SERVICES.length }`
							: __( 'Not set', 'blocklane' ) }
					</StatusChip>
				</p>
				{ VERIFICATION_SERVICES.map( ( { key, label } ) => (
					<TextControl
						key={ key }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ label }
						value={ verification[ key ] || '' }
						onChange={ ( v ) =>
							setField( 'verification' )( {
								...verification,
								[ key ]: v,
							} )
						}
						className="blocklane-pro-seo-screen__verification-field"
					/>
				) ) }
			</PanelBody>

			<PanelBody
				title={ __( 'IndexNow', 'blocklane' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Notify search engines of content changes',
						'blocklane'
					) }
					help={
						settings.indexnow && settings.indexnow_key ? (
							<>
								{ __(
									'Bing, Yandex, and other IndexNow engines are pinged the moment content is published, updated, or removed.',
									'blocklane'
								) }{ ' ' }
								<ExternalLink
									href={ `${ site.url }${ settings.indexnow_key }.txt` }
								>
									{ __( 'View the key file', 'blocklane' ) }
								</ExternalLink>
							</>
						) : (
							__(
								'Pings Bing, Yandex, and other IndexNow search engines the moment content is published, updated, or removed, instead of waiting for a crawl. Google doesn’t use IndexNow.',
								'blocklane'
							)
						)
					}
					checked={ !! settings.indexnow }
					onChange={ setField( 'indexnow' ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Import from another SEO plugin', 'blocklane' ) }
				initialOpen={ false }
			>
				<p className="blocklane-pro-seo-screen__section-intro">
					{ __(
						'Copies each post’s search title, description, hidden-from-search flag, and schema type into Blocklane. Nothing you’ve already set here is overwritten, so it’s safe to run more than once.',
						'blocklane'
					) }
				</p>
				{ importError ? (
					<Notice
						status="error"
						onRemove={ () => setImportError( '' ) }
					>
						{ importError }
					</Notice>
				) : null }
				{ importResult ? (
					<Notice
						status="success"
						onRemove={ () => setImportResult( null ) }
					>
						{ sprintf(
							/* translators: 1: posts, 2: titles, 3: descriptions, 4: noindex flags, 5: schema types, 6: skipped values. */
							__(
								'Imported %1$d post(s): %2$d title(s), %3$d description(s), %4$d hidden flag(s), %5$d schema type(s). %6$d value(s) already set here were kept.',
								'blocklane'
							),
							importResult.counts?.posts || 0,
							importResult.counts?.title || 0,
							importResult.counts?.description || 0,
							importResult.counts?.noindex || 0,
							importResult.counts?.schema || 0,
							importResult.counts?.skipped || 0
						) }
					</Notice>
				) : null }
				{ null === importSources && (
					<div className="blocklane-pro-loading">
						<Spinner />
					</div>
				) }
				{ importSources && ! importSources.length && (
					<p className="blocklane-pro-seo-screen__section-intro">
						{ __(
							'No SEO data from other plugins was found on this site.',
							'blocklane'
						) }
					</p>
				) }
				{ ( importSources || [] ).map( ( { source, label, count } ) => (
					<Flex
						key={ source }
						justify="flex-start"
						gap="3"
						className="blocklane-pro-seo-screen__import-row"
					>
						<FlexItem isBlock>
							<strong>{ label }</strong>{ ' ' }
							<StatusChip>
								{ sprintf(
									/* translators: %d: number of posts with importable data. */
									__( '%d post(s)', 'blocklane' ),
									count
								) }
							</StatusChip>
						</FlexItem>
						<Button
							variant="secondary"
							isBusy={ importBusy === source }
							disabled={ !! importBusy }
							onClick={ () => runImport( source ) }
						>
							{ __( 'Import', 'blocklane' ) }
						</Button>
					</Flex>
				) ) }
			</PanelBody>

			<Flex
				justify="flex-end"
				className="blocklane-pro-child-theme__actions"
			>
				<Button
					variant="primary"
					__next40pxDefaultSize
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ save }
				>
					{ __( 'Save SEO Settings', 'blocklane' ) }
				</Button>
			</Flex>
		</div>
	);
};
