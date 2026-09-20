/**
 * Site Visibility screen — the whole-site Coming Soon / Maintenance gate, plus
 * the shared "discourage search engines" (blog_public) control.
 *
 * Wired to GET/POST blocklane-pro/v1/site-lock. The splash itself is an editable
 * block template (Site Editor), so this screen only controls the toggle, the
 * mode, and the shared password. The settings GET never carries the cleartext
 * password — only hasPassword/canReveal flags; clicking the eye fetches it on
 * demand from /site-lock/reveal. Legacy hash-only passwords can't be revealed
 * (hasPassword: true, canReveal: false).
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
	Flex,
	FlexItem,
	Button,
	Notice,
	Spinner,
	ToggleControl,
	SelectControl,
	CheckboxControl,
	__experimentalInputControl as InputControl,
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
	__experimentalDivider as Divider,
} from '@wordpress/components';
import { seen as seenIcon, unseen as unseenIcon } from '@wordpress/icons';

import { HelpTip } from '../components/HelpTip';

import { siteLock as siteLockApi } from '../api/client';
import { HelpTab, useHelpPreference } from '../components/HelpTab';
import {
	ScreenDisabledNotice,
	isToolScreenOn,
} from '../components/ScreenDisabledNotice';
import { errorMessage } from '../api/errors';
import { pluginName } from '../edition';

export const SitePrivacyScreen = () => {
	const [ loading, setLoading ] = useState( true );
	const [ helpOpen, setHelpOpen ] = useHelpPreference( 'site-privacy' );
	const [ enabled, setEnabled ] = useState( false );
	const [ mode, setMode ] = useState( 'coming-soon' );
	const [ hasPassword, setHasPassword ] = useState( false );
	const [ canReveal, setCanReveal ] = useState( false );
	// Only what the admin has typed (or revealed) this session — '' = unchanged.
	const [ password, setPassword ] = useState( '' );
	const [ showPassword, setShowPassword ] = useState( false );
	const [ clearPassword, setClearPassword ] = useState( false );
	const [ editUrls, setEditUrls ] = useState( {} );
	const [ previewUrl, setPreviewUrl ] = useState( '' );
	const [ copied, setCopied ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );
	// Core "Discourage search engines" setting (blog_public), shared with
	// Settings → Reading — the same option, so it stays in sync automatically.
	const [ discourageSearch, setDiscourageSearch ] = useState( false );

	const pageStart = useRef( null );

	// Sibling of the current admin.php page, so it works on subdirectory installs.
	const readingUrl = window.location.pathname.replace(
		/admin\.php$/,
		'options-reading.php'
	);

	useEffect( () => {
		siteLockApi
			.get()
			.then( ( res ) => {
				setEnabled( !! res?.enabled );
				setMode( res?.mode || 'coming-soon' );
				setHasPassword( !! res?.hasPassword );
				setCanReveal( !! res?.canReveal );
				setEditUrls( res?.editUrls || {} );
				setPreviewUrl( res?.previewUrl || '' );
				setDiscourageSearch( !! res?.discourageSearch );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		if ( ! loading ) {
			pageStart.current?.focus();
		}
	}, [ loading ] );

	const isComingSoon = 'coming-soon' === mode;
	const currentEditUrl = editUrls[ mode ] || '';

	const dirty = () => setSaved( false );

	const save = () => {
		setIsBusy( true );
		setError( '' );
		setSaved( false );
		siteLockApi
			.save( {
				enabled,
				mode,
				password,
				clearPassword,
				discourageSearch,
			} )
			.then( ( res ) => {
				setEnabled( !! res?.enabled );
				setMode( res?.mode || 'coming-soon' );
				setHasPassword( !! res?.hasPassword );
				setCanReveal( !! res?.canReveal );
				setDiscourageSearch( !! res?.discourageSearch );
				setClearPassword( false );
				setSaved( true );
				// The lock state drives the server-rendered toolbar badge, so reload
				// to reflect it (the save has already resolved here).
				window.location.reload();
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setIsBusy( false ) );
	};

	const regeneratePreview = () => {
		setIsBusy( true );
		setError( '' );
		setCopied( false );
		siteLockApi
			.save( { enabled, mode, regeneratePreview: true } )
			.then( ( res ) => {
				setEnabled( !! res?.enabled );
				setMode( res?.mode || 'coming-soon' );
				setHasPassword( !! res?.hasPassword );
				setCanReveal( !! res?.canReveal );
				setPreviewUrl( res?.previewUrl || '' );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setIsBusy( false ) );
	};

	// The eye: with nothing typed and a revealable saved password, the first
	// "show" fetches the cleartext on demand (it never rides on the settings
	// GET); otherwise it just toggles visibility of what's in the field.
	const toggleReveal = () => {
		if ( showPassword ) {
			setShowPassword( false );
			return;
		}
		if ( '' === password && hasPassword && canReveal ) {
			setIsBusy( true );
			setError( '' );
			siteLockApi
				.reveal()
				.then( ( res ) => {
					setPassword( res?.password || '' );
					setShowPassword( true );
				} )
				.catch( ( err ) => setError( errorMessage( err ) ) )
				.finally( () => setIsBusy( false ) );
			return;
		}
		setShowPassword( true );
	};

	const copyPreview = () => {
		if ( ! previewUrl || ! navigator?.clipboard ) {
			return;
		}
		navigator.clipboard
			.writeText( previewUrl )
			.then( () => {
				setCopied( true );
				window.setTimeout( () => setCopied( false ), 2000 );
			} )
			.catch( () => {
				// Clipboard blocked (insecure context or denied) — the preview
				// URL stays visible in the field for manual copy.
			} );
	};

	// Coming Soon is only a real gate once a password exists — an empty one leaves
	// the site public. "Will have a password" accounts for the pending edits: a
	// newly typed one or the existing stored one (removal via clear wipes it).
	const willHavePassword =
		! clearPassword && ( '' !== password || hasPassword );
	// Explicitly removing the password is a valid reset: on save the server clears
	// it and switches Coming Soon off (returning the site to public).
	const removingProtection = enabled && isComingSoon && clearPassword;
	// The one blocked case is turning Coming Soon *on* without ever setting a
	// password — prompt for one instead of saving an inert gate. (Removal is fine.)
	const needsPassword =
		enabled && isComingSoon && ! willHavePassword && ! clearPassword;

	let statusLabel = __( 'Public — anyone can view the site.', 'blocklane' );
	let statusClass = 'is-public';
	if ( removingProtection ) {
		statusLabel = __(
			'Removing the password returns the site to public.',
			'blocklane'
		);
		statusClass = 'is-public';
	} else if ( needsPassword ) {
		statusLabel = __(
			'Coming Soon needs a site password to go live.',
			'blocklane'
		);
		statusClass = 'is-incomplete';
	} else if ( enabled && isComingSoon ) {
		statusLabel = __(
			'Coming Soon — visitors see a 200 password page.',
			'blocklane'
		);
		statusClass = 'is-locked';
	} else if ( enabled ) {
		statusLabel = __(
			'Maintenance — visitors see a 503 “be right back” page.',
			'blocklane'
		);
		statusClass = 'is-maintenance';
	}

	// Help copy under the password field — one of four mutually exclusive states.
	let passwordHelp = __(
		'Visitors enter this to view the site. Coming Soon won’t go live until you set one.',
		'blocklane'
	);
	if ( clearPassword ) {
		passwordHelp = __(
			'The saved password will be removed and Coming Soon switched off, returning the site to public.',
			'blocklane'
		);
	} else if ( hasPassword && '' !== password ) {
		passwordHelp = __(
			'Saving will set the site password to this value. Or remove it below.',
			'blocklane'
		);
	} else if ( hasPassword && canReveal ) {
		passwordHelp = __(
			'A site password is set — use the eye to reveal it, type to replace it, or remove it below.',
			'blocklane'
		);
	} else if ( hasPassword ) {
		passwordHelp = __(
			'A password was set before this update, so it can’t be shown — type a new one to change and reveal it.',
			'blocklane'
		);
	}

	if ( ! isToolScreenOn( 'site-privacy' ) ) {
		return (
			<ScreenDisabledNotice
				title={ __( 'Site Visibility', 'blocklane' ) }
				body={ __(
					'Turning it off only hides this screen; whatever visibility you set stays in effect.',
					'blocklane'
				) }
				slug="site-privacy"
			/>
		);
	}

	return (
		<Flex
			align="stretch"
			gap="0"
			className={
				helpOpen
					? 'blocklane-pro-extensions blocklane-pro-site-privacy'
					: 'blocklane-pro-extensions blocklane-pro-site-privacy is-help-closed'
			}
		>
			<FlexItem className="blocklane-pro-extensions__sidebar">
				<section className="blocklane-pro-extensions__section">
					<header className="blocklane-pro-extensions__header">
						<h1
							className="blocklane-pro-extensions__title"
							ref={ pageStart }
							tabIndex={ -1 }
						>
							{ __( 'Site Visibility', 'blocklane' ) }
						</h1>
					</header>

					{ error ? (
						<Notice
							status="error"
							onRemove={ () => setError( '' ) }
						>
							{ error }
						</Notice>
					) : null }
					{ saved ? (
						<Notice
							status="success"
							onRemove={ () => setSaved( false ) }
						>
							{ __( 'Site visibility saved.', 'blocklane' ) }
						</Notice>
					) : null }

					{ loading ? (
						<div className="blocklane-pro-loading">
							<Spinner />
						</div>
					) : (
						<div className="blocklane-pro-site-privacy__form">
							<p
								className={ `blocklane-pro-site-privacy__status ${ statusClass }` }
							>
								{ statusLabel }
							</p>

							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Restrict access to this site',
									'blocklane'
								) }
								help={ __(
									'You and other editors always keep full access while signed in.',
									'blocklane'
								) }
								checked={ enabled }
								onChange={ ( v ) => {
									setEnabled( v );
									dirty();
								} }
							/>

							{ enabled ? (
								<>
									<SelectControl
										__nextHasNoMarginBottom
										__next40pxDefaultSize
										label={ __( 'Mode', 'blocklane' ) }
										value={ mode }
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
										onChange={ ( v ) => {
											setMode( v );
											dirty();
										} }
									/>

									{ isComingSoon ? (
										<div className="blocklane-pro-site-privacy__password">
											<InputControl
												__next40pxDefaultSize
												type={
													showPassword
														? 'text'
														: 'password'
												}
												label={ __(
													'Site password',
													'blocklane'
												) }
												placeholder={
													hasPassword
														? '••••••••'
														: ''
												}
												value={ password }
												disabled={ clearPassword }
												onChange={ ( v ) => {
													setPassword( v ?? '' );
													dirty();
												} }
												suffix={
													<InputControlSuffixWrapper>
														<Button
															size="small"
															icon={
																showPassword
																	? unseenIcon
																	: seenIcon
															}
															label={
																showPassword
																	? __(
																			'Hide password',
																			'blocklane'
																		)
																	: __(
																			'Show password',
																			'blocklane'
																		)
															}
															onClick={
																toggleReveal
															}
															disabled={
																clearPassword ||
																isBusy
															}
														/>
													</InputControlSuffixWrapper>
												}
											/>
											<p className="blocklane-pro-site-privacy__help">
												{ passwordHelp }
											</p>
											{ hasPassword ? (
												<CheckboxControl
													__nextHasNoMarginBottom
													label={ __(
														'Remove the current password',
														'blocklane'
													) }
													checked={ clearPassword }
													onChange={ ( v ) => {
														setClearPassword( v );
														dirty();
													} }
												/>
											) : null }
										</div>
									) : null }

									{ currentEditUrl ? (
										<p className="blocklane-pro-site-privacy__edit">
											<a href={ currentEditUrl }>
												{ isComingSoon
													? __(
															'Edit the Coming Soon page in the Site Editor →',
															'blocklane'
														)
													: __(
															'Edit the Maintenance page in the Site Editor →',
															'blocklane'
														) }
											</a>
										</p>
									) : null }

									{ previewUrl ? (
										<div className="blocklane-pro-site-privacy__preview">
											<p className="blocklane-pro-site-privacy__preview-label">
												{ __(
													'Shareable preview link',
													'blocklane'
												) }
											</p>
											<p className="blocklane-pro-site-privacy__help">
												{ __(
													'Anyone with this link can view the site without the password. Regenerate it to revoke links you’ve already shared.',
													'blocklane'
												) }
											</p>
											<Flex
												gap={ 2 }
												align="center"
												justify="flex-start"
												className="blocklane-pro-site-privacy__preview-row"
											>
												<FlexItem isBlock>
													<InputControl
														__next40pxDefaultSize
														readOnly
														value={ previewUrl }
														onFocus={ ( e ) =>
															e.target.select()
														}
													/>
												</FlexItem>
												<Button
													variant="secondary"
													__next40pxDefaultSize
													onClick={ copyPreview }
												>
													{ copied
														? __(
																'Copied',
																'blocklane'
															)
														: __(
																'Copy',
																'blocklane'
															) }
												</Button>
											</Flex>
											<Button
												variant="link"
												onClick={ regeneratePreview }
												disabled={ isBusy }
												className="blocklane-pro-site-privacy__regen"
											>
												{ __(
													'Regenerate link',
													'blocklane'
												) }
											</Button>
										</div>
									) : null }
								</>
							) : null }

							<Divider className="blocklane-pro-site-privacy__rule" />

							<div className="blocklane-pro-site-privacy__search">
								<CheckboxControl
									__nextHasNoMarginBottom
									className="blocklane-pro-site-privacy__search-check"
									label={
										// Exact core wording (Settings → Reading).
										__(
											'Discourage search engines from indexing this site',
											'blocklane'
										)
									}
									help={ __(
										'It is up to search engines to honor this request.',
										'blocklane'
									) }
									checked={ discourageSearch }
									onChange={ ( v ) => {
										setDiscourageSearch( v );
										dirty();
									} }
								/>
								<HelpTip
									label={ __(
										'Where this setting lives',
										'blocklane'
									) }
									text={
										<>
											{ __(
												'This is the same setting as Settings → Reading — changing it in either place updates the other.',
												'blocklane'
											) }
											<br />
											<a
												className="blocklane-pro-site-privacy__search-link"
												href={ readingUrl }
											>
												{ __(
													'Open Settings → Reading',
													'blocklane'
												) }
											</a>
										</>
									}
								/>
							</div>

							<Flex
								justify="flex-end"
								className="blocklane-pro-site-privacy__actions"
							>
								<Button
									variant="primary"
									__next40pxDefaultSize
									isBusy={ isBusy }
									disabled={ isBusy || needsPassword }
									onClick={ save }
								>
									{ __( 'Save', 'blocklane' ) }
								</Button>
							</Flex>
						</div>
					) }
				</section>
			</FlexItem>

			<HelpTab
				isOpen={ helpOpen }
				onToggle={ () => setHelpOpen( ! helpOpen ) }
				panelId="blocklane-pro-help-panel-site-privacy"
			/>
			<FlexItem
				className="blocklane-pro-extensions__preview"
				id="blocklane-pro-help-panel-site-privacy"
				aria-hidden={ ! helpOpen }
			>
				<section className="blocklane-pro-extensions__preview-content">
					<p>
						<strong>{ __( 'Overview', 'blocklane' ) }</strong>
					</p>
					<p>
						{ __(
							'Put the whole front end behind a shared password (Coming soon) or temporarily close it for everyone (Maintenance) — like a pre-launch gate. Your dashboard and editing are never affected.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'Design the page', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ __(
							'The splash is an editable block template — open it in the Site Editor to add your logo, headline, and message. Drop in the “Password Form” block wherever you want the entry field.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>{ __( 'Safe by design', 'blocklane' ) }</strong>
					</p>
					<p>
						{ sprintf(
							/* translators: %s: the plugin name. */
							__(
								'Signed-in editors always bypass the gate, search engines are told not to index it, and deactivating %s makes the site public again — so you can never lock yourself out.',
								'blocklane'
							),
							pluginName()
						) }
					</p>
				</section>
			</FlexItem>
		</Flex>
	);
};
