/**
 * Choice — edit. One choice row; the control type follows the parent
 * through block context: radio/checkbox groups render their input, and a
 * parent without that context (the dropdown) gets a plain text row — a
 * real <option> can't hold a control.
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

export default function FormOptionEdit( {
	attributes,
	setAttributes,
	context,
	clientId,
} ) {
	const { label, value } = attributes;
	const groupType = context[ 'blocklane/fieldType' ];
	const isChoiceRow = 'radio' === groupType || 'checkbox' === groupType;
	const type = 'checkbox' === groupType ? 'checkbox' : 'radio';

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const editId = 'blf-edit-' + clientId;
	const blockProps = useBlockProps( {
		className: 'blocklane-form__option',
	} );

	const labelRichText = (
		<RichText
			tagName="span"
			className="blocklane-form__label-text"
			value={ label }
			onChange={ ( next ) => setAttributes( { label: next } ) }
			placeholder={ __( 'Add a choice…', 'blocklane' ) }
			/* Group rows render their label markup on the front; a real
			   <option> cannot, so inside a select formatting is not offered
			   rather than silently stripped at render. */
			allowedFormats={ isChoiceRow ? [ 'core/bold', 'core/italic' ] : [] }
		/>
	);

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					label={ __( 'Choice settings', 'blocklane' ) }
					resetAll={ () => setAttributes( { value: '' } ) }
					dropdownMenuProps={ dropdownMenuProps }
				>
					<ToolsPanelItem
						hasValue={ () => '' !== value }
						label={ __( 'Submitted value', 'blocklane' ) }
						onDeselect={ () => setAttributes( { value: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Submitted value', 'blocklane' ) }
							help={ __(
								'Empty submits the label.',
								'blocklane'
							) }
							value={ value }
							onChange={ ( next ) =>
								setAttributes( { value: next } )
							}
						/>
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			{ isChoiceRow ? (
				<label { ...blockProps } htmlFor={ editId }>
					<input
						id={ editId }
						type={ type }
						readOnly
						checked={ false }
					/>
					{ labelRichText }
				</label>
			) : (
				<div { ...blockProps }>{ labelRichText }</div>
			) }
		</>
	);
}
