/**
 * The search engines card (`@card:search-engines`, design spec Amendment O,
 * D25): core's "Discourage search engines" checkbox, which is the same
 * `blog_public` option Settings → Reading writes, with the help tip beside
 * its label holding text only, and the help line under it carrying the link
 * to Settings → Reading.
 *
 * The help line is drawn here, not through the checkbox's `help`, so the tip
 * can sit on the label's line (the frame's local `SV/Search engines
 * checkbox`: core's checkbox cannot hold a button beside its label). The
 * checkbox names the line as its description.
 */
import { __ } from '@wordpress/i18n';
import { useId } from '@wordpress/element';
import { Button, CheckboxControl } from '@wordpress/components';

import { HelpTip } from '../../components/HelpTip';

/**
 * Settings → Reading, beside the current admin page, so a subdirectory
 * install keeps its path.
 *
 * @return {string} The URL path.
 */
const readingHref = () =>
	window.location.pathname.replace( /[^/]*$/, 'options-reading.php' );

/**
 * @param {Object}                   props
 * @param {boolean}                  props.checked  Search engines are discouraged.
 * @param {(value: boolean) => void} props.onChange Edits the form.
 * @return {JSX.Element} The card.
 */
export const SearchEnginesCard = ( { checked, onChange } ) => {
	const helpId = `${ useId() }-help`;
	return (
		<div
			data-card="search-engines"
			className="blocklane-site-visibility__card"
		>
			<div className="blocklane-site-visibility__check-group">
				<div className="blocklane-site-visibility__check-row">
					<CheckboxControl
						__nextHasNoMarginBottom
						// Core's own wording (Settings → Reading).
						label={ __(
							'Discourage search engines from indexing this site',
							'blocklane'
						) }
						aria-describedby={ helpId }
						checked={ checked }
						onChange={ onChange }
					/>
					<HelpTip
						label={ __( 'Where this setting lives', 'blocklane' ) }
						text={ __(
							'This is the same setting as Settings → Reading — changing it in either place updates the other.',
							'blocklane'
						) }
					/>
				</div>
				<p
					id={ helpId }
					data-search-help
					className="blocklane-site-visibility__help is-indented"
				>
					{ __(
						'It is up to search engines to honor this request.',
						'blocklane'
					) }{ ' ' }
					<Button
						variant="link"
						href={ readingHref() }
						data-reading-link
					>
						{ __( 'Open Settings → Reading', 'blocklane' ) }
					</Button>
				</p>
			</div>
		</div>
	);
};

export default SearchEnginesCard;
