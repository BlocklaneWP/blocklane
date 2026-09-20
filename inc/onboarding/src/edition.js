/**
 * The edition, as the dashboard sees it — ONE source, read in ONE place.
 *
 * PHP localizes `edition` onto the admin object (Settings::enqueue_admin →
 * Edition::id()/name()/pro_url()/absent()), so which artifact this is, and what
 * it calls itself, is data rather than a literal compiled into the bundle. Every
 * shared file that would otherwise hard-code the paid product's name asks
 * pluginName() instead; a file that only one edition carries (a unit-owned row,
 * LicenseHero, ProCard naming the product it advertises) may still name Pro,
 * because it is only ever in the artifact that IS Pro.
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
 *                  'free'), `name`, `proUrl`, `absent`. Empty when the payload
 *                  is missing (a screen rendered outside wp-admin, a test).
 */
export const edition = () => window.blocklaneProAdmin?.edition || {};

/**
 * @return {string} What this artifact calls itself, for user-visible copy.
 */
export const pluginName = () => edition().name || 'Blocklane';
