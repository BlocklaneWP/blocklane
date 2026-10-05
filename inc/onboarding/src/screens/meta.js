/**
 * Screen META — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php rewrites this file line by line (grammar B, DROP
 * mode): the unit is resolved from the PATH, and an edition that does not
 * carry the unit has no line for its screen, so webpack never reaches the
 * module and the screen's label, title and subtitle are never emitted into
 * that edition's bundle. A screen is not a row and never stands in: there is
 * no Pro row for a screen, the label reaches the free dashboard only through
 * the localized catalog when a row asks for it.
 *
 * meta/home.js is core (manifest `core_screens`) and stays in every edition;
 * every other file under meta/ is owned by the unit whose `screen` it names,
 * and generator rule 16 refuses a unit that declares a screen without owning
 * its meta file, a meta file that names no unit's screen, and a meta file
 * owned by a unit whose screen it is not.
 *
 * Order is NOT this file's concern: a namespace import is keyed
 * alphabetically, so registry.js orders screens by SCREEN_ORDER (slugs are
 * shared data, like AdvancedScreen's ROW_ORDER). Adding a screen means a
 * file under meta/, a line here, a line in components.js, the slug in
 * SCREEN_ORDER, and `screen` plus the meta path on its unit in the manifest.
 */

export { default as home } from './meta/home.js';
export { default as seo } from './meta/seo.js';
export { default as forms } from './meta/forms.js';
export { default as sitePrivacy } from './meta/site-privacy.js';
export { default as extensions } from './meta/extensions.js';
export { default as advanced } from './meta/advanced.js';
export { default as childTheme } from './meta/child-theme.js';
