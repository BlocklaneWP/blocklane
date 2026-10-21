/**
 * The Forms screen's CONTRIBUTIONS — ONE ENTRY PER LINE, deliberately.
 *
 * Each line re-exports a file a CONTRIBUTING UNIT of module:forms owns
 * (edition-manifest rule 6): the copy that unit adds to this screen. The
 * file sits inside the parent's directory and the manifest gives it to the
 * contributor, so its owner is the LONGEST covering path.
 * bin/generate-edition.php rewrites this file line by line (grammar B, DROP
 * mode; EDITION_DROP_INDEXES): an edition without the unit has no line for
 * its file and no file, so webpack never reaches the module and none of its
 * words are emitted into that edition's bundle (#2394). Nothing stands in.
 *
 * A part's shape: { present: () => boolean, group?: { label, items },
 * copy?: { <key>: string } }. forms/about.js appends every present part's
 * group to the About; forms/copy.js reads a present part's `copy` in place
 * of its own edition-neutral sentence for the same key, and no two parts
 * may offer one key (contributions.test.js FC1: the first present offer
 * would win, so the order of these lines would decide). Adding a part means
 * a file under the unit's own directory here, a line in this file, the path
 * on the unit in the manifest, and its sentences in the free edition's
 * forbidden_ui_strings so a free zip carrying one fails.
 */

// Keeps this file an ES module when the free stage drops every line below.
// A file with no export statement is a CommonJS script to Babel and to
// webpack, and a namespace import of one is { default: {} }: forms/about.js
// would read that object as a part and throw (the free stage's jest run
// caught it, 2026-10-13). Not a grammar-B line, so the generator leaves it.
export {};

