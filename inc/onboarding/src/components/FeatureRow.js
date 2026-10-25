/**
 * FeatureRow — one row of a Settings list (spec
 * the C-settings spec (2026-10-12) §3.3; design spec Amendments C2, E,
 * F4/4b, G; Amendment 1 SD1, SD18).
 *
 * Anatomy, left to right: the 32×20 switch cell, then the text column (the
 * title with its badge, the one-line description, an enforced row's reason,
 * and — while the row is ON — its options), then the actions ("Open
 * <feature>" when a live row is ON and has a screen, and always the info
 * button that opens the row's details card).
 *
 * Three kinds, computed by the caller's ONE function (the stores door's
 * kindOf), never inferred here:
 * - `live`: a real switch (core FormToggle), named by the title and described
 *   by the description. Toggling writes; it never selects the row.
 * - `pro`: a feature this build does not include. The switch cell holds an
 *   inert visual in the off position that is presentation only (SD18,
 *   Amendment Z4): an aria-hidden span with no role, no focus, no handler
 *   and never an `<input disabled>`. The badge pair (ProBadge) says whose
 *   feature it is; the info button, the row's one tab stop, is described by
 *   that sentence. No options and no Open button, whatever the caller hands.
 * - `enforced`: ON and disabled, badged "Enforced", with the reason beside
 *   it as the switch's accessible description (SD1, design rule R6).
 *
 * Selection is the info button's alone: `aria-expanded` and `aria-controls`
 * (never `aria-pressed`), and a class that swaps its glyph's color token.
 * The row itself takes no tint (Amendment G; SD3). A pointer click on the
 * row's summary selects too; the info button is the keyboard path to the
 * same thing. Options sit outside the summary, so working an option never
 * selects the row.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, FormToggle } from '@wordpress/components';
import { info } from '@wordpress/icons';

import { ProBadge, proSentenceId } from './ProBadge';
import { StatusBadge } from './StatusBadge';

/** The kinds a row can be. */
export const ROW_KINDS = Object.freeze( [ 'live', 'pro', 'enforced' ] );

/**
 * @param {Object}                  props
 * @param {string}                  props.slug        Row slug: keys every id and the anchors.
 * @param {string}                  props.kind        'live' | 'pro' | 'enforced'.
 * @param {string}                  props.title       The feature's name.
 * @param {string}                  props.description One line.
 * @param {boolean}                 [props.checked]   Whether a live row is ON.
 * @param {boolean}                 [props.busy]      A write this row is part of is in flight.
 * @param {boolean}                 [props.selected]  Whether its details card is open.
 * @param {string}                  [props.openHref]  The feature's screen, when it has one.
 * @param {string}                  [props.openLabel] "Open <feature>".
 * @param {string}                  [props.reason]    An enforced row's reason line.
 * @param {(next: boolean) => void} props.onToggle    ( next: boolean ) → void.
 * @param {(slug: string) => void}  props.onSelect    ( slug ) → void.
 * @param {string}                  props.detailsId   The details card's id (aria-controls).
 * @param {JSX.Element}             [props.children]  The row's options, shown while it is ON.
 * @return {JSX.Element} The row.
 */
export const FeatureRow = ( {
	slug,
	kind,
	title,
	description,
	checked = false,
	busy = false,
	selected = false,
	openHref,
	openLabel,
	reason,
	onToggle,
	onSelect,
	detailsId,
	children,
} ) => {
	if ( ! ROW_KINDS.includes( kind ) ) {
		throw new Error( `FeatureRow "${ slug }": unknown kind "${ kind }"` );
	}
	const pro = 'pro' === kind;
	const enforced = 'enforced' === kind;
	const on = ! pro && ( enforced || checked );
	const ids = {
		title: `blocklane-row-title-${ slug }`,
		description: `blocklane-row-description-${ slug }`,
		reason: `blocklane-row-reason-${ slug }`,
	};

	let badge = null;
	if ( pro ) {
		badge = <ProBadge slug={ slug } />;
	} else if ( enforced ) {
		badge = (
			<StatusBadge status="enforced">
				{ __( 'Enforced', 'blocklane' ) }
			</StatusBadge>
		);
	}

	return (
		<li className="blocklane-feature-row" data-row={ slug }>
			<div className="blocklane-feature-row__switch">
				{ pro ? (
					<span
						className="blocklane-feature-row__switch-inert"
						aria-hidden="true"
					/>
				) : (
					<FormToggle
						checked={ on }
						disabled={ enforced || busy }
						onChange={ ( event ) =>
							onToggle( event.target.checked )
						}
						aria-labelledby={ ids.title }
						aria-describedby={
							enforced && reason
								? `${ ids.description } ${ ids.reason }`
								: ids.description
						}
					/>
				) }
			</div>
			<div className="blocklane-feature-row__text">
				{ /* A pointer shortcut to the info button, which is the
				     keyboard path to the same selection. */ }
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */ }
				<div
					className="blocklane-feature-row__summary"
					onClick={ () => onSelect( slug ) }
				>
					<div className="blocklane-feature-row__heading">
						<span
							className="blocklane-feature-row__title"
							id={ ids.title }
						>
							{ title }
						</span>
						{ badge ? (
							<span className="blocklane-feature-row__badge">
								{ badge }
							</span>
						) : null }
					</div>
					<p
						className="blocklane-feature-row__description"
						id={ ids.description }
					>
						{ description }
					</p>
					{ enforced && reason ? (
						<p
							className="blocklane-feature-row__reason"
							id={ ids.reason }
						>
							{ reason }
						</p>
					) : null }
				</div>
				{ on && children ? (
					<div
						className="blocklane-feature-row__options"
						data-options={ slug }
					>
						{ children }
					</div>
				) : null }
			</div>
			<div className="blocklane-feature-row__actions">
				{ 'live' === kind && on && openHref ? (
					<Button
						className="blocklane-feature-row__open"
						variant="secondary"
						size="small"
						href={ openHref }
					>
						{ openLabel }
					</Button>
				) : null }
				<Button
					className={
						'blocklane-feature-row__info' +
						( selected ? ' is-selected' : '' )
					}
					icon={ info }
					size="small"
					label={ sprintf(
						/* translators: %s: the feature's name. */
						__( 'About %s', 'blocklane' ),
						title
					) }
					aria-expanded={ selected }
					aria-controls={ selected ? detailsId : undefined }
					aria-describedby={ pro ? proSentenceId( slug ) : undefined }
					onClick={ () => onSelect( slug ) }
				/>
			</div>
		</li>
	);
};

export default FeatureRow;
