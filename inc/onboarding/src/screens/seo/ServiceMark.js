/**
 * A verification service's brand mark, drawn before its name in the SEO
 * screen's "Ownership verification" overview card and before each label of
 * the settings form (design spec Amendment AO, Garrett 2026-10-13).
 *
 * The marks are the server's: Seo::brand_marks() asks WordPress core for
 * its Social Icons glyphs (Google, Pinterest, Facebook) and reads any mark a
 * Pro unit contributes (Bing, Yandex), and Settings localizes them as
 * `window.blocklaneProAdmin.screenData.seo.marks` (service => { viewBox,
 * paths }). This component holds no glyph: it draws the paths it is handed,
 * and the `@wordpress/icons` globe for a service it has none for, which is
 * what the free build shows for Bing and Yandex.
 *
 * Every mark is 16×16 and decorative (aria-hidden, unfocusable: the service
 * is named by the text beside it), in one fixed neutral color whatever the
 * admin color scheme (blocklane-page/seo/style.css `.blocklane-seo-mark`).
 */
import { Icon, globe } from '@wordpress/icons';

/** The size every mark is drawn at, px. */
const SIZE = 16;

/**
 * @param {string} service A verification service key.
 * @return {{ viewBox: string, paths: string[] }|null} Its localized mark.
 */
const markOf = ( service ) => {
	const mark = window.blocklaneProAdmin?.screenData?.seo?.marks?.[ service ];
	return mark &&
		'string' === typeof mark.viewBox &&
		Array.isArray( mark.paths )
		? mark
		: null;
};

/**
 * @param {Object} props
 * @param {string} props.service A verification service key.
 * @return {JSX.Element} The mark.
 */
export const ServiceMark = ( { service } ) => {
	const mark = markOf( service );
	if ( ! mark ) {
		return (
			<Icon
				icon={ globe }
				size={ SIZE }
				className="blocklane-seo-mark"
				aria-hidden="true"
				focusable="false"
				data-mark="globe"
			/>
		);
	}
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox={ mark.viewBox }
			width={ SIZE }
			height={ SIZE }
			className="blocklane-seo-mark"
			aria-hidden="true"
			focusable="false"
			data-mark={ service }
		>
			{ mark.paths.map( ( d ) => (
				<path key={ d } d={ d } />
			) ) }
		</svg>
	);
};
