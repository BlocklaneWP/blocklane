/**
 * Message (textarea) field — edit. Same structure as render.php; typing in
 * the control edits the default value.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	TextControl,
	__experimentalNumberControl as NumberControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
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

export default function FormTextareaEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const {
		name,
		label,
		hideLabel,
		required,
		placeholder,
		defaultValue,
		rows,
		maxlength,
	} = attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const resolvedName = useResolvedFieldName( attributes );
	const controlProps = toControlProps( useControlStyleProps( attributes ) );
	const editId = 'blf-edit-' + clientId;

	const blockProps = useBlockProps( {
		className:
			'blocklane-form__field is-type-textarea' +
			( required ? ' is-required' : '' ),
	} );

	return (
		<>
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
							required: false,
							placeholder: '',
							name: '',
							rows: 4,
							maxlength: undefined,
						} )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<FieldLabelItem
						label={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<HideLabelItem
						hideLabel={ hideLabel }
						onChange={ ( value ) =>
							setAttributes( { hideLabel: value } )
						}
					/>
					<RequiredItem
						required={ required }
						onChange={ ( value ) =>
							setAttributes( { required: value } )
						}
					/>
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
					<ToolsPanelItem
						hasValue={ () => 4 !== rows }
						label={ __( 'Rows', 'blocklane' ) }
						onDeselect={ () => setAttributes( { rows: 4 } ) }
					>
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Rows', 'blocklane' ) }
							min={ 2 }
							max={ 30 }
							value={ rows }
							onChange={ ( value ) =>
								setAttributes( {
									rows: value ? parseInt( value, 10 ) : 4,
								} )
							}
						/>
					</ToolsPanelItem>
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
					<VisibilityItem
						condition={ attributes.condition }
						onChange={ ( condition ) =>
							setAttributes( { condition } )
						}
						clientId={ clientId }
					/>
					<FieldNameItem
						name={ name }
						resolvedName={ resolvedName }
						onChange={ ( value ) =>
							setAttributes( { name: value } )
						}
					/>
				</ToolsPanel>
			</InspectorControls>
			<div { ...blockProps }>
				<label
					className={
						'blocklane-form__label' +
						( hideLabel ? ' is-visually-hidden' : '' )
					}
					htmlFor={ editId }
				>
					<RichText
						tagName="span"
						className="blocklane-form__label-text"
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
						placeholder={ __( 'Add a label…', 'blocklane' ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
					/>
					{ required && (
						<span
							className="blocklane-form__required"
							aria-hidden="true"
						>
							*
						</span>
					) }
				</label>
				<textarea
					{ ...controlProps }
					id={ editId }
					rows={ rows }
					placeholder={
						placeholder || ( hideLabel ? stripTags( label ) : '' )
					}
					value={ defaultValue }
					maxLength={ maxlength || undefined }
					onChange={ ( event ) =>
						setAttributes( { defaultValue: event.target.value } )
					}
				/>
			</div>
		</>
	);
}
