/**
 * Form Step edit: the honest edit surface for stepped forms — steps render
 * STACKED with a visible boundary header (step number + label); the front's
 * one-at-a-time reveal is runtime behavior, like popup display rules. The
 * step's fields edit exactly as they render (the fields themselves stay 1:1).
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function FormStepEdit( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { label } = attributes;

	const stepNumber = useSelect(
		( select ) => {
			const sel = select( blockEditorStore );
			const parent = sel.getBlockRootClientId( clientId );
			const siblings = sel.getBlock( parent )?.innerBlocks || [];
			let index = 0;
			for ( const sibling of siblings ) {
				if ( 'blocklane/form-step' === sibling.name ) {
					index++;
					if ( sibling.clientId === clientId ) {
						return index;
					}
				}
			}
			return index || 1;
		},
		[ clientId ]
	);

	const blockProps = useBlockProps( {
		className: 'blocklane-form__step',
	} );
	// No template: templates re-apply to ANY empty container at mount, so
	// an intentionally (or historically) empty step would sprout a zombie
	// field every time the editor opened. Quick inserter leads with fields
	// (the form wrapper's prioritized list, minus structure blocks).
	const innerBlocksProps = useInnerBlocksProps(
		{},
		{
			prioritizedInserterBlocks: [
				'blocklane/form-input',
				'blocklane/form-textarea',
				'blocklane/form-select',
				'blocklane/form-group',
				'blocklane/form-file',
			],
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Step', 'blocklane' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Step label', 'blocklane' ) }
						help={ __(
							'Shown in the progress indicator above the form.',
							'blocklane'
						) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div
					className="blocklane-form__step-boundary"
					contentEditable={ false }
				>
					{ '' !== label.trim()
						? sprintf(
								/* translators: 1: step number, 2: step label. */
								__( 'Step %1$d — %2$s', 'blocklane' ),
								stepNumber,
								label.trim()
							)
						: sprintf(
								/* translators: %d: step number. */
								__( 'Step %d', 'blocklane' ),
								stepNumber
							) }
				</div>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
