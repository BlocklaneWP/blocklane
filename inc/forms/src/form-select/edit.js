/**
 * Dropdown field — edit. Choices are form-option inner blocks (the same
 * primitive the choice group uses): while the block or one of its options
 * is selected the canvas shows an editable open list; at rest it shows the
 * real closed <select> built from the children, so the resting state is
 * 1:1 with the front.
 *
 * Legacy dropdowns stored options as an array attribute; those (and the
 * block.json default on fresh inserts) migrate to form-option children on
 * mount. The render + schema sides keep an attribute fallback so unedited
 * legacy content never breaks.
 */
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
	InspectorControls,
	InnerBlocks,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	TextControl,
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
import { useControlStyleProps, toControlProps } from '../shared/control-props';
import { stripTags } from '../shared/strip-tags';

export default function FormSelectEdit( {
	attributes,
	setAttributes,
	clientId,
	isSelected,
} ) {
	const { name, label, hideLabel, required, options, emptyLabel } =
		attributes;

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const resolvedName = useResolvedFieldName( attributes );
	const styleProps = useControlStyleProps( attributes );
	const controlProps = toControlProps( styleProps );
	const editId = 'blf-edit-' + clientId;

	const { hasChildren, isInnerSelected, optionLabels } = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const inner = sel.getBlock( clientId )?.innerBlocks || [];
			return {
				hasChildren: inner.length > 0,
				isInnerSelected: sel.hasSelectedInnerBlock( clientId, true ),
				optionLabels: inner
					.filter(
						( block ) => 'blocklane/form-option' === block.name
					)
					.map( ( block ) => block.attributes?.label || '' ),
			};
		},
		[ clientId ]
	);
	const { replaceInnerBlocks, __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );

	/* Migrate the legacy options array (and the block.json default on fresh
	   inserts) into form-option children. Marked not-persistent so the
	   migration never owns an undo step of its own. */
	useEffect( () => {
		if ( hasChildren || ! Array.isArray( options ) || ! options.length ) {
			return;
		}
		__unstableMarkNextChangeAsNotPersistent?.();
		replaceInnerBlocks(
			clientId,
			options.map( ( option ) =>
				createBlock( 'blocklane/form-option', {
					label: option?.label || '',
					value: option?.value || '',
				} )
			),
			false
		);
		__unstableMarkNextChangeAsNotPersistent?.();
		setAttributes( { options: [] } );
	}, [
		hasChildren,
		options,
		clientId,
		replaceInnerBlocks,
		setAttributes,
		__unstableMarkNextChangeAsNotPersistent,
	] );

	const showRows = isSelected || isInnerSelected;

	const blockProps = useBlockProps( {
		className:
			'blocklane-form__field is-type-select' +
			( required ? ' is-required' : '' ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: [
				'blocklane-form__select-options',
				styleProps.className,
			]
				.filter( Boolean )
				.join( ' ' ),
			style: styleProps.style,
		},
		{
			allowedBlocks: [ 'blocklane/form-option' ],
			renderAppender: showRows ? InnerBlocks.ButtonBlockAppender : false,
		}
	);

	/* Preview <option> text — trimmed like wp_strip_all_tags in
	   blocklane_pro_forms_select_options(): option labels are RichText and
	   may carry bold/italic markup a real <option> cannot show. */
	const previewTexts = optionLabels
		.map( ( optionLabel ) => stripTags( optionLabel ).trim() )
		.filter( Boolean );

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
							name: '',
							emptyLabel: '',
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
						hasValue={ () => '' !== emptyLabel }
						label={ __( 'Empty option label', 'blocklane' ) }
						onDeselect={ () => setAttributes( { emptyLabel: '' } ) }
					>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Empty option label', 'blocklane' ) }
							help={ __(
								'The first, unselected choice. Empty shows a dash.',
								'blocklane'
							) }
							value={ emptyLabel }
							onChange={ ( value ) =>
								setAttributes( { emptyLabel: value } )
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
				{ showRows ? (
					<div { ...innerBlocksProps } />
				) : (
					<select { ...controlProps } id={ editId } tabIndex={ -1 }>
						<option>{ emptyLabel || '—' }</option>
						{ previewTexts.map( ( text, index ) => (
							<option key={ index }>{ text }</option>
						) ) }
					</select>
				) }
			</div>
		</>
	);
}
