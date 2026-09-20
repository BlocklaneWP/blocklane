/**
 * Input field — edit. The canvas renders the same label + control structure
 * as render.php; typing in the control edits the field's default value.
 * Primary settings (required, reply-to for email) show by default; the rest
 * are opt-in ToolsPanel items.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';
import { useResolvedFieldName } from '../shared/use-resolved-field-names';
import {
	FieldLabelItem,
	HideLabelItem,
	RequiredItem,
	VisibilityItem,
	FieldNameItem,
} from '../shared/field-controls';
import { stripTags } from '../shared/strip-tags';
import { useControlStyleProps, toControlProps } from '../shared/control-props';

const LABEL_FORMATS = [ 'core/bold', 'core/italic' ];

export default function FormInputEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const {
		type,
		name,
		label,
		hideLabel,
		required,
		placeholder,
		defaultValue,
		autocomplete,
		isReplyTo,
		min,
		max,
		step,
		maxlength,
	} = attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const resolvedName = useResolvedFieldName( attributes );
	const styleProps = useControlStyleProps( attributes );
	const controlProps = toControlProps( styleProps );
	const isNumeric = 'number' === type || 'date' === type;
	const editId = 'blf-edit-' + clientId;

	/* The checkbox styles its row wrapper — the same split as the front
	   render's $style_on_wrapper (there is no styleable text control). */
	const isCheckbox = 'checkbox' === type;
	const blockProps = useBlockProps( {
		className: [
			'blocklane-form__field is-type-' + type,
			required ? 'is-required' : '',
			isCheckbox ? styleProps.className : '',
		]
			.filter( Boolean )
			.join( ' ' ),
		style: isCheckbox ? styleProps.style : undefined,
	} );

	const labelRichText = (
		<RichText
			tagName="span"
			className="blocklane-form__label-text"
			value={ label }
			onChange={ ( value ) => setAttributes( { label: value } ) }
			placeholder={ __( 'Add a label…', 'blocklane' ) }
			allowedFormats={ LABEL_FORMATS }
		/>
	);

	const requiredMark = required && (
		<span className="blocklane-form__required" aria-hidden="true">
			*
		</span>
	);

	const inspector = (
		<InspectorControls>
			<ToolsPanel
				label={ __( 'Field settings', 'blocklane' ) }
				resetAll={ () =>
					setAttributes( {
						condition: {
							enabled: false,
							field: '',
							operator: 'is',
							value: '',
						},
						label: '',
						hideLabel: false,
						required: false,
						isReplyTo: false,
						placeholder: '',
						autocomplete: '',
						name: '',
						min: '',
						max: '',
						step: '',
						maxlength: undefined,
					} )
				}
				dropdownMenuProps={ dropdownMenuProps }
			>
				{ 'hidden' !== type && (
					<FieldLabelItem
						label={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
				) }
				{ 'hidden' !== type && (
					<HideLabelItem
						hideLabel={ hideLabel }
						onChange={ ( value ) =>
							setAttributes( { hideLabel: value } )
						}
					/>
				) }
				{ 'hidden' !== type && (
					<RequiredItem
						required={ required }
						onChange={ ( value ) =>
							setAttributes( { required: value } )
						}
					/>
				) }
				{ 'email' === type && (
					<ToolsPanelItem
						hasValue={ () => isReplyTo }
						label={ __( 'Use as reply-to', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { isReplyTo: false } )
						}
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Use as reply-to', 'blocklane' ) }
							help={ __(
								'Notification emails reply to this address; the auto-responder sends here.',
								'blocklane'
							) }
							checked={ isReplyTo }
							onChange={ ( value ) =>
								setAttributes( { isReplyTo: value } )
							}
						/>
					</ToolsPanelItem>
				) }
				{ ! [ 'hidden', 'checkbox', 'date' ].includes( type ) && (
					<ToolsPanelItem
						hasValue={ () => '' !== placeholder }
						label={ __( 'Placeholder', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { placeholder: '' } )
						}
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Placeholder', 'blocklane' ) }
							value={ placeholder }
							onChange={ ( value ) =>
								setAttributes( { placeholder: value } )
							}
						/>
					</ToolsPanelItem>
				) }
				{ isNumeric && (
					<ToolsPanelItem
						hasValue={ () =>
							'' !== min || '' !== max || '' !== step
						}
						label={ __( 'Bounds', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { min: '', max: '', step: '' } )
						}
					>
						<VStack spacing={ 3 }>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Minimum', 'blocklane' ) }
								value={ min }
								onChange={ ( value ) =>
									setAttributes( { min: value } )
								}
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Maximum', 'blocklane' ) }
								value={ max }
								onChange={ ( value ) =>
									setAttributes( { max: value } )
								}
							/>
							{ 'number' === type && (
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Step', 'blocklane' ) }
									value={ step }
									onChange={ ( value ) =>
										setAttributes( { step: value } )
									}
								/>
							) }
						</VStack>
					</ToolsPanelItem>
				) }
				{ ! [ 'hidden', 'checkbox', 'number', 'date' ].includes(
					type
				) && (
					<ToolsPanelItem
						hasValue={ () => !! maxlength }
						label={ __( 'Maximum length', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { maxlength: undefined } )
						}
					>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Maximum length', 'blocklane' ) }
							min={ 1 }
							value={ maxlength ?? '' }
							onChange={ ( value ) =>
								setAttributes( {
									maxlength: value
										? parseInt( value, 10 )
										: undefined,
								} )
							}
						/>
					</ToolsPanelItem>
				) }
				{ 'checkbox' !== type && (
					<ToolsPanelItem
						hasValue={ () => '' !== autocomplete }
						label={ __( 'Autocomplete', 'blocklane' ) }
						onDeselect={ () =>
							setAttributes( { autocomplete: '' } )
						}
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Autocomplete', 'blocklane' ) }
							help={ __(
								'A HTML autocomplete token, e.g. name, email, tel, organization.',
								'blocklane'
							) }
							value={ autocomplete }
							onChange={ ( value ) =>
								setAttributes( { autocomplete: value } )
							}
						/>
					</ToolsPanelItem>
				) }
				<VisibilityItem
					condition={ attributes.condition }
					onChange={ ( condition ) => setAttributes( { condition } ) }
					clientId={ clientId }
				/>
				<FieldNameItem
					name={ name }
					resolvedName={ resolvedName }
					onChange={ ( value ) => setAttributes( { name: value } ) }
				/>
				<ToolsPanelItem
					hasValue={ () => 'text' !== type }
					label={ __( 'Input type', 'blocklane' ) }
					onDeselect={ () => setAttributes( { type: 'text' } ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Input type', 'blocklane' ) }
						value={ type }
						options={ [
							{
								label: __( 'Text', 'blocklane' ),
								value: 'text',
							},
							{
								label: __( 'Email', 'blocklane' ),
								value: 'email',
							},
							{
								label: __( 'Phone', 'blocklane' ),
								value: 'tel',
							},
							{
								label: __( 'URL', 'blocklane' ),
								value: 'url',
							},
							{
								label: __( 'Number', 'blocklane' ),
								value: 'number',
							},
							{
								label: __( 'Date', 'blocklane' ),
								value: 'date',
							},
							{
								label: __( 'Hidden', 'blocklane' ),
								value: 'hidden',
							},
							{
								label: __( 'Checkbox', 'blocklane' ),
								value: 'checkbox',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { type: value } )
						}
					/>
				</ToolsPanelItem>
			</ToolsPanel>
		</InspectorControls>
	);

	if ( 'hidden' === type ) {
		return (
			<>
				{ inspector }
				<div { ...blockProps }>
					<span className="blocklane-form__hidden-chip">
						{ __( 'Hidden field:', 'blocklane' ) +
							' ' +
							resolvedName +
							( defaultValue ? ` = ${ defaultValue }` : '' ) }
					</span>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Value', 'blocklane' ) }
						hideLabelFromVision
						placeholder={ __( 'Value…', 'blocklane' ) }
						value={ defaultValue }
						onChange={ ( value ) =>
							setAttributes( { defaultValue: value } )
						}
					/>
				</div>
			</>
		);
	}

	if ( 'checkbox' === type ) {
		return (
			<>
				{ inspector }
				<div { ...blockProps }>
					<label
						className="blocklane-form__checkbox-label"
						htmlFor={ editId }
					>
						<input
							id={ editId }
							type="checkbox"
							readOnly
							checked={ false }
						/>
						{ labelRichText }
						{ requiredMark }
					</label>
				</div>
			</>
		);
	}

	return (
		<>
			{ inspector }
			<div { ...blockProps }>
				<label
					className={
						'blocklane-form__label' +
						( hideLabel ? ' is-visually-hidden' : '' )
					}
					htmlFor={ editId }
				>
					{ labelRichText }
					{ requiredMark }
				</label>
				<input
					{ ...controlProps }
					id={ editId }
					type={ type }
					placeholder={
						placeholder || ( hideLabel ? stripTags( label ) : '' )
					}
					value={ defaultValue }
					onChange={ ( event ) =>
						setAttributes( { defaultValue: event.target.value } )
					}
					min={ min || undefined }
					max={ max || undefined }
					step={ step || undefined }
					maxLength={ maxlength || undefined }
				/>
			</div>
		</>
	);
}
