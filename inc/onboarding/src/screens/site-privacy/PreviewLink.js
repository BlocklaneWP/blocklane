/**
 * The shareable preview link (`@preview-link`, design spec Amendment O):
 * a label, the help for the selected mode (code item 6), the read-only URL
 * with Copy, and Regenerate. The URL is shown, not hidden: Copy falls back
 * to selecting it when the clipboard is refused.
 */
import { __ } from '@wordpress/i18n';
import { useId } from '@wordpress/element';
import {
	Button,
	__experimentalInputControl as InputControl,
} from '@wordpress/components';

import { previewHelp } from './copy';

/**
 * @param {Object}     props
 * @param {string}     props.url          The preview URL.
 * @param {string}     props.mode         The selected mode.
 * @param {boolean}    props.copied       Copy just succeeded.
 * @param {boolean}    props.busy         A request is running.
 * @param {() => void} props.onCopy       Copies the URL.
 * @param {() => void} props.onRegenerate Rotates the key.
 * @return {JSX.Element} The block.
 */
export const PreviewLink = ( {
	url,
	mode,
	copied,
	busy,
	onCopy,
	onRegenerate,
} ) => {
	const id = useId();
	const helpId = `${ id }-help`;
	return (
		<div data-field="preview" className="blocklane-site-visibility__field">
			<label htmlFor={ id } className="blocklane-site-visibility__label">
				{ __( 'Shareable preview link', 'blocklane' ) }
			</label>
			<p id={ helpId } className="blocklane-site-visibility__help">
				{ previewHelp( mode ) }
			</p>
			<div
				data-preview-row
				className="blocklane-site-visibility__preview-row"
			>
				<InputControl
					__next40pxDefaultSize
					id={ id }
					readOnly
					value={ url }
					aria-describedby={ helpId }
					onFocus={ ( event ) => event.target.select() }
				/>
				<Button
					variant="secondary"
					__next40pxDefaultSize
					className="blocklane-site-visibility__copy"
					data-copy=""
					onClick={ onCopy }
				>
					{ copied
						? __( 'Copied', 'blocklane' )
						: __( 'Copy', 'blocklane' ) }
				</Button>
			</div>
			<div
				data-regen-row
				className="blocklane-site-visibility__regen-row"
			>
				<Button
					variant="link"
					disabled={ busy }
					onClick={ onRegenerate }
				>
					{ __( 'Regenerate link', 'blocklane' ) }
				</Button>
			</div>
		</div>
	);
};

export default PreviewLink;
