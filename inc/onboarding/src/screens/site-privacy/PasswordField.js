/**
 * The site password, as the frames draw it (`@password`: the field, its
 * help line, the R6 error line, then the removal checkbox; design spec
 * Amendment O, V5, code item 6). The eye button shows the field's text, and
 * with nothing typed it asks the screen to fetch the saved password; it
 * never fetches on its own.
 */
import { __ } from '@wordpress/i18n';
import { useId } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Icon,
	__experimentalInputControl as InputControl,
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
} from '@wordpress/components';
import { error as errorIcon, seen, unseen } from '@wordpress/icons';

import { passwordHelp, passwordRequired } from './copy';
import { passwordChanged } from './status';

/**
 * @param {Object}                  props
 * @param {Object}                  props.draft       The form.
 * @param {Object}                  props.saved       The stored state (hasPassword, canReveal).
 * @param {boolean}                 props.busy        A request is running.
 * @param {boolean}                 props.invalid     Save found no password (R6).
 * @param {(patch: Object) => void} props.onChange    Edits the form.
 * @param {() => void}              props.onToggleEye Shows or hides the password.
 * @return {JSX.Element} The field.
 */
export const PasswordField = ( {
	draft,
	saved,
	busy,
	invalid,
	onChange,
	onToggleEye,
} ) => {
	const id = useId();
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const help = passwordHelp( {
		clearPassword: draft.clearPassword,
		hasPassword: !! saved.hasPassword,
		canReveal: !! saved.canReveal,
		changed: passwordChanged( draft ),
	} );

	return (
		<div data-field="password" className="blocklane-site-visibility__field">
			<InputControl
				__next40pxDefaultSize
				id={ id }
				type={ draft.showPassword ? 'text' : 'password' }
				label={ __( 'Site password', 'blocklane' ) }
				placeholder={ saved.hasPassword ? '••••••••' : '' }
				value={ draft.password }
				disabled={ draft.clearPassword }
				aria-invalid={ invalid ? 'true' : undefined }
				aria-describedby={
					invalid ? `${ helpId } ${ errorId }` : helpId
				}
				onChange={ ( value ) => onChange( { password: value ?? '' } ) }
				suffix={
					<InputControlSuffixWrapper>
						<Button
							size="small"
							className="blocklane-site-visibility__reveal"
							icon={ draft.showPassword ? unseen : seen }
							label={
								draft.showPassword
									? __( 'Hide password', 'blocklane' )
									: __( 'Show password', 'blocklane' )
							}
							disabled={ draft.clearPassword || busy }
							onClick={ onToggleEye }
						/>
					</InputControlSuffixWrapper>
				}
			/>
			<p id={ helpId } className="blocklane-site-visibility__help">
				{ help }
			</p>
			{ invalid ? (
				<p id={ errorId } className="blocklane-site-visibility__error">
					<Icon icon={ errorIcon } size={ 16 } />
					<span>{ passwordRequired() }</span>
				</p>
			) : null }
			{ saved.hasPassword ? (
				<div data-field="remove-password">
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'Remove the current password',
							'blocklane'
						) }
						checked={ draft.clearPassword }
						onChange={ ( value ) =>
							onChange( { clearPassword: value } )
						}
					/>
				</div>
			) : null }
		</div>
	);
};

export default PasswordField;
