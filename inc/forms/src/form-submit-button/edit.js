/**
 * Submit button — edit. The canvas renders the real button element with the
 * theme's button classes (wp-element-button), matching render.php.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	TextControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';

export default function FormSubmitButtonEdit( { attributes, setAttributes } ) {
	const { text, busyText } = attributes;
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	// The Turnstile placeholder moved to the form wrapper (form/edit.js),
	// mirroring the front-end move: the widget is the form's last child,
	// never a sibling of the button.
	const blockProps = useBlockProps( {
		className:
			'blocklane-form__submit wp-block-button__link wp-element-button',
	} );

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					label={ __( 'Button settings', 'blocklane' ) }
					resetAll={ () => setAttributes( { busyText: '' } ) }
					dropdownMenuProps={ dropdownMenuProps }
				>
					<ToolsPanelItem
						hasValue={ () => '' !== busyText }
						label={ __( 'Sending label', 'blocklane' ) }
						onDeselect={ () => setAttributes( { busyText: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Sending label', 'blocklane' ) }
							help={ __(
								'Shown while the form submits. Empty uses “Sending…”.',
								'blocklane'
							) }
							value={ busyText }
							onChange={ ( value ) =>
								setAttributes( { busyText: value } )
							}
						/>
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			<RichText
				{ ...blockProps }
				tagName="button"
				value={ text }
				onChange={ ( value ) => setAttributes( { text: value } ) }
				placeholder={ __( 'Submit', 'blocklane' ) }
				allowedFormats={ [] }
				onClick={ ( event ) => event.preventDefault() }
			/>
		</>
	);
}
