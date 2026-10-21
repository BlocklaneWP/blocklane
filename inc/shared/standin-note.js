/**
 * The editor stand-ins' one reader of their inspector copy.
 *
 * blocklane_pro\Standin::enqueue() (inc/class-blocklane-pro-standin.php) adds,
 * before each stand-in bundle, `window.blocklaneProStandin[ <block name> ] =
 * { reason, text }`: why the real block is not registered on this request
 * (`edition`, `classic_theme` or `not_running`) and the sentence the
 * inspector shows for it. The copy is defined ONCE, in PHP, so the edition
 * that can fail it asserts it (inc/shared/README.md rule 4); every stand-in
 * bundle reads it through this function and nowhere else (rule 1: two
 * consumers, one module).
 *
 * @param {Object|undefined} global    The localized global, `window.blocklaneProStandin`.
 * @param {string}           blockName The stand-in's block name.
 * @return {{reason: string, text: string}|null} The note, or null when there
 *         is none to show: no global (an older loader, a theme's copy of the
 *         bundle, a cached page), no entry for this block, or an entry whose
 *         `text` is not a non-empty string or whose `reason` is not a string.
 *         A null note means the paragraph is simply absent; the rest of the
 *         inspector renders.
 */
export function standinNote( global, blockName ) {
	const entry = global?.[ blockName ];
	if (
		! entry ||
		'string' !== typeof entry.reason ||
		'string' !== typeof entry.text ||
		'' === entry.text.trim()
	) {
		return null;
	}

	return { reason: entry.reason, text: entry.text };
}
