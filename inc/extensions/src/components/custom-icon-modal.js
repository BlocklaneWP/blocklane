/**
 * Custom SVG modal — paste markup, watch the sanitized result live, insert.
 *
 * The anatomy follows the strongest editor convention for this job (a
 * full-height paste area beside a fixed preview rail with size control,
 * validity notice, and Clear/Insert actions), reimplemented on our own
 * pipeline with two upgrades over the convention: validity is decided by
 * the whitelist sanitizer — the preview renders exactly the markup that
 * would be stored, not the raw paste — and an optional Label field names
 * the icon for the library listing. The PHP twin sanitizer
 * (blocklane_pro_ext_sanitize_svg) re-checks everything server-side.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import {
	Button,
	Icon,
	Modal,
	Notice,
	RangeControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import sanitizeSvgString from './sanitize-svg';

// The Blocklane mark (the brand B, matching the admin-menu icon), at
// currentColor so the preview's muted default state applies. The Icon
// wrapper sizes it from the preview-size slider like a pasted SVG.
const blocklaneMark = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 489.5 512"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M98.5.8c-.1.4-1.4 6.9-2.8 14.3s-4.1 21.3-5.9 31c-1.9 9.6-5 25.7-6.9 35.7s-4.6 24-6 31.3c-1.4 7.2-3.1 16-3.7 19.4-.9 5-9.6 49.8-11.8 61.5-.4 2.1-.4 2.8.3 3.1.4.2 16.7.2 36.1 0 33.5-.3 35.6-.4 42.1-1.7 31.8-6.5 57.5-26.1 71-54.1 5.6-11.6 6.7-16 16.3-66.7 1.8-9.5 3.4-17.3 3.4-17.4.3-.4 18.2 3.1 18.7 3.7.3.3.3 2-.2 4.1-4 19.6-4.1 20.1-4.1 33.4 0 11 .2 13.8 1.4 19.5 7.2 34.3 31.4 62.1 64 73.4 11 3.8 20.5 5.4 32.2 5.4 21.2 0 38.2-5.1 56.1-17 7.6-5.1 20.2-17.6 25.5-25.3 10.1-15 15.8-31 17-48.4 2.4-32.2-10.6-62.8-35.6-83.6-12.9-10.7-29-18.1-46.1-21-7-1.2-11-1.3-133.9-1.3S98.9.3 98.6.9m-41.8 216c0 .3-1 5.6-2.2 11.8-3.9 19.8-6.4 32.9-8.8 44.8-1.2 6.4-3.2 16.6-4.4 22.8-3.4 17.5-6.9 35.8-9.4 48.8-1.3 6.5-3.3 17-4.4 23.2-2.6 13.3-5.5 28.6-8.7 45.1-2.3 12-4.8 25-8.8 45.7-1.2 6.2-3.2 16.6-4.4 23.2-1.3 6.5-3.1 15.9-4.1 20.8L-.2 512l179.8-.2 179.7-.2 7.2-1.4c31.4-6.1 56.1-18.7 78.1-39.9 20.5-19.8 35.1-45.6 41.4-73.5 2.8-12.3 3.3-18 3.3-32.2 0-14.6-.8-21.2-3.6-33.5-10-43.3-38.9-79.6-78.9-99.2-14.1-6.9-28.3-11.4-44.1-13.8-9-1.4-30.7-1.6-39.4-.3-34.2 4.8-62.6 18.8-86.1 42.4-22.5 22.5-36.3 49.9-42 82.9-1.1 6.7-2.5 13.1-2.9 13.5-.3.3-16.6-2.6-18.4-3.3-.5-.1-.2-2.8 1.1-8.9 3.8-18 4.5-29.5 2.8-42.7-3.3-25-16.4-47.9-36.6-64-11.8-9.5-28.4-16.8-43.8-19.7-6.5-1.1-40.7-2.1-40.7-1.1" />
	</svg>
);

const PREVIEW_MIN = 24;
const PREVIEW_MAX = 400;
const PREVIEW_DEFAULT = 100;

/**
 * @param {Object}                               props
 * @param {(svg: string, label: string) => void} props.onInsert       Called with ( sanitizedSvg, label ).
 * @param {() => void}                           props.onClose        Closes the modal.
 * @param {string}                               props.initialSvg     Existing SVG markup when editing.
 * @param {string}                               props.title          Modal title.
 * @param {string}                               props.insertLabel    Insert button label.
 * @param {boolean}                              props.showLabelField Show the library Label field.
 * @param {boolean}                              props.isSaving       Disables actions while saving.
 * @param {string}                               props.errorMessage   Save failure from the caller.
 */
export default function CustomIconModal( {
	onInsert,
	onClose,
	initialSvg = '',
	title,
	insertLabel,
	showLabelField = false,
	isSaving = false,
	errorMessage = null,
} ) {
	const [ svgInput, setSvgInput ] = useState( initialSvg );
	const [ label, setLabel ] = useState( '' );
	const [ previewSize, setPreviewSize ] = useState( PREVIEW_DEFAULT );
	const [ sanitized, setSanitized ] = useState( () =>
		sanitizeSvgString( initialSvg )
	);

	const onChange = useCallback( ( value ) => {
		setSvgInput( value );
		setSanitized( sanitizeSvgString( value ) );
	}, [] );

	const isValid = !! sanitized;

	return (
		<Modal
			title={ title || __( 'Custom Icon', 'blocklane' ) }
			onRequestClose={ onClose }
			className="blocklane-pro-custom-icon-modal"
			isFullScreen
		>
			<div className="blocklane-pro-custom-icon-modal__inserter">
				<div className="blocklane-pro-custom-icon-modal__content">
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Custom icon', 'blocklane' ) }
						hideLabelFromVision
						value={ svgInput }
						onChange={ onChange }
						placeholder={ __(
							'Paste the SVG code for your custom icon.',
							'blocklane'
						) }
					/>
				</div>
				<div className="blocklane-pro-custom-icon-modal__sidebar">
					<div className="blocklane-pro-custom-icon-modal__top">
						{ showLabelField && (
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className="blocklane-pro-custom-icon-modal__label"
								label={ __( 'Label', 'blocklane' ) }
								help={ __(
									'How the icon is listed in the library.',
									'blocklane'
								) }
								value={ label }
								onChange={ setLabel }
							/>
						) }
						<div
							className={
								isValid
									? 'blocklane-pro-custom-icon-modal__preview'
									: 'blocklane-pro-custom-icon-modal__preview is-default'
							}
						>
							{ isValid ? (
								<span
									style={ {
										width: `${ previewSize }px`,
									} }
									dangerouslySetInnerHTML={ {
										__html: sanitized,
									} }
								/>
							) : (
								<Icon
									icon={ blocklaneMark }
									size={ previewSize }
								/>
							) }
						</div>
						<div className="blocklane-pro-custom-icon-modal__size">
							<span>{ __( 'Preview size', 'blocklane' ) }</span>
							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Preview size', 'blocklane' ) }
								hideLabelFromVision
								withInputField={ false }
								min={ PREVIEW_MIN }
								max={ PREVIEW_MAX }
								value={ previewSize }
								onChange={ setPreviewSize }
							/>
						</div>
						{ !! svgInput && ! isValid && (
							<Notice status="error" isDismissible={ false }>
								{ __(
									'This doesn’t appear to be valid SVG — only safe SVG elements and attributes are kept.',
									'blocklane'
								) }
							</Notice>
						) }
						{ !! errorMessage && (
							<Notice status="error" isDismissible={ false }>
								{ errorMessage }
							</Notice>
						) }
					</div>
					<div className="blocklane-pro-custom-icon-modal__actions">
						<Button
							variant="secondary"
							disabled={ ! svgInput || isSaving }
							label={ __( 'Clear custom icon', 'blocklane' ) }
							onClick={ () => onChange( '' ) }
						>
							{ __( 'Clear', 'blocklane' ) }
						</Button>
						<Button
							variant="primary"
							disabled={ ! isValid || isSaving }
							label={ __( 'Insert custom icon', 'blocklane' ) }
							onClick={ () => onInsert( sanitized, label ) }
						>
							{ insertLabel ||
								__( 'Insert custom icon', 'blocklane' ) }
						</Button>
					</div>
				</div>
			</div>
		</Modal>
	);
}
