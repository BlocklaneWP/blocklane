/**
 * ListToolbar — the row above a Settings tab's list: the tab's bulk button
 * and its search box (the C-settings spec (2026-10-12) §3.3).
 * Core parts only: a compact secondary Button (busy while a bulk write
 * runs) and a compact SearchControl. Every label is the caller's: the
 * sentences depend on the tab's noun (meta `noun`), which only the screen
 * knows.
 */
import { Button, SearchControl } from '@wordpress/components';

/**
 * @param {Object}                  props
 * @param {string}                  props.bulkLabel         The bulk button's label.
 * @param {() => void}              props.onBulk            Runs the bulk change.
 * @param {boolean}                 [props.bulkDisabled]    Nothing on the tab can change.
 * @param {boolean}                 [props.busy]            A bulk write is running.
 * @param {string}                  props.search            The query.
 * @param {(query: string) => void} props.onSearch          ( query ) → void.
 * @param {string}                  props.searchLabel       The search box's accessible name.
 * @param {string}                  props.searchPlaceholder Its placeholder.
 * @return {JSX.Element} The toolbar.
 */
export const ListToolbar = ( {
	bulkLabel,
	onBulk,
	bulkDisabled = false,
	busy = false,
	search,
	onSearch,
	searchLabel,
	searchPlaceholder,
} ) => (
	<div className="blocklane-list-toolbar">
		<div className="blocklane-list-toolbar__search">
			<SearchControl
				__nextHasNoMarginBottom
				size="compact"
				label={ searchLabel }
				placeholder={ searchPlaceholder }
				value={ search }
				onChange={ onSearch }
			/>
		</div>
		<div className="blocklane-list-toolbar__bulk">
			<Button
				variant="secondary"
				size="compact"
				isBusy={ busy }
				disabled={ busy || bulkDisabled }
				onClick={ onBulk }
			>
				{ bulkLabel }
			</Button>
		</div>
	</div>
);

export default ListToolbar;
