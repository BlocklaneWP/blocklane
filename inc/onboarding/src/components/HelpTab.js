/**
 * Help tab — core's contextual-help affordance, reworked as a drawer handle.
 * A vertical pull tab rides the seam between the feature list and the help
 * sidebar (a zero-width rail in the flex row, so it tracks the seam through
 * the collapse animation with no hardcoded offsets and stays sticky in
 * view). Closed, the list takes the whole canvas and the tab hugs the right
 * edge; opening slides the panel out from under it. The preference persists
 * per screen; pinning a card always shows the panel regardless — its
 * sub-settings are workflow, not help — and closing the tab unpins.
 */

import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { Icon, chevronRight, help } from '@wordpress/icons';

const storageKey = ( screen ) => `blocklane-pro-help:${ screen }`;

/**
 * The persisted open/closed preference for a screen's help sidebar. Defaults
 * open so a first visit still teaches the screen; localStorage failures
 * (private windows) just mean the choice doesn't stick.
 *
 * @param {string} screen Screen key, e.g. 'extensions'.
 * @return {[boolean, Function]} [preference, setPreference].
 */
export const useHelpPreference = ( screen ) => {
	const [ pref, setPrefState ] = useState( () => {
		try {
			return (
				'closed' !== window.localStorage.getItem( storageKey( screen ) )
			);
		} catch ( e ) {
			return true;
		}
	} );
	const setPref = useCallback(
		( open ) => {
			setPrefState( open );
			try {
				window.localStorage.setItem(
					storageKey( screen ),
					open ? 'open' : 'closed'
				);
			} catch ( e ) {
				// The preference just won't persist this session.
			}
		},
		[ screen ]
	);
	return [ pref, setPref ];
};

/**
 * The pull-tab handle. Rendered between the list and panel flex items.
 * Icon-only: closed it shows the help glyph (what lives behind it), open it
 * shows a collapse chevron; closed it hangs off the list side of the seam,
 * open it flips onto the panel's padding — same tint, so it reads as part
 * of the drawer face and never overlaps the list content.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen   Whether the help sidebar is showing.
 * @param {Function} props.onToggle Toggle handler.
 * @param {string}   props.panelId  id of the sidebar element it controls.
 */
export const HelpTab = ( { isOpen, onToggle, panelId } ) => {
	const label = isOpen
		? __( 'Hide help', 'blocklane' )
		: __( 'Help', 'blocklane' );
	return (
		<div className="blocklane-pro-help-tab-rail">
			<button
				type="button"
				className="blocklane-pro-help-tab"
				aria-expanded={ isOpen }
				aria-controls={ panelId }
				aria-label={ label }
				onClick={ onToggle }
			>
				<Icon icon={ isOpen ? chevronRight : help } size={ 24 } />
				<span className="blocklane-pro-help-tab__label">
					{ __( 'Help', 'blocklane' ) }
				</span>
			</button>
		</div>
	);
};
