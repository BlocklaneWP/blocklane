/**
 * LAYOUT — every Settings row's place: its slug, its tab and (on a sectioned
 * tab) its section, in display order (spec 2026-10-12-c-settings.md §3.2).
 * ONE table where five used to be (two category maps, two order lists and a
 * display order). Slugs only: a slug names a row and carries no copy, so a
 * Pro feature's name never appears here (the source scan reads this file).
 *
 * The order is the Pro display order (the Settings frames draw the four
 * settings tabs in this order, Amendment Z1); a build that shows Pro rows
 * lists its live rows first within each section or tab (liveFirst, F2).
 * The Extensions sections follow Amendment F2 (Layout & responsive first,
 * live in free, then Styling & effects, then Workflow & tools), with Motion
 * between the first two (spec 2026-10-19-pro-extension-renames §8: the
 * entrance and scroll motion rows; the transforms row stays in Styling).
 * Within a section the order is provisional until the Settings frames are
 * exported (§6 step 3), and moves with them.
 *
 * rows.test.js holds this table and the row index equal in both directions
 * (RW1, RW2): a row with no place, a place with no row, a slug twice, an
 * unknown tab or section, and a row missing its section on a sectioned tab
 * all fail.
 */
export const LAYOUT = Object.freeze( [
	// Extensions — Layout & responsive.
	{ slug: 'responsive-controls', tab: 'extensions', section: 'layout' },
	{ slug: 'transparent-header', tab: 'extensions', section: 'layout' },
	{ slug: 'navigation-toggle', tab: 'extensions', section: 'layout' },
	{ slug: 'advanced-group', tab: 'extensions', section: 'layout' },
	{ slug: 'advanced-grid', tab: 'extensions', section: 'layout' },
	// Extensions — Motion.
	{ slug: 'animation-designer', tab: 'extensions', section: 'motion' },
	{ slug: 'scroll-motion', tab: 'extensions', section: 'motion' },
	// Extensions — Styling & effects.
	{ slug: 'transforms', tab: 'extensions', section: 'styling' },
	{ slug: 'hover-colors', tab: 'extensions', section: 'styling' },
	{ slug: 'button-icons', tab: 'extensions', section: 'styling' },
	{ slug: 'icon-library', tab: 'extensions', section: 'styling' },
	{ slug: 'background-url', tab: 'extensions', section: 'styling' },
	{ slug: 'video-modal', tab: 'extensions', section: 'styling' },
	{ slug: 'advanced-tabs', tab: 'extensions', section: 'styling' },
	{ slug: 'text-wrap', tab: 'extensions', section: 'styling' },
	// Extensions — Workflow & tools.
	{ slug: 'class-manager', tab: 'extensions', section: 'workflow' },
	{ slug: 'smart-sync', tab: 'extensions', section: 'workflow' },
	// Content & editing.
	{ slug: 'seo', tab: 'content' },
	{ slug: 'forms', tab: 'content' },
	{ slug: 'carousel', tab: 'content' },
	{ slug: 'duplicate-content', tab: 'content' },
	{ slug: 'reorder-content', tab: 'content' },
	{ slug: 'media-replacement', tab: 'content' },
	{ slug: 'disable-comments', tab: 'content' },
	{ slug: 'disable-blog-features', tab: 'content' },
	// Site tools.
	{ slug: 'content-types', tab: 'tools' },
	{ slug: 'dynamic-values', tab: 'tools' },
	{ slug: 'scripts', tab: 'tools' },
	{ slug: 'site-privacy', tab: 'tools' },
	{ slug: 'ai-mcp', tab: 'tools' },
	{ slug: 'ai-tools', tab: 'tools' },
	{ slug: 'popups', tab: 'tools' },
	{ slug: 'child-theme-tool', tab: 'tools' },
	// Updates & performance.
	{ slug: 'auto-update-plugins', tab: 'updates' },
	{ slug: 'auto-update-themes', tab: 'updates' },
	{ slug: 'limit-revisions', tab: 'updates' },
	{ slug: 'limit-heartbeat', tab: 'updates' },
	{ slug: 'clean-uninstall', tab: 'updates' },
	// Security & hardening.
	{ slug: 'limit-login-attempts', tab: 'security' },
	{ slug: 'disable-xmlrpc', tab: 'security' },
	{ slug: 'disable-file-editing', tab: 'security' },
	{ slug: 'block-user-enumeration', tab: 'security' },
	{ slug: 'safe-svg-upload', tab: 'security' },
	{ slug: 'last-login-column', tab: 'security' },
] );

export default LAYOUT;
