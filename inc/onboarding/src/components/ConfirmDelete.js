/**
 * ConfirmDelete — the destructive confirm (design spec W1 `BL/ConfirmDelete`,
 * R4a, and V3's code note: core's ConfirmDialog has no destructive confirm,
 * so this is one Modal-based part every delete door uses). Authored from the
 * frames `90:27839`, `153:73760` and `115:62248` and core's Modal and Button.
 *
 * Two shapes of one part:
 *   - `ConfirmDeleteBody`: the sentence and the two buttons, for a host that
 *     draws its own titled Modal (DataViews draws one for an action's
 *     RenderModal, titled by its `modalHeader`);
 *   - `ConfirmDelete`: the same body inside core's titled Modal (R4: every
 *     dialog shows its title, which is also its accessible name).
 *
 * The Forms screen was its first user; the module screens adopt it from
 * here.
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';

/**
 * @param {Object}      props
 * @param {string}      [props.message]      What deleting does (the body); absent
 *                                           when the children say it instead.
 * @param {JSX.Element} [props.children]     More of the body, under the message and
 *                                           above the buttons (a delete-with-data
 *                                           checkbox and its warning, design
 *                                           spec X17).
 * @param {string}      [props.confirmLabel] The destructive button ("Delete").
 * @param {boolean}     [props.isBusy]       A delete in flight.
 * @param {() => void}  props.onCancel       Closes without deleting.
 * @param {() => void}  props.onConfirm      Deletes.
 * @return {JSX.Element} The body.
 */
export const ConfirmDeleteBody = ( {
	message,
	children,
	confirmLabel,
	isBusy = false,
	onCancel,
	onConfirm,
} ) => (
	<div className="blocklane-confirm-delete">
		{ message ? (
			<p className="blocklane-confirm-delete__message">{ message }</p>
		) : null }
		{ children }
		<div className="blocklane-confirm-delete__actions">
			<Button
				variant="tertiary"
				__next40pxDefaultSize
				disabled={ isBusy }
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'blocklane' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive
				__next40pxDefaultSize
				isBusy={ isBusy }
				disabled={ isBusy }
				onClick={ onConfirm }
				data-confirm="true"
			>
				{ confirmLabel || __( 'Delete', 'blocklane' ) }
			</Button>
		</div>
	</div>
);

/**
 * @param {Object}     props
 * @param {string}     props.title    The question ("Delete 1 submission?").
 * @param {() => void} props.onCancel Closes without deleting (also Escape and
 *                                    the header's close button).
 * @return {JSX.Element} The titled dialog.
 */
export const ConfirmDelete = ( { title, onCancel, ...body } ) => (
	<Modal
		title={ title }
		onRequestClose={ onCancel }
		size="medium"
		className="blocklane-confirm-delete-modal"
	>
		<ConfirmDeleteBody onCancel={ onCancel } { ...body } />
	</Modal>
);
