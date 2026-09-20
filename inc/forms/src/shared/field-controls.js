/**
 * Shared field-inspector controls — the Field-label mirror, the Required
 * toggle, and the Field-name override that every field block (input,
 * textarea, select, group) exposes. Extracted so the copy, the i18n, and
 * the name-preview stay in ONE place (they had drifted: a concatenated
 * help string, and a group that previewed the wrong fallback).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { stripTags } from './strip-tags';
import useFormFieldNames from './use-form-field-names';

/**
 * The "Field label" item — an inspector mirror of the canvas RichText label
 * (Navigation Link's Text field precedent: same attribute, two surfaces).
 * The label may carry bold/italic from the canvas; here it shows stripped,
 * and editing here writes plain text.
 *
 * @param {Object}   props
 * @param {string}   props.label          Current label value (may hold markup).
 * @param {Function} props.onChange       Receives the next plain-text string.
 * @param {string}   [props.controlLabel] Control label ('Field label').
 */
export function FieldLabelItem( { label, onChange, controlLabel } ) {
	const text = controlLabel || __( 'Field label', 'blocklane' );
	return (
		<ToolsPanelItem
			hasValue={ () => '' !== label }
			label={ text }
			onDeselect={ () => onChange( '' ) }
			isShownByDefault
		>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ text }
				value={ stripTags( label ) }
				onChange={ onChange }
			/>
		</ToolsPanelItem>
	);
}

/**
 * The "Required" opt-in toggle item.
 *
 * @param {Object}   props
 * @param {boolean}  props.required Current value.
 * @param {Function} props.onChange Receives the next boolean.
 */
export function RequiredItem( { required, onChange } ) {
	return (
		<ToolsPanelItem
			hasValue={ () => required }
			label={ __( 'Required', 'blocklane' ) }
			onDeselect={ () => onChange( false ) }
			isShownByDefault
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Required', 'blocklane' ) }
				checked={ required }
				onChange={ onChange }
			/>
		</ToolsPanelItem>
	);
}

/**
 * The "Hide label" item. The label element STAYS in the DOM for assistive
 * tech — the class only clips it visually — and with no authored placeholder
 * the label text folds into the control as its placeholder.
 *
 * @param {Object}   props
 * @param {boolean}  props.hideLabel Current value.
 * @param {Function} props.onChange  Receives the next boolean.
 */
export function HideLabelItem( { hideLabel, onChange } ) {
	return (
		<ToolsPanelItem
			hasValue={ () => !! hideLabel }
			label={ __( 'Hide label', 'blocklane' ) }
			onDeselect={ () => onChange( false ) }
			isShownByDefault={ false }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Hide label', 'blocklane' ) }
				help={ __(
					'Visually hides the label; screen readers still announce it. Without a placeholder, the label text shows inside the field.',
					'blocklane'
				) }
				checked={ !! hideLabel }
				onChange={ onChange }
			/>
		</ToolsPanelItem>
	);
}

/**
 * The "Field name" override item. The help previews the name the SERVER will
 * derive (see field-name.js), so callers pass the already-resolved name.
 *
 * @param {Object}   props
 * @param {string}   props.name         Current explicit name ('' = derived).
 * @param {string}   props.resolvedName The derived name to preview.
 * @param {Function} props.onChange     Receives the next string.
 */
export function FieldNameItem( { name, resolvedName, onChange } ) {
	return (
		<ToolsPanelItem
			hasValue={ () => '' !== name }
			label={ __( 'Field name', 'blocklane' ) }
			onDeselect={ () => onChange( '' ) }
		>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Field name', 'blocklane' ) }
				help={ sprintf(
					/* translators: %s: the field name the server will derive. */
					__(
						'The submitted name. Empty derives it from the label — currently: %s',
						'blocklane'
					),
					resolvedName
				) }
				value={ name }
				onChange={ onChange }
			/>
		</ToolsPanelItem>
	);
}

const CONDITION_DEFAULT = {
	enabled: false,
	field: '',
	operator: 'is',
	value: '',
};

const OPERATOR_OPTIONS = [
	{ label: __( 'is', 'blocklane' ), value: 'is' },
	{ label: __( 'is not', 'blocklane' ), value: 'is_not' },
	{ label: __( 'contains', 'blocklane' ), value: 'contains' },
	{ label: __( 'is empty', 'blocklane' ), value: 'empty' },
	{ label: __( 'is not empty', 'blocklane' ), value: 'not_empty' },
	{ label: __( 'is greater than', 'blocklane' ), value: 'gt' },
	{ label: __( 'is less than', 'blocklane' ), value: 'lt' },
];

/**
 * The "Visibility" condition item (v3 conditional logic): show this field
 * only when another field's value satisfies one rule. One rule per field on
 * purpose — simple-first; the attribute shape leaves room for groups later.
 * The hidden state is enforced server-side too (required waived, posted
 * value discarded), so this is behavior, not decoration.
 *
 * @param {Object}   props
 * @param {Object}   props.condition Current condition attribute.
 * @param {Function} props.onChange  Receives the next condition object.
 * @param {string}   props.clientId  The field block's clientId.
 */
export function VisibilityItem( { condition, onChange, clientId } ) {
	const formClientId = useSelect(
		( select ) =>
			select( blockEditorStore ).getBlockParentsByBlockName(
				clientId,
				'blocklane/form',
				true
			)[ 0 ] || '',
		[ clientId ]
	);
	const fieldNames = useFormFieldNames( formClientId, {
		excludeClientId: clientId,
	} );
	const cond = { ...CONDITION_DEFAULT, ...( condition || {} ) };
	const needsValue = ! [ 'empty', 'not_empty' ].includes( cond.operator );

	return (
		<ToolsPanelItem
			hasValue={ () => !! cond.enabled }
			label={ __( 'Visibility', 'blocklane' ) }
			onDeselect={ () => onChange( { ...CONDITION_DEFAULT } ) }
		>
			<VStack spacing={ 3 }>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Show only when…', 'blocklane' ) }
					help={ __(
						'Hidden fields aren’t required and their values are discarded — enforced on the server, not just on screen.',
						'blocklane'
					) }
					value={ cond.enabled ? cond.field : '' }
					options={ [
						{
							label: __( 'Always show', 'blocklane' ),
							value: '',
						},
						...fieldNames.map( ( name ) => ( {
							label: name,
							value: name,
						} ) ),
					] }
					onChange={ ( field ) =>
						onChange(
							'' === field
								? { ...CONDITION_DEFAULT }
								: { ...cond, enabled: true, field }
						)
					}
				/>
				{ cond.enabled && (
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Condition', 'blocklane' ) }
						value={ cond.operator }
						options={ OPERATOR_OPTIONS }
						onChange={ ( operator ) =>
							onChange( { ...cond, operator } )
						}
					/>
				) }
				{ cond.enabled && needsValue && (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Value', 'blocklane' ) }
						value={ cond.value }
						onChange={ ( value ) => onChange( { ...cond, value } ) }
					/>
				) }
			</VStack>
		</ToolsPanelItem>
	);
}
