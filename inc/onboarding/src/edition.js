/**
 * The edition, as the dashboard sees it — ONE source, read in ONE place.
 *
 * PHP localizes `edition` onto the admin object (Settings::enqueue_admin →
 * Edition::id()/name()/pro_url()/absent()), so which artifact this is, and what
 * it calls itself, is data rather than a literal compiled into the bundle. Every
 * shared file that would otherwise hard-code the paid product's name asks
 * pluginName() instead; a file that only one edition carries (a unit-owned row,
 * LicenseHero) may still name Pro, because it is only ever in the artifact that
 * IS Pro. Shared copy may also name the paid product where it ADVERTISES it on
 * purpose — the Pro row's one link and the Extensions panel's Pro sentence —
 * because the rule exists to stop a free build calling ITSELF Pro, not to hide
 * that Pro exists.
 *
 * Two payload keys say what this artifact is made of, and each has ONE reader:
 *
 *   `units`  — PRESENCE: every manifest unit id this artifact contains. Read
 *              by hasUnit() below and nowhere else (ESLint refuses a second
 *              reader). A control that governs an absent pipeline asks this.
 *   `absent` — COPY: a catalog, keyed by unit id — a label and a blurb,
 *              nothing else — of what this artifact does NOT contain. Only the
 *              free build has entries, and only for LABELED units; it is
 *              marketing data and never a presence test (an unlabeled absent
 *              unit has no entry, so a control keyed on it would flip live the
 *              day a label was blanked, #1043). screens/pro-row.js is its only
 *              reader (ESLint refuses a second) — it turns an entry into the
 *              row that stands where the missing feature's control would be —
 *              and the copy lives in the PAYLOAD rather than the bundle, which
 *              is what keeps Pro's own words out of a build that must not carry
 *              them.
 *
 * The fallback is the bare brand, never the paid name: if the payload is
 * missing the wrong answer must be the harmless one. A free build calling
 * itself Blocklane reads fine; a free build calling itself the paid product is
 * the defect this helper exists to make impossible.
 *
 * This is the GATED half of the edition rule — control flow and copy that must
 * run in both editions. The OWNED half (UI a unit brings with it) is not
 * branched here at all: it lives under a unit path and the free artifact simply
 * does not contain it.
 */

/**
 * @return {Object} The localized edition payload: `edition` (the id, 'pro' or
 *                  'free'), `name`, `proUrl`, `units`, `absent`. Empty when the
 *                  payload is missing (a screen rendered outside wp-admin, a
 *                  test).
 */
export const edition = () => window.blocklaneProAdmin?.edition || {};

/**
 * @return {string} What this artifact calls itself, for user-visible copy.
 */
export const pluginName = () => edition().name || 'Blocklane';

/**
 * Whether this artifact contains a manifest unit — the client's ONE presence
 * signal, and the only reader of the payload's `units`.
 *
 * Fails CLOSED: no payload, or a payload without `units` (a build before
 * package 7), reads as "not present", so a control that governs an absent
 * pipeline is hidden — the harmless answer — rather than shown live over
 * nothing. The previous test read the catalog (`! absent[ id ]`), which is
 * `!undefined` → true for every unlabeled unit and for every context with no
 * payload: a dead control by construction (#1043, D9).
 *
 * @param {string} unitId Manifest unit id, e.g. 'block:form-file'.
 * @return {boolean} Whether the unit is in this artifact.
 */
export const hasUnit = ( unitId ) =>
	( edition().units || [] ).includes( unitId );
