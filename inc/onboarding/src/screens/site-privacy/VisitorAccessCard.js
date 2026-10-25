/**
 * The visitor access card (`@card:visitor-access`, design spec Amendment O):
 * the Restrict switch, and while it is on the mode, the site password
 * (Coming soon only), the Site Editor link for the selected mode's page and,
 * after a divider, the shareable preview link.
 */
import { __ } from '@wordpress/i18n';
import { SelectControl, ToggleControl } from '@wordpress/components';

import { PasswordField } from './PasswordField';
import { PreviewLink } from './PreviewLink';

/**
 * @param {Object}                  props
 * @param {Object}                  props.draft        The form.
 * @param {Object}                  props.saved        The stored state.
 * @param {boolean}                 props.busy         A request is running.
 * @param {boolean}                 props.invalid      Save found no password (R6).
 * @param {boolean}                 props.copied       Copy just succeeded.
 * @param {(patch: Object) => void} props.onChange     Edits the form.
 * @param {() => void}              props.onToggleEye  Shows or hides the password.
 * @param {() => void}              props.onCopy       Copies the preview URL.
 * @param {() => void}              props.onRegenerate Rotates the preview key.
 * @return {JSX.Element} The card.
 */
export const VisitorAccessCard = ( {
	draft,
	saved,
	busy,
	invalid,
	copied,
	onChange,
	onToggleEye,
	onCopy,
	onRegenerate,
} ) => {
	const comingSoon = 'coming-soon' === draft.mode;
	const editUrl = ( saved.editUrls || {} )[ draft.mode ] || '';
	return (
		<div
			data-card="visitor-access"
			className="blocklane-site-visibility__card"
		>
			<div data-field="restrict">
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Restrict access to this site', 'blocklane' ) }
					help={ __(
						'You and other editors always keep full access while signed in.',
						'blocklane'
					) }
					checked={ draft.enabled }
					onChange={ ( value ) => onChange( { enabled: value } ) }
				/>
			</div>
			{ draft.enabled ? (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Mode', 'blocklane' ) }
						value={ draft.mode }
						options={ [
							{
								label: __(
									'Coming soon (password to enter)',
									'blocklane'
								),
								value: 'coming-soon',
							},
							{
								label: __(
									'Maintenance (closed to everyone)',
									'blocklane'
								),
								value: 'maintenance',
							},
						] }
						onChange={ ( value ) => onChange( { mode: value } ) }
					/>
					{ comingSoon ? (
						<PasswordField
							draft={ draft }
							saved={ saved }
							busy={ busy }
							invalid={ invalid }
							onChange={ onChange }
							onToggleEye={ onToggleEye }
						/>
					) : null }
					{ editUrl ? (
						<p
							data-edit-link
							className="blocklane-site-visibility__edit"
						>
							<a href={ editUrl }>
								{ comingSoon
									? __(
											'Edit the Coming soon page in the Site Editor →',
											'blocklane'
										)
									: __(
											'Edit the Maintenance page in the Site Editor →',
											'blocklane'
										) }
							</a>
						</p>
					) : null }
					{ saved.previewUrl ? (
						<>
							<hr className="blocklane-site-visibility__divider" />
							<PreviewLink
								url={ saved.previewUrl }
								mode={ draft.mode }
								copied={ copied }
								busy={ busy }
								onCopy={ onCopy }
								onRegenerate={ onRegenerate }
							/>
						</>
					) : null }
				</>
			) : null }
		</div>
	);
};

export default VisitorAccessCard;
