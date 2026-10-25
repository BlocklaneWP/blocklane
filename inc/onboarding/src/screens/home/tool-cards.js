/**
 * Home tool cards — ONE ENTRY PER LINE, deliberately.
 *
 * bin/generate-edition.php rewrites this file line by line (grammar B, DROP
 * mode): the unit is resolved from the PATH, and an edition that does not
 * carry the unit has no line for its card, so webpack never reaches the
 * module and the card's title and description are never emitted into that
 * edition's bundle. A card is not a row and never stands in: a card for a
 * screen this edition lacks simply has no line, by construction, which is
 * what lets HomeScreen read TOGGLE state alone (isToolScreenOn) and never an
 * edition.
 *
 * Every file under home/tool-cards/ is owned by the unit whose `screen` the
 * card opens (generator rule 16 refuses one that is not). Order is NOT this
 * file's concern: a namespace import is keyed alphabetically, so HomeScreen
 * orders cards by TOOL_CARD_ORDER (slugs are shared data). Adding a card
 * means a file under home/tool-cards/, a line here, the slug in
 * TOOL_CARD_ORDER, and the path on its unit in the manifest.
 */

export { default as sitePrivacy } from './tool-cards/site-privacy.js';
