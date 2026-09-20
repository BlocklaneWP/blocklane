/**
 * FeatureItem — a single row in a feature list: a toggle on the left, then a
 * details button (title + short description, chevron on the right) that opens the
 * item's detail in the preview panel. Shared by the Extensions and Advanced
 * screens so they read identically.
 *
 * Two ways into the panel, with different semantics: the details button
 * TOGGLES (onLearnMore — clicking the pinned row's button closes it), the
 * switch OPENS (onOpen — flipping a switch settles the panel on that row so
 * its settings are in reach, and must never close the row it just changed).
 * Without onOpen the switch falls back to onLearnMore.
 *
 * The toggle and the details button are SIBLINGS inside a plain row wrapper — the
 * toggle is never nested inside the button, so there's no interactive control
 * inside a button role (valid ARIA). Renders as a real <li> so callers just drop
 * it inside a <ul>.
 */

import {
	ToggleControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { Icon, chevronRight } from '@wordpress/icons';

export const FeatureItem = ( {
	title,
	description,
	checked,
	disabled,
	isActive,
	itemKey,
	badge,
	onChange,
	onLearnMore,
	onOpen,
	onHover,
} ) => (
	<li>
		<HStack
			alignment="center"
			justify="flex-start"
			spacing={ 7 }
			className={ `blocklane-pro-feature-item${
				isActive ? ' is-active' : ''
			}` }
			onMouseEnter={ onHover ? () => onHover( itemKey ) : undefined }
		>
			<div className="blocklane-pro-feature-item__toggle">
				<ToggleControl
					hideLabelFromVision
					label={ title }
					checked={ checked }
					// Toggling also opens the item's panel, so any per-feature
					// settings there are revealed without a second click —
					// opens, never toggles (see the header).
					onChange={ ( value ) => {
						onChange( value );
						( onOpen || onLearnMore )( itemKey );
					} }
					disabled={ disabled }
					__nextHasNoMarginBottom
				/>
			</div>
			<button
				type="button"
				className="blocklane-pro-feature-item__open"
				aria-expanded={ isActive }
				onClick={ () => onLearnMore( itemKey ) }
			>
				<VStack
					spacing={ 1 }
					className="blocklane-pro-feature-item__text"
				>
					<HStack
						spacing={ 2 }
						alignment="center"
						justify="flex-start"
					>
						<span className="blocklane-pro-feature-item__title">
							{ title }
						</span>
						{ badge ? (
							<span className="blocklane-pro-feature-item__badge">
								{ badge }
							</span>
						) : null }
					</HStack>
					<p className="blocklane-pro-feature-item__desc">
						{ description }
					</p>
				</VStack>
				<Icon
					className="blocklane-pro-feature-item__chevron"
					icon={ chevronRight }
					size={ 20 }
				/>
			</button>
		</HStack>
	</li>
);
