/**
 * FeatureToolbar — the row above a toggle screen's feature list: the
 * tab-scoped Enable/Disable All button and the tab-scoped search box.
 * One markup for the Extensions and Advanced screens (each used to carry
 * its own copy).
 *
 * `busy` is the serial-write guard's flag (Extensions): while a loop is in
 * flight the button is disabled but keeps focus (accessibleWhenDisabled —
 * the user has just clicked it) and shows the busy state. Labels stay
 * props: the two screens' search copy differs.
 */
import { __ } from '@wordpress/i18n';
import { Button, SearchControl } from '@wordpress/components';

/**
 * @param {Object}                  props
 * @param {boolean}                 props.allOn             Every toggleable row on the tab is on.
 * @param {() => void}              props.onToggleAll       Click handler.
 * @param {boolean}                 [props.disabled]        Not loaded, or nothing toggleable.
 * @param {boolean}                 [props.busy]            A write loop is in flight.
 * @param {string}                  props.search            The query.
 * @param {(query: string) => void} props.onSearch          Query change handler.
 * @param {string}                  props.searchLabel       Accessible label for the box.
 * @param {string}                  props.searchPlaceholder Placeholder for the box.
 */
export const FeatureToolbar = ( {
	allOn,
	onToggleAll,
	disabled = false,
	busy = false,
	search,
	onSearch,
	searchLabel,
	searchPlaceholder,
} ) => (
	<div className="blocklane-pro-page__toolbar">
		<Button
			variant="secondary"
			size="compact"
			onClick={ onToggleAll }
			disabled={ disabled || busy }
			accessibleWhenDisabled
			isBusy={ busy }
		>
			{ allOn
				? __( 'Disable All', 'blocklane' )
				: __( 'Enable All', 'blocklane' ) }
		</Button>
		<div className="blocklane-pro-extensions__search">
			<SearchControl
				value={ search }
				onChange={ onSearch }
				label={ searchLabel }
				placeholder={ searchPlaceholder }
				size="compact"
				__nextHasNoMarginBottom
			/>
		</div>
	</div>
);
