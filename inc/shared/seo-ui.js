/**
 * Shared SEO UI primitives — the single source for the pieces the editor
 * sidebar bundle (inc/seo/src) and the dashboard bundle (inc/onboarding/src)
 * both render: the SEO mark, the ⓘ help tooltip, the character counter, the
 * Google-style URL crumb, the search-result preview, and the canonical
 * schema/verification enums. Per inc/shared/README.md rule 1: two consumers
 * → it lives here, never copied.
 *
 * The two bundles ship separate stylesheets with their own class
 * namespaces, so the components come from makeSeoUi( prefix, previewBase )
 * — call it once at module top with the bundle's prefix and destructure;
 * call sites then read exactly as before (labelWithTip( label, tip )).
 */

import { __ } from '@wordpress/i18n';
import { info, globe } from '@wordpress/icons';
import { SVG, Path } from '@wordpress/primitives';
import { decodeEntities } from '@wordpress/html-entities';
import { Icon, Tooltip } from '@wordpress/components';

// Soft guidance limits — what search engines typically display, not hard caps.
export const TITLE_LIMIT = 60;
export const DESCRIPTION_LIMIT = 160;

// The SEO mark — a magnifier whose handle sweeps out of the rings. Cleaned
// into core-icon form (SVG/Path primitives, one merged path, currentColor).
// The art spans ~208 units around center 128; core glyphs occupy ~65% of
// their box, so the viewBox pads out to 320 (centered at 128) to match, and
// the stroke bumps to 20 so it still lands at ~1.5px at the toolbar's 24px
// render — @wordpress/icons' visual weight.
export const seoIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="-32 -32 320 320">
		<Path
			d="M128 128 224 32M195.88 60.12a95.88 95.88 0 1 0 18.77 26.49M161.94 94.06a48 48 0 1 0 14 31.2"
			fill="none"
			stroke="currentColor"
			strokeLinecap="round"
			strokeLinejoin="round"
			strokeWidth="20"
		/>
	</SVG>
);

// The per-post schema enum — mirrors BLOCKLANE_PRO_SEO_SCHEMA_TYPES.
export const SCHEMA_OPTIONS = [
	{ label: __( 'None', 'blocklane' ), value: '' },
	{ label: __( 'Article', 'blocklane' ), value: 'article' },
	{ label: __( 'FAQ', 'blocklane' ), value: 'faq' },
];

export const schemaLabel = ( value ) =>
	SCHEMA_OPTIONS.find( ( option ) => option.value === value )?.label ||
	SCHEMA_OPTIONS[ 0 ].label;

// Verification services offered, in display order — mirrors
// Seo::VERIFICATION_SERVICES.
export const VERIFICATION_SERVICES = [
	{ key: 'google', label: __( 'Google', 'blocklane' ) },
	{ key: 'bing', label: __( 'Bing', 'blocklane' ) },
	{ key: 'pinterest', label: __( 'Pinterest', 'blocklane' ) },
	{ key: 'yandex', label: __( 'Yandex', 'blocklane' ) },
	{ key: 'facebook', label: __( 'Facebook', 'blocklane' ) },
];

// The permalink as a Google-style breadcrumb: host › path › parts.
export const urlCrumb = ( permalink ) => {
	if ( ! permalink ) {
		return '';
	}
	try {
		const { host, pathname } = new URL( permalink );
		const parts = pathname.split( '/' ).filter( Boolean );
		return [ host, ...parts ].join( ' › ' );
	} catch ( e ) {
		return permalink;
	}
};

/**
 * Build the prefix-bound component set.
 *
 * @param {string} prefix      CSS class namespace (e.g. 'blocklane-pro-seo').
 * @param {string} previewBase Base class for the search preview's element
 *                             tree (defaults to `${prefix}__preview`; the
 *                             dashboard passes its 'serp' block).
 * @return {Object} { HelpTip, labelWithTip, CharCount, SearchPreview }.
 */
export const makeSeoUi = ( prefix, previewBase = `${ prefix }__preview` ) => {
	/**
	 * Info-glyph help, core-native — help text lives behind a small ⓘ
	 * beside the label, shown in core's dark hover/focus Tooltip. The span
	 * is focusable so keyboard users get it on Tab as well.
	 *
	 * @param {Object} props      Component props.
	 * @param {string} props.text Help text shown in the tooltip.
	 * @return {JSX.Element} The help glyph.
	 */
	const HelpTip = ( { text } ) => (
		<Tooltip
			className={ `${ prefix }__help-tooltip` }
			text={ text }
			placement="top"
			delay={ 300 }
		>
			<span
				className={ `${ prefix }__help-tip-toggle` }
				tabIndex={ 0 }
				aria-label={ __( 'More information', 'blocklane' ) }
			>
				<Icon icon={ info } size={ 18 } />
			</span>
		</Tooltip>
	);

	/**
	 * Compose a control label with a trailing HelpTip.
	 *
	 * @param {string} label Visible label text.
	 * @param {string} tip   Tooltip explanation.
	 * @return {JSX.Element} A label node for a control's `label` prop.
	 */
	const labelWithTip = ( label, tip ) => (
		<span className={ `${ prefix }__label-with-tip` }>
			{ label }
			<HelpTip text={ tip } />
		</span>
	);

	// "n / limit" character counter that flags overruns.
	const CharCount = ( { value, limit } ) => (
		<span
			className={
				`${ prefix }__count` +
				( value.length > limit ? ' is-over' : '' )
			}
		>
			{ value.length } / { limit }
		</span>
	);

	/**
	 * The real Google result anatomy: favicon circle, site name over the
	 * URL breadcrumb, then title and description. Every SEO preview surface
	 * (editor sidebar, Edit SEO drawer, Settings homepage card) renders
	 * THIS component, so they can't drift apart.
	 *
	 * @param {Object}  props             Component props.
	 * @param {string}  props.title       Result title ('(no title)' when empty).
	 * @param {string}  props.description Result description (placeholder when empty).
	 * @param {string}  [props.permalink] URL rendered as a crumb.
	 * @param {string}  [props.crumb]     Explicit crumb text (overrides permalink).
	 * @param {boolean} [props.noindex]   Dims the card via the is-noindex class.
	 * @param {string}  [props.siteTitle] Site name above the crumb.
	 * @param {string}  [props.siteIcon]  Favicon URL (globe glyph fallback).
	 * @return {JSX.Element} The preview card.
	 */
	const SearchPreview = ( {
		title,
		description,
		permalink,
		crumb,
		noindex,
		siteTitle,
		siteIcon,
	} ) => (
		<div className={ previewBase + ( noindex ? ' is-noindex' : '' ) }>
			<div className={ `${ previewBase }-site` }>
				<span
					className={ `${ previewBase }-favicon` }
					aria-hidden="true"
				>
					{ siteIcon ? (
						<img src={ siteIcon } alt="" />
					) : (
						<Icon icon={ globe } size={ 16 } />
					) }
				</span>
				<span className={ `${ previewBase }-site-lines` }>
					{ siteTitle && (
						<span className={ `${ previewBase }-site-name` }>
							{ decodeEntities( siteTitle ) }
						</span>
					) }
					<span className={ `${ previewBase }-url` }>
						{ crumb ?? urlCrumb( permalink ) }
					</span>
				</span>
			</div>
			<div className={ `${ previewBase }-title` }>
				{ title || __( '(no title)', 'blocklane' ) }
			</div>
			<div className={ `${ previewBase }-description` }>
				{ description ||
					__(
						'Search engines will pick a snippet from the page content.',
						'blocklane'
					) }
			</div>
		</div>
	);

	return { HelpTip, labelWithTip, CharCount, SearchPreview };
};
