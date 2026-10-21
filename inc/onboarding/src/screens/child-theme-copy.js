import { __, _n } from '@wordpress/i18n';

/**
 * Every user-facing string of the Create Child Theme admin screen, gathered
 * in one place so the screen's components only ever read words from here.
 *
 * `counts` describes what the import option would bring across: `templates`
 * is how many edited templates were found and `parts` how many edited
 * template parts. Both are whole numbers and each one is taken as 1 when it
 * is not given; they only choose the singular or plural form of two strings.
 *
 * Placeholders are left as written. The screen fills them in.
 *
 * @param {{templates?: number, parts?: number}} counts What the import found.
 * @return {Object} A fresh object of translated strings on every call.
 */
export function childThemeCopy( counts = {} ) {
	const templates = counts.templates ?? 1;
	const parts = counts.parts ?? 1;

	return {
		pageTitle: __( 'Create Child Theme', 'blocklane' ),

		offNotice: __(
			'Child theme creation is disabled for now.',
			'blocklane'
		),
		offBody: __(
			'While it stays disabled, this page has no menu entry and the endpoints that write theme files are unavailable. Turn on Create Child Theme in Blocklane Settings whenever you want it back.',
			'blocklane'
		),

		customizationsError: __(
			'Your Site Editor changes could not be loaded, so they cannot be copied this time. Creating the theme still works.',
			'blocklane'
		),

		optionalNote: __(
			"Every field is optional. Blank fields use your site's details.",
			'blocklane'
		),

		fieldThemeNameLabel: __( 'Theme name', 'blocklane' ),
		fieldThemeNameHelp: __(
			'Shown under Appearance > Themes, and also used for the folder whenever the text domain is empty.',
			'blocklane'
		),
		fieldThemeUrlLabel: __( 'Theme URL', 'blocklane' ),
		fieldThemeUrlHelp: __(
			"A link for the theme that goes into its header; your site's home address fills in when you skip it.",
			'blocklane'
		),
		fieldDescriptionLabel: __( 'Description', 'blocklane' ),
		fieldDescriptionHelp: __(
			'A sentence or two shown beside the theme under Appearance > Themes; skip it and a line naming the parent goes in.',
			'blocklane'
		),
		fieldAuthorLabel: __( 'Author', 'blocklane' ),
		fieldAuthorHelp: __(
			"Whoever gets credit for the theme; skip it and your site's title is used, or the parent's author if the site has no title.",
			'blocklane'
		),
		fieldAuthorUrlLabel: __( 'Author URL', 'blocklane' ),
		fieldAuthorUrlHelp: __(
			"Where that author can be found online; your site's home address is used if you skip it.",
			'blocklane'
		),
		fieldVersionLabel: __( 'Version', 'blocklane' ),
		fieldVersionHelp: __(
			"Which release this is, as written to the theme's header; it begins at 1.0.0 and falls back to that if cleared.",
			'blocklane'
		),
		fieldTextDomainLabel: __( 'Text domain', 'blocklane' ),
		fieldTextDomainHelp: __(
			"Translation ID and, if set, the directory name; left empty, the header takes the parent's directory with -child added, and the folder comes from the theme name.",
			'blocklane'
		),

		/* translators: %s: display name of the theme whose Site Editor changes would be copied. */
		importLabel: __(
			'Carry over the Site Editor changes made to %s',
			'blocklane'
		),
		importSourceFallback: __( 'the theme you started from', 'blocklane' ),
		/* translators: %s: the list of things being copied, such as global styles and a number of templates. */
		importHelp: __(
			'This writes %s into files inside the new theme, so the design you have built carries across, and nothing in the source theme is altered.',
			'blocklane'
		),
		/* translators: 1: first item being copied, 2: second item being copied. */
		importListTwo: __( 'both %1$s and %2$s', 'blocklane' ),
		/* translators: 1: first item being copied, 2: second item, 3: third item. */
		importListThree: __( '%1$s, %2$s, plus %3$s', 'blocklane' ),
		importGlobalStyles: __( 'your global style settings', 'blocklane' ),
		/* translators: %d: number of templates edited in the Site Editor. */
		importTemplates: _n(
			'%d template you edited',
			'%d templates you edited',
			templates,
			'blocklane'
		),
		/* translators: %d: number of template parts edited in the Site Editor. */
		importParts: _n(
			'%d template part you edited',
			'%d template parts you edited',
			parts,
			'blocklane'
		),
		createButton: __( 'Build my child theme and use it', 'blocklane' ),

		confirmTitle: __( 'Just so you know', 'blocklane' ),
		confirmBody: __(
			'Your site starts using the new theme the moment you continue. Whatever you saved in the Site Editor belongs to whichever theme was in use back then, so the new theme only shows it if you chose to copy it over. None of it is removed; reactivate the old theme and it all comes back.',
			'blocklane'
		),
		confirmCancel: __( 'Leave it for now', 'blocklane' ),
		confirmSubmit: __( 'Yes, build it and switch', 'blocklane' ),

		successNotice: __(
			'Done. The new child theme exists and your site is now using it.',
			'blocklane'
		),
		importDoneNote: __(
			"Your Site Editor changes were copied over and now sit in the new theme's own files.",
			'blocklane'
		),
		importPartialNote: __(
			'A few files could not be saved during the copy, though the theme was still made and switched on. Look over its theme.json, templates folder and parts folder.',
			'blocklane'
		),

		screenshotAlt: __(
			'Screenshot image included with the new theme',
			'blocklane'
		),
		hideToolNote: __(
			"You'll likely need this only once, so feel free to tuck it out of the menu by switching off Create Child Theme in Blocklane Settings. Doing so never touches a child theme you have made, and you can bring the tool back at any point.",
			'blocklane'
		),
		hideToolLink: __( 'Open Blocklane Settings', 'blocklane' ),
	};
}
