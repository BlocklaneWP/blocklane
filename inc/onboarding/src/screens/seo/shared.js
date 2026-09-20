/**
 * Bits shared by the SEO screen's tabs. The UI primitives themselves live
 * in inc/shared/seo-ui.js (the editor sidebar bundle renders the same
 * components under its own class prefix) — this file binds them to the
 * screen's namespace and re-exports the canonical enums beside the
 * screen-only helpers.
 */

import { makeSeoUi } from '../../../../shared/seo-ui';

export const { HelpTip, labelWithTip, CharCount, SearchPreview } = makeSeoUi(
	'blocklane-pro-seo-screen',
	'blocklane-pro-seo-screen__serp'
);

export {
	seoIcon,
	urlCrumb,
	SCHEMA_OPTIONS,
	schemaLabel,
	VERIFICATION_SERVICES,
	TITLE_LIMIT,
	DESCRIPTION_LIMIT,
} from '../../../../shared/seo-ui';

export { errorMessage } from '../../api/errors';

// Section status chip, Jetpack-dashboard style ("2 of 5 set", "Not set").
export const StatusChip = ( { children, tone = 'neutral' } ) => (
	<span className={ `blocklane-pro-seo-screen__chip is-${ tone }` }>
		{ children }
	</span>
);

// A status dot + label row for Overview cards.
export const StatusRow = ( { on, children, offTone = 'neutral' } ) => (
	<li
		className={
			'blocklane-pro-seo-screen__status-row ' +
			( on ? 'is-on' : `is-off-${ offTone }` )
		}
	>
		<span
			className="blocklane-pro-seo-screen__status-dot"
			aria-hidden="true"
		/>
		{ children }
	</li>
);
