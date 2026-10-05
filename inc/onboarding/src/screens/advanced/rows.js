/**
 * Advanced rows owned by a unit — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php rewrites this file line by line (grammar B): the
 * unit is resolved from the PATH, and an edition that does not carry the unit
 * gets its line REPLACED by a proRow() call carrying only the unit id, so
 * webpack never reaches the module, the row's copy is never emitted, and the
 * screen draws the inert Pro row from the localized catalog instead (a
 * catalog with no entry yields null, which the screen drops). Adding a
 * unit-owned Advanced row means a file under
 * rows/, a line here, and its slug in AdvancedScreen's ROW_ORDER.
 *
 * Order here is the FEATURES order the screen used to carry inline; the
 * display order itself lives in ROW_ORDER, and rows.test.js holds the two in
 * lockstep with the shared rows.
 */

import { proRow } from '../pro-row.js';

export { default as seo } from './rows/seo.js';
export { default as forms } from './rows/forms.js';
export const carousel = proRow( 'module:carousel', 'carousel' );
export const contentTypes = proRow( 'module:content-types', 'content-types' );
export const dynamicValues = proRow( 'module:dynamic-values', 'dynamic-values' );
export const scripts = proRow( 'service:scripts', 'scripts' );
export { default as sitePrivacy } from './rows/site-privacy.js';
export const aiMcp = proRow( 'module:ai-mcp', 'ai-mcp' );
export const aiTools = proRow( 'module:ai-tools', 'ai-tools' );
export { default as popups } from './rows/popups.js';
export { default as childThemeTool } from './rows/child-theme-tool.js';
export const autoUpdatePlugins = proRow( 'security:auto-update-plugins', 'auto-update-plugins' );
export const autoUpdateThemes = proRow( 'security:auto-update-themes', 'auto-update-themes' );
