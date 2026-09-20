/**
 * Core's ToolsPanel options-menu placement.
 *
 * Core makes every block-support ToolsPanel fly its "enable controls"
 * dropdown out flush with the sidebar's left edge on desktop via a private
 * block-editor hook (useToolsPanelDropdownMenuProps in global-styles/utils).
 * It isn't exported publicly, so this mirrors it verbatim — including the
 * offset recipe — and every ToolsPanel we render passes the result as
 * dropdownMenuProps so our panels match core's.
 *
 * @package
 */

import { useViewportMatch } from '@wordpress/compose';

export default function useToolsPanelDropdownMenuProps() {
	const isMobile = useViewportMatch( 'medium', '<' );
	return ! isMobile
		? {
				popoverProps: {
					placement: 'left-start',
					// Inner sidebar width (248px) - button width (24px) -
					// border (1px) + padding (16px) + spacing (20px).
					offset: 259,
				},
			}
		: {};
}
