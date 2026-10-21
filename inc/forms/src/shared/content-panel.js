/**
 * The "Text" panel in the block inspector's content group: every copy
 * attribute a field edits outside the canvas (its label, placeholder, empty
 * option, sending label). Core 7.1 shows only the content group when a block
 * is edited in content-only mode, which is how a page built from a pattern
 * opens for most editors, so copy kept in the default group could not be
 * edited there at all (spec 2026-10-15-bundled-patterns D9 follow-up).
 * Settings that are not copy stay in each block's own panel.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	TextControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import useToolsPanelDropdownMenuProps from '../../../shared/use-tools-panel-dropdown-menu-props';

/**
 * One copy attribute as a text field in the panel.
 *
 * @param {Object}                  props
 * @param {string}                  props.label    Control label.
 * @param {string}                  props.value    Current value.
 * @param {(value: string) => void} props.onChange Receives the next value.
 * @param {string}                  [props.help]   Help text.
 * @param {boolean}                 [props.shown]  Shown without opting in (the default: copy is never hidden behind a menu).
 */
export function ContentTextItem( {
	label,
	value,
	onChange,
	help,
	shown = true,
} ) {
	return (
		<ToolsPanelItem
			hasValue={ () => '' !== value }
			label={ label }
			onDeselect={ () => onChange( '' ) }
			isShownByDefault={ shown }
		>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				value={ value }
				onChange={ onChange }
			/>
		</ToolsPanelItem>
	);
}

/**
 * The panel itself.
 *
 * @param {Object}     props
 * @param {() => void} props.resetAll Clears every item.
 * @param {Element}    props.children The items.
 */
export default function ContentTextPanel( { resetAll, children } ) {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	return (
		<InspectorControls group="content">
			<ToolsPanel
				label={ __( 'Text', 'blocklane' ) }
				resetAll={ resetAll }
				dropdownMenuProps={ dropdownMenuProps }
			>
				{ children }
			</ToolsPanel>
		</InspectorControls>
	);
}
