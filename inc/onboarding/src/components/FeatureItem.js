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
 *
 * `pro` marks a row whose feature is NOT in this build (screens/pro-row.js
 * builds those, and only in the free edition; the screens compute the prop
 * with isProRow(), the factory's own predicate, never from a row's own
 * property). This is THE one place either screen's list decides how such a
 * row looks: NO switch of any kind, live or inert, and the "Pro" badge
 * always. A switch beside a feature this plugin does not include — even a
 * grayed, inoperable one — reads as an included feature locked behind a
 * payment, which is what wordpress.org guideline 9 names ("implying users
 * must pay to unlock included features"). The toggle column stays as an
 * empty cell so the titles line up with the live rows above and below.
 *
 * The STATE reaches a screen reader through ProBadge, inside the details
 * button: the visible "Pro" badge is aria-hidden (one word names an owner,
 * not a state) and a visually hidden sentence says which product has the
 * feature and that it is not in this plugin. The details button stays live:
 * reading what a feature does is the whole reason the row is here, and the
 * detail panel carries the one plain link to the Pro site. `disabled` is
 * read only on the live branch; a caller never folds `pro` into it.
 */

import {
	ToggleControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { Icon, chevronRight } from '@wordpress/icons';

import { ProBadge } from './ProBadge';

export const FeatureItem = ( {
	title,
	description,
	checked,
	disabled,
	isActive,
	itemKey,
	badge,
	pro,
	onChange,
	onLearnMore,
	onOpen,
	onHover,
} ) => {
	// The badge is not optional on a Pro row: ProBadge defaults its label, so
	// a new caller cannot produce an unbadged Pro row by forgetting an
	// argument.
	return (
		<li>
			<HStack
				alignment="center"
				justify="flex-start"
				spacing={ 7 }
				className={ `blocklane-pro-feature-item${
					isActive ? ' is-active' : ''
				}${ pro ? ' is-pro' : '' }` }
				onMouseEnter={ onHover ? () => onHover( itemKey ) : undefined }
			>
				<div className="blocklane-pro-feature-item__toggle">
					{ ! pro && (
						<ToggleControl
							hideLabelFromVision
							label={ title }
							checked={ checked }
							// Toggling also opens the item's panel, so any
							// per-feature settings there are revealed without a
							// second click — opens, never toggles (see the header).
							onChange={ ( value ) => {
								onChange( value );
								( onOpen || onLearnMore )( itemKey );
							} }
							disabled={ disabled }
							__nextHasNoMarginBottom
						/>
					) }
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
							{ pro ? (
								<ProBadge label={ badge } />
							) : (
								badge && (
									<span className="blocklane-pro-feature-item__badge">
										{ badge }
									</span>
								)
							) }
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
};
