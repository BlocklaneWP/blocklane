/**
 * The JS mirror of wp_strip_all_tags() minus its trailing trim: RichText
 * values may carry bold/italic markup a plain-text surface cannot show.
 * Callers that PREVIEW a value should .trim() the result (the server
 * helper trims); controlled inputs must NOT — trimming a controlled value
 * eats the space the author is typing.
 *
 * @param {string} html Markup-bearing value.
 * @return {string} The value with tags removed.
 */
export const stripTags = ( html ) => ( html || '' ).replace( /<[^>]*>/g, '' );
