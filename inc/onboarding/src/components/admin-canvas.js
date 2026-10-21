/**
 * The canvas color: the admin color scheme's menu surface, read from the page
 * (Amendment AJ as revised by Garrett, 2026-10-12: "the canvas follows the
 * admin color scheme, the way core does").
 *
 * MIRROR CONTRACT with WordPress 7.1's boot layout, the frame core's own new
 * screens (Fonts, Connectors) sit in:
 *   wp-includes/js/dist/script-modules/boot/index.js
 *     :9426, :9437-9440  getAdminThemeColors() reads the scheme from the
 *                 body's `admin-color-<scheme>` class and looks up a seed in
 *                 the module's own ADMIN_THEME_COLORS table;
 *     :10604-10611 Root wraps the layout in `@wordpress/theme`'s ThemeProvider
 *                 with that seed, which re-scopes the --wpds-* tokens;
 *     :10586      `.boot-layout{background:var(--wpds-color-background-
 *                 surface-neutral-weak)}`, which then resolves to the scheme's
 *                 menu surface (measured on core's Fonts and Connectors pages:
 *                 #1e1e1e on Modern, #e5e5e5 on Light).
 * We cannot mirror that path itself: the seed table is internal to the boot
 * module (not exported), and ThemeProvider reaches its tokens through the
 * private-API consent, which the dashboard's entry chunk may not carry
 * (bin/dist-check.php's consent rule 3; the shell renders eagerly). Keeping
 * our own copy of the table is the one thing this must not do. So the canvas
 * takes the SAME surface from where the scheme paints it: the admin menu's
 * background, set by wp-admin/css/colors/<scheme>/colors.css from the
 * scheme's first registered color (register_admin_color_schemes(),
 * wp-includes/general-template.php:5148, colors at :5156 and :5180: Modern '#1e1e1e', Light
 * '#e5e5e5'). Any scheme, core's or a plugin's, gets its own menu color with
 * no table here. If core's boot canvas and the menu ever disagree for a
 * scheme, this contract is the place that says which one we follow: the menu.
 *
 * No menu on the page (a test, a page outside wp-admin): nothing is set, and
 * the stylesheet's fallback token paints the canvas.
 */

/** The custom property the canvas reads (blocklane-page/style.css). */
export const CANVAS_PROPERTY = '--blocklane-canvas';

/**
 * The admin menu's painted background, or null when there is none to read.
 *
 * @param {Document} doc The document.
 * @return {string|null} A computed color (e.g. `rgb(30, 30, 30)`), or null.
 */
export const adminMenuColor = ( doc = document ) => {
	const menu =
		doc.getElementById( 'adminmenuback' ) ||
		doc.getElementById( 'adminmenuwrap' );
	if ( ! menu ) {
		return null;
	}
	const color = doc.defaultView
		.getComputedStyle( menu )
		.getPropertyValue( 'background-color' )
		.trim();
	return color && 'rgba(0, 0, 0, 0)' !== color && 'transparent' !== color
		? color
		: null;
};

/**
 * Hand the scheme's menu surface to the canvas.
 *
 * @param {HTMLElement} el  The app's mount (the canvas's ancestor).
 * @param {Document}    doc The document.
 * @return {string|null} The color set, or null when none was.
 */
export const applyAdminCanvas = ( el, doc = document ) => {
	const color = adminMenuColor( doc );
	if ( color ) {
		el.style.setProperty( CANVAS_PROPERTY, color );
	}
	return color;
};
