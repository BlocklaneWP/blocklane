/**
 * The Settings rows — ONE ENTRY PER LINE, deliberately (spec
 * the C-settings spec (2026-10-12) §3.2; one index where there were
 * two). bin/generate-edition.php rewrites this file line by line (grammar
 * B): the owner is resolved from the PATH; a line whose unit an edition does
 * not carry is REPLACED by a proRow() call carrying only the unit id and the
 * row slug, so webpack never reaches the file and its copy is never emitted
 * (a unit with no catalog entry yields null, which the screen drops); a line
 * whose path is a core path (a row no unit owns) is kept in every edition.
 *
 * Adding a row: a file under rows/, a line here, and its place in LAYOUT
 * (layout.js); a unit-owned row also needs the path on its unit in
 * edition-manifest.json. rows.test.js holds the index, LAYOUT, the
 * manifest and the stores in lockstep. Order here is not display order;
 * LAYOUT is.
 */

import { proRow } from '../pro-row.js';

export { default as responsiveControls } from './rows/responsive-controls.js';
export { default as transparentHeader } from './rows/transparent-header.js';
export { default as navigationToggle } from './rows/navigation-toggle.js';
export const advancedGroup = proRow( 'extension:advanced-group', 'advanced-group' );
export const advancedGrid = proRow( 'extension:advanced-grid', 'advanced-grid' );
export const animationDesigner = proRow( 'extension:animation-designer', 'animation-designer' );
export const scrollMotion = proRow( 'extension:scroll-motion', 'scroll-motion' );
export const transforms = proRow( 'extension:transforms', 'transforms' );
export const hoverColors = proRow( 'extension:hover-colors', 'hover-colors' );
export const buttonIcons = proRow( 'extension:button-icons', 'button-icons' );
export const iconLibrary = proRow( 'extension:icon-library', 'icon-library' );
export const backgroundUrl = proRow( 'extension:background-url', 'background-url' );
export const videoModal = proRow( 'extension:video-modal', 'video-modal' );
export const advancedTabs = proRow( 'extension:advanced-tabs', 'advanced-tabs' );
export const textWrap = proRow( 'extension:text-wrap', 'text-wrap' );
export const classManager = proRow( 'extension:class-manager', 'class-manager' );
export const smartSync = proRow( 'extension:smart-sync', 'smart-sync' );
export { default as seo } from './rows/seo.js';
export { default as forms } from './rows/forms.js';
export { default as carousel } from './rows/carousel.js';
export { default as duplicateContent } from './rows/duplicate-content.js';
export { default as reorderContent } from './rows/reorder-content.js';
export { default as mediaReplacement } from './rows/media-replacement.js';
export { default as disableComments } from './rows/disable-comments.js';
export { default as disableBlogFeatures } from './rows/disable-blog-features.js';
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
export { default as limitRevisions } from './rows/limit-revisions.js';
export { default as limitHeartbeat } from './rows/limit-heartbeat.js';
export { default as cleanUninstall } from './rows/clean-uninstall.js';
export { default as limitLoginAttempts } from './rows/limit-login-attempts.js';
export { default as disableXmlrpc } from './rows/disable-xmlrpc.js';
export { default as disableFileEditing } from './rows/disable-file-editing.js';
export { default as blockUserEnumeration } from './rows/block-user-enumeration.js';
export { default as safeSvgUpload } from './rows/safe-svg-upload.js';
export { default as lastLoginColumn } from './rows/last-login-column.js';
