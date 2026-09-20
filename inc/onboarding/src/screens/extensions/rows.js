/**
 * Extensions rows — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php filters this file line by line (grammar B): the unit
 * is resolved from the PATH, and an edition that does not carry the extension
 * simply has no line for it, so webpack never reaches the module and its copy is
 * never emitted. Every row here is owned by an `extension:*` unit — including the
 * two the free build carries, so that moving an extension between editions is one
 * manifest line and no JS change.
 *
 * Order here is the order the map used to carry inline; the DISPLAY order lives
 * in ExtensionsScreen's DISPLAY_ORDER, and rows.test.js holds the two in
 * lockstep.
 */

export { default as transparentHeader } from './rows/transparent-header.js';
export { default as responsiveControls } from './rows/responsive-controls.js';
