/**
 * The SEO strings both bundles show, written clean-room against a behavior
 * brief (spec "Clean-room SEO copy", 2026-10-09).
 *
 * The SEO screen (inc/onboarding/src/screens/seo/) and the editor's SEO
 * sidebar (inc/seo/src/index.js) read these keys from here. Twelve of them
 * are also spoken by PHP (register_meta()'s labels and descriptions and the
 * post list columns in inc/seo/runtime.php), each at a call marked
 * `seo-copy:<key>` that holds the same text; seo-copy.test.js (under
 * inc/onboarding/src/screens/seo/) holds each pair equal.
 *
 * The contract (the test enforces each point): it imports `__` from
 * `@wordpress/i18n` and nothing else, and exports one function, `seoCopy()`,
 * which returns a new object whose every value is one
 * `__( '<literal>', 'blocklane' )` call. Three values carry one `%s` each
 * (`sitemapIncludeNote`, `opensAt`, `closesAt`); the screen fills it.
 *
 * @return {Object} The strings, keyed by where each appears.
 */
import { __ } from '@wordpress/i18n';

export function seoCopy() {
	return {
		// Overview tab.
		overviewContentHeading: __(
			'SEO coverage of your content',
			'blocklane'
		),
		ringSchema: __( 'Structured data chosen', 'blocklane' ),
		ringTitle: __( 'Search title set', 'blocklane' ),
		ringDescription: __( 'Search description set', 'blocklane' ),
		ringIndexable: __( 'Open to search indexing', 'blocklane' ),
		overviewManageContent: __( 'Review content SEO', 'blocklane' ),
		siteVisibilityHeading: __(
			'Visibility to search engines',
			'blocklane'
		),
		overviewManageVisibility: __(
			'Adjust visibility settings',
			'blocklane'
		),
		siteVerificationHeading: __( 'Ownership verification', 'blocklane' ),
		overviewManageVerification: __(
			'Enter verification codes',
			'blocklane'
		),
		verificationAdded: __( 'Code saved', 'blocklane' ),
		verificationMissing: __( 'No code yet', 'blocklane' ),
		disableLead: __( 'Do you handle SEO with another tool?', 'blocklane' ),
		disableLink: __( 'Turn off Blocklane SEO', 'blocklane' ),

		// Content tab.
		columnTitle: __( 'Title', 'blocklane' ),
		columnType: __( 'Content type', 'blocklane' ),
		columnSchema: __( 'Structured data', 'blocklane' ),
		columnSearchTitle: __( 'Title in search', 'blocklane' ),
		columnSearchDescription: __( 'Description in search', 'blocklane' ),
		columnIndexing: __( 'Search indexing', 'blocklane' ),
		indexingShown: __( 'Indexing allowed', 'blocklane' ),
		indexingHidden: __( 'Kept out of indexes', 'blocklane' ),
		cellEmpty: __( 'No custom value', 'blocklane' ),
		editSeo: __( 'Edit SEO fields', 'blocklane' ),
		actionHide: __( 'Hide from search results', 'blocklane' ),
		actionShow: __( 'Allow in search results', 'blocklane' ),
		actionSetSchema: __( 'Set structured data type', 'blocklane' ),

		// The four fields.
		fieldTitleLabel: __( 'Custom search title', 'blocklane' ),
		fieldTitleHelp: __(
			"This replaces the whole page title in search results, on the browser tab and in shared links. When it is blank, the site's title pattern is used, or else WordPress's usual title.",
			'blocklane'
		),
		fieldDescriptionLabel: __( 'Custom search description', 'blocklane' ),
		fieldDescriptionHelp: __(
			'The short summary a search listing can show under the title, also sent with shared links. When it is blank, the excerpt is used, or else the opening words of the content.',
			'blocklane'
		),
		fieldSchemaLabel: __( 'Structured data type', 'blocklane' ),
		fieldSchemaHelp: __(
			"Structured data describes this page to search engines in a form they can read. Article marks it as an article. FAQ builds questions and answers from the page's Details and Accordion blocks, with each Details summary as a question.",
			'blocklane'
		),
		fieldNoindexLabel: __( 'Keep out of search engines', 'blocklane' ),
		fieldNoindexHelp: __(
			'Turning this on adds a request not to index the page and removes it from the sitemap.',
			'blocklane'
		),
		noindexNotice: __(
			'Search engines are now asked to keep this item out of their indexes, and it no longer appears in the sitemap.',
			'blocklane'
		),

		// Settings tab.
		indexingToggleHelp: __(
			'This is the option WordPress shows under Settings > Reading, which the Site Visibility screen also changes. Turning it off asks every crawler to skip the whole site, so do that only before your site launches.',
			'blocklane'
		),
		archiveCanonicalsLabel: __(
			'Canonical links on archives',
			'blocklane'
		),
		archiveCanonicalsHelp: __(
			"Adds a canonical link to category, tag, author, date and other archive pages, pointing to the archive's own clean address. Later pages point to themselves, and archives hidden from search engines get none.",
			'blocklane'
		),
		sitemapToggleLabel: __( 'Publish an XML sitemap', 'blocklane' ),
		sitemapToggleHelp: __(
			'WordPress builds this sitemap by itself from everything you publish.',
			'blocklane'
		),
		/* translators: %s: the name of the switch that keeps a single item out of search engines. */
		sitemapIncludeNote: __(
			'Anything you leave unchecked is left out of the XML sitemap. To leave out a single item, turn on “%s” for that item.',
			'blocklane'
		),
		previewsHeading: __( 'Search and sharing previews', 'blocklane' ),
		previewSearchCaption: __( 'As a search result', 'blocklane' ),
		previewSocialCaption: __( 'As a shared link', 'blocklane' ),
		titlePreviewCaption: __( 'Sample page title', 'blocklane' ),
		orgNameLabel: __( 'Name of your organization', 'blocklane' ),
		socialProfilesLabel: __( 'Your social profiles', 'blocklane' ),
		socialProfilesHelp: __(
			'Add the web address of each account you run, such as on Facebook, Instagram or LinkedIn, so your structured data links to it.',
			'blocklane'
		),
		profileUrlLabel: __( 'Profile web address', 'blocklane' ),
		addProfile: __( 'Add a profile', 'blocklane' ),
		localBusinessLabel: __(
			'Describe it as a local business',
			'blocklane'
		),
		localBusinessHelp: __(
			'Turns your structured data into a local business and adds whichever address, contact, hours and map details you fill in. Search engines may display them.',
			'blocklane'
		),
		streetAddressLabel: __( 'Street address', 'blocklane' ),
		regionLabel: __( 'Region or state', 'blocklane' ),
		postalCodeLabel: __( 'Postal code', 'blocklane' ),
		openingHoursLabel: __( 'Opening hours', 'blocklane' ),
		openingHoursHelp: __(
			'Any day you leave without times is treated as closed.',
			'blocklane'
		),
		/* translators: %s: a weekday name, such as Monday. */
		opensAt: __( '%s opening time', 'blocklane' ),
		/* translators: %s: a weekday name, such as Monday. */
		closesAt: __( '%s closing time', 'blocklane' ),
	};
}
