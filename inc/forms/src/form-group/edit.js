/**
 * Choice group — edit. A real fieldset + legend in the canvas; the options
 * are inner blocks so each row is directly editable.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';
import { useResolvedFieldName } from '../shared/use-resolved-field-names';
import {
	FieldLabelItem,
	RequiredItem,
	VisibilityItem,
	FieldNameItem,
} from '../shared/field-controls';

const OPTIONS_TEMPLATE = [
	[ 'blocklane/form-option', {} ],
	[ 'blocklane/form-option', {} ],
];

export default function FormGroupEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { type, name, legend, required } = attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	// 'choices' fallback mirrors the server (blocklane_pro_forms_collect_fields
	// derives the group name with the same fallback) — the preview must match.
	const resolvedName = useResolvedFieldName(
		{ name, label: legend },
		'choices'
	);

	const blockProps = useBlockProps( {
		className:
			'blocklane-form__field blocklane-form__fieldset is-type-' +
			type +
			( required ? ' is-required' : '' ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{},
		{ template: OPTIONS_TEMPLATE }
	);

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
							legend: '',
							type: 'radio',
							required: false,
							name: '',
						} )
					}
					dropdownMenuProps={ dropdownMenuProps }
				>
					<FieldLabelItem
						label={ legend }
						controlLabel={ __( 'Legend', 'blocklane' ) }
						onChange={ ( value ) =>
							setAttributes( { legend: value } )
						}
					/>
					<ToolsPanelItem
						hasValue={ () => 'radio' !== type }
						label={ __( 'Choice type', 'blocklane' ) }
						onDeselect={ () => setAttributes( { type: 'radio' } ) }
						isShownByDefault
					>
						<ToggleGroupControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Choice type', 'blocklane' ) }
							value={ type }
							isBlock
							help={ __(
								'Radio: pick one. Checkboxes: pick any.',
								'blocklane'
							) }
							onChange={ ( value ) =>
								setAttributes( { type: value } )
							}
						>
							<ToggleGroupControlOption
								value="radio"
								label={ __( 'Radio', 'blocklane' ) }
							/>
							<ToggleGroupControlOption
								value="checkbox"
								label={ __( 'Checkboxes', 'blocklane' ) }
							/>
						</ToggleGroupControl>
					</ToolsPanelItem>
					<RequiredItem
						required={ required }
						onChange={ ( value ) =>
							setAttributes( { required: value } )
						}
					/>
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
			<fieldset { ...blockProps }>
				<legend className="blocklane-form__legend">
					<RichText
						tagName="span"
						className="blocklane-form__label-text"
						value={ legend }
						onChange={ ( value ) =>
							setAttributes( { legend: value } )
						}
						placeholder={ __( 'Add a legend…', 'blocklane' ) }
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
				</legend>
				<div { ...innerBlocksProps } />
			</fieldset>
		</>
	);
}
