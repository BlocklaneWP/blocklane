/**
 * The pattern taxonomy's normalizer, browser half (spec
 * 2026-10-17-pattern-catalog-browse §3.4): steps 1 and 2 only, the fold and
 * the stemmer. Synonyms never reach the browser: the server expands a card's
 * search text with every surface form of its terms, so the client needs no
 * synonym map.
 *
 * MIRROR CONTRACT with blocklane_pro\Pattern_Taxonomy::fold(), ::stem() and
 * ::phrase() (inc/pattern-taxonomy/class-blocklane-pro-pattern-taxonomy.php).
 * Both read their tables from taxonomy.json `normalizer` (the server hands
 * them to the page), and both are held to normalize-vectors.json: the PHP
 * battery and normalize.test.js read the same file. Lengths are counted in
 * code points on both sides, never UTF-16 units or bytes.
 *
 * Pure: no DOM, no imports. A library front end imports these functions and
 * passes the tables it was given.
 */

/** The longest text read, in code points (§3.4 step 1). */
export const CAP = 200;

/** Combining diacritical marks, stripped after the fold table. */
const COMBINING = /[̀-ͯ]/g;

/** Hyphens, underscores and ASCII whitespace: each run becomes one space. */
const SEPARATORS = /[-_\x09-\x0d\x20]+/g;

/**
 * Text capped, lowercased, folded through the fixed table, combining marks
 * stripped, apostrophes deleted, separators collapsed to single spaces and
 * trimmed: step 1 (Pattern_Taxonomy::fold()).
 *
 * @param {string} text   Any text.
 * @param {Object} tables The normalizer tables ({ fold, … }).
 * @return {string} The folded text, words separated by single spaces.
 */
export function fold( text, tables ) {
	const table = tables?.fold ?? {};
	const capped = Array.from( String( text ?? '' ) )
		.slice( 0, CAP )
		.join( '' );
	let out = '';
	for ( const c of Array.from( capped.toLowerCase() ) ) {
		out += Object.prototype.hasOwnProperty.call( table, c )
			? table[ c ]
			: c;
	}
	return out
		.replace( COMBINING, '' )
		.replace( /'/g, '' )
		.replace( SEPARATORS, ' ' )
		.replace( /^ +| +$/g, '' );
}

/**
 * One word without its plural ending: step 2 (Pattern_Taxonomy::stem()).
 * An exception is kept, an irregular mapped, -ss/-us/-is kept, -ies is -y,
 * -ses/-xes/-zes/-ches/-shes drop -es, any other -s drops, and a result
 * shorter than min_stem keeps the word.
 *
 * @param {string} word   One folded word.
 * @param {Object} tables The normalizer tables ({ min_stem, exceptions, irregular }).
 * @return {string} The stem, or the word itself.
 */
export function stemOf( word, tables ) {
	if ( '' === word || ( tables?.exceptions ?? [] ).includes( word ) ) {
		return word;
	}
	const irregular = tables?.irregular ?? {};
	if ( Object.prototype.hasOwnProperty.call( irregular, word ) ) {
		return irregular[ word ];
	}
	if ( /(?:ss|us|is)$/.test( word ) ) {
		return word;
	}
	let stem;
	if ( word.endsWith( 'ies' ) ) {
		stem = word.slice( 0, -3 ) + 'y';
	} else if ( /(?:s|x|z|ch|sh)es$/.test( word ) ) {
		stem = word.slice( 0, -2 );
	} else if ( word.endsWith( 's' ) ) {
		stem = word.slice( 0, -1 );
	} else {
		return word;
	}
	return Array.from( stem ).length < ( tables?.min_stem ?? 3 ) ? word : stem;
}

/**
 * Steps 1 and 2: the phrase (Pattern_Taxonomy::phrase()).
 *
 * @param {string} text   Any text.
 * @param {Object} tables The normalizer tables.
 * @return {string} The phrase: folded, each word stemmed.
 */
export function phrase( text, tables ) {
	const folded = fold( text, tables );
	return '' === folded
		? ''
		: folded
				.split( ' ' )
				.map( ( word ) => stemOf( word, tables ) )
				.join( ' ' );
}
