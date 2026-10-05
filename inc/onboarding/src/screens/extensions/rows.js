/**
 * Extensions rows — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php rewrites this file line by line (grammar B): the unit
 * is resolved from the PATH, and an edition that does not carry the extension
 * gets its line REPLACED by a proRow() call carrying only the unit id, so
 * webpack never reaches the module, its copy is never emitted, and the screen
 * draws the inert Pro row from the localized catalog instead. Every row here is
 * owned by an `extension:*` unit — including the
 * two the free build carries, so that moving an extension between editions is one
 * manifest line and no JS change.
 *
 * Order here is the order the map used to carry inline; the DISPLAY order lives
 * in ExtensionsScreen's DISPLAY_ORDER, and rows.test.js holds the two in
 * lockstep.
 */

import { proRow } from '../pro-row.js';

export const animationDesigner = proRow( 'extension:animation-designer', 'animation-designer' );
export const hoverColors = proRow( 'extension:hover-colors', 'hover-colors' );
export const advancedGroup = proRow( 'extension:advanced-group', 'advanced-group' );
export const buttonIcons = proRow( 'extension:button-icons', 'button-icons' );
export const advancedGrid = proRow( 'extension:advanced-grid', 'advanced-grid' );
export const classManager = proRow( 'extension:class-manager', 'class-manager' );
export const smartSync = proRow( 'extension:smart-sync', 'smart-sync' );
export const advancedTabs = proRow( 'extension:advanced-tabs', 'advanced-tabs' );
export const textWrap = proRow( 'extension:text-wrap', 'text-wrap' );
export { default as transparentHeader } from './rows/transparent-header.js';
export const videoModal = proRow( 'extension:video-modal', 'video-modal' );
export { default as responsiveControls } from './rows/responsive-controls.js';
export const iconLibrary = proRow( 'extension:icon-library', 'icon-library' );
export const backgroundUrl = proRow( 'extension:background-url', 'background-url' );
