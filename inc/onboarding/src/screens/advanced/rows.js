/**
 * Advanced rows owned by a unit — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php filters this file line by line (grammar B): the
 * unit is resolved from the PATH, and an edition that does not carry the unit
 * simply has no line for it, so webpack never reaches the module and the row's
 * copy is never emitted. Adding a unit-owned Advanced row means a file under
 * rows/, a line here, and its slug in AdvancedScreen's ROW_ORDER.
 *
 * Order here is the FEATURES order the screen used to carry inline; the
 * display order itself lives in ROW_ORDER, and rows.test.js holds the two in
 * lockstep with the shared rows.
 */

export { default as seo } from './rows/seo.js';
export { default as forms } from './rows/forms.js';
export { default as scripts } from './rows/scripts.js';
export { default as sitePrivacy } from './rows/site-privacy.js';
export { default as popups } from './rows/popups.js';
export { default as childThemeTool } from './rows/child-theme-tool.js';
