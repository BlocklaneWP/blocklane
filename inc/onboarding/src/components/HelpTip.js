/**
 * Help-tip — a small info glyph that opens a popover with explanatory text.
 *
 * Click-to-open Dropdown (mirrors the blocks' blocklane-pro-help-tip). Shared
 * across dashboard screens; styled via the .blocklane-pro-help-tip* rules.
 */

import { __ } from '@wordpress/i18n';
import { Dropdown, Icon } from '@wordpress/components';
import { info as infoIcon } from '@wordpress/icons';

export const HelpTip = ( { text, label } ) => (
	<Dropdown
		className="blocklane-pro-help-tip"
		contentClassName="blocklane-pro-help-tip__popover"
		focusOnMount="container"
		popoverProps={ { placement: 'top', offset: 8, shift: true } }
		renderToggle={ ( { isOpen, onToggle } ) => (
			<button
				type="button"
				className="blocklane-pro-help-tip__toggle"
				aria-label={ label || __( 'More information', 'blocklane' ) }
				aria-expanded={ isOpen }
				onClick={ onToggle }
			>
				<Icon icon={ infoIcon } size={ 18 } />
			</button>
		) }
		renderContent={ () => (
			<p className="blocklane-pro-help-tip__text">{ text }</p>
		) }
	/>
);
