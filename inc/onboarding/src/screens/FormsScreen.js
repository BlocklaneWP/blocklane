/**
 * Forms — two tabs, the SEO-screen shell shape. Submissions: a
 * server-driven DataViews list over the self-describing submissions table
 * (filter by form, origin, and status; open an entry; mark read/unread;
 * delete; bulk included) — rows render their stored { label, value }
 * snapshots, so entries stay correct after a form is edited. Settings:
 * site-wide form defaults, spam protection, and storage in bordered
 * sections (seo/SettingsTab idiom). Banners live above both tabs; the
 * Turnstile ones deep-link into the Settings tab's section, the
 * mail-broken banner reports the first failed send (self-clearing), and
 * the SMTP hint surfaces the one deliverability footgun we don't own.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useState,
	useEffect,
	useMemo,
	useRef,
	useCallback,
} from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import {
	Button,
	ExternalLink,
	Modal,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { seen, unseen, trash } from '@wordpress/icons';
import { ScreenHeader } from '../components/ScreenHeader';
import { ScreenTabs, ScreenTabPanel } from '../components/ScreenTabs';
import { useTabShell } from '../components/use-tab-shell';
import { forms as formsApi } from '../api/client';
import { hasUnit } from '../edition.js';
import {
	ScreenDisabledNotice,
	isToolScreenOn,
} from '../components/ScreenDisabledNotice';
import { formatSize } from '../../../forms/src/shared/format-size';
import { errorMessage } from '../api/errors';

// The visible always-pass dummy site key test mode forces server-side (see
// blocklane_pro_forms_turnstile()) — shown in the disabled Site key field so
// the UI displays what is actually in effect, never sent in a save payload.
const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';

const defaultLayouts = {
	table: {},
};

/* Two tabs, the SEO-screen shell shape: Submissions (the inbox) and
   Settings. "Submissions" matches every other surface's noun (the route,
   the copy, the abilities) — deliberately not "Entries". */
const TABS = [
	{ slug: 'submissions', label: __( 'Submissions', 'blocklane' ) },
	{ slug: 'settings', label: __( 'Settings', 'blocklane' ) },
];

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	perPage: 20,
	search: '',
	filters: [],
	titleField: 'form',
	fields: [ 'excerpt', 'origin', 'status', 'date' ],
	...defaultLayouts.table,
};

const fieldDisplayValue = ( field ) =>
	Array.isArray( field.value )
		? field.value.join( ', ' )
		: String( field.value ?? '' );

/* The provenance display name: the origin page's stored title, else the URL's
   path (the full URL — UTMs included — stays visible in the entry modal). */
const originLabel = ( item ) => {
	if ( item.origin?.title ) {
		return item.origin.title;
	}
	if ( item.origin?.url ) {
		try {
			const url = new URL( item.origin.url );
			return url.pathname + url.search;
		} catch ( error ) {
			return item.origin.url;
		}
	}
	return '';
};

function EntryModal( { item, onClose, onToggleStatus, onDelete, onDownload } ) {
	// The download route addresses the flat list of files across ALL file
	// fields, in snapshot order — mirror that numbering here.
	let fileIndex = -1;

	return (
		<Modal
			title={ item.form_name }
			onRequestClose={ onClose }
			className="blocklane-pro-forms-entry"
			size="medium"
		>
			<VStack spacing={ 0 }>
				<p className="blocklane-pro-forms-entry__meta">{ item.date }</p>
				{ ( item.origin?.url || item.origin?.title ) && (
					<p className="blocklane-pro-forms-entry__meta">
						{ __( 'Submitted from', 'blocklane' ) }{ ' ' }
						{ item.origin?.url ? (
							<ExternalLink href={ item.origin.url }>
								{ item.origin.title || item.origin.url }
							</ExternalLink>
						) : (
							item.origin.title
						) }
						{ item.origin?.url && item.origin?.title && (
							<>
								<br />
								{ item.origin.url }
							</>
						) }
						{ item.origin?.referrer && (
							<>
								<br />
								{ sprintf(
									/* translators: %s: the referrer URL. */
									__( 'Referrer: %s', 'blocklane' ),
									item.origin.referrer
								) }
							</>
						) }
					</p>
				) }
				{ item.fields
					.filter(
						( field ) => 'hidden' !== field.type || field.value
					)
					.map( ( field ) => (
						<div
							key={ field.name }
							className="blocklane-pro-forms-entry__field"
						>
							<strong>{ field.label }</strong>
							{ field.files?.length ? (
								<ul className="blocklane-pro-forms-entry__files">
									{ field.files.map( ( file ) => {
										fileIndex++;
										const index = fileIndex;
										return (
											<li key={ index }>
												<Button
													variant="link"
													onClick={ () =>
														onDownload(
															item,
															index,
															file.name
														)
													}
												>
													{ file.name }
												</Button>{ ' ' }
												<span className="blocklane-pro-forms-entry__filesize">
													{ formatSize( file.size ) }
												</span>
											</li>
										);
									} ) }
								</ul>
							) : (
								<p className="blocklane-pro-forms-entry__value">
									{ fieldDisplayValue( field ) || '—' }
								</p>
							) }
						</div>
					) ) }
			</VStack>
			<HStack justify="flex-end" spacing={ 2 }>
				<Button
					variant="tertiary"
					isDestructive
					onClick={ () => onDelete( item ) }
				>
					{ __( 'Delete', 'blocklane' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => onToggleStatus( item ) }
				>
					{ 'read' === item.status
						? __( 'Mark unread', 'blocklane' )
						: __( 'Mark read', 'blocklane' ) }
				</Button>
				<Button variant="primary" onClick={ onClose }>
					{ __( 'Close', 'blocklane' ) }
				</Button>
			</HStack>
		</Modal>
	);
}

function DeleteModal( { items, closeModal, onConfirm } ) {
	return (
		<VStack spacing={ 4 }>
			<p style={ { margin: 0 } }>
				{ sprintf(
					/* translators: %d: number of submissions. */
					_n(
						'Delete %d submission? This cannot be undone.',
						'Delete %d submissions? This cannot be undone.',
						items.length,
						'blocklane'
					),
					items.length
				) }
			</p>
			<HStack justify="flex-end" spacing={ 2 }>
				<Button variant="tertiary" onClick={ closeModal }>
					{ __( 'Cancel', 'blocklane' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={ () => {
						onConfirm( items );
						closeModal();
					} }
				>
					{ __( 'Delete', 'blocklane' ) }
				</Button>
			</HStack>
		</VStack>
	);
}

/* The stored → editable shape for the Form defaults section, shared by the
   initial state and the post-save re-sync (the server returns normalized
   values — clamped numbers, whitelisted enums — so the form re-syncs to what
   was actually stored). */
const defaultsState = ( stored = {} ) => ( {
	recipients: stored.recipients || '',
	subject: stored.subject || '',
	success_action: stored.success_action || 'message',
	redirect_url: stored.redirect_url || '',
	store_submissions: stored.store_submissions ?? true,
	notify_admin: stored.notify_admin ?? true,
	auto_responder: {
		enabled: stored.auto_responder?.enabled ?? false,
		subject: stored.auto_responder?.subject || '',
		message: stored.auto_responder?.message || '',
	},
	replace_on_success: stored.replace_on_success ?? false,
} );

/* The Settings tab — seo/SettingsTab shaped: the former settings modal's
   controls in bordered sections, one Save at the bottom, success/error
   notices local to the tab. Settings arrive from the shell's overview (the
   shell already fetched them for the banners — no second GET); onSave
   resolves with the normalized saved settings so the form re-syncs to what
   the server actually stored. */
function FormsSettingsTab( { settings, onSave, initialPanel } ) {
	const [ isBusy, setIsBusy ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );

	const [ purgeDays, setPurgeDays ] = useState( settings.purge_days );
	const [ emailFromName, setEmailFromName ] = useState(
		settings.email_from_name
	);
	const [ emailFromAddress, setEmailFromAddress ] = useState(
		settings.email_from_address
	);
	const [ deleteOnUninstall, setDeleteOnUninstall ] = useState(
		settings.delete_on_uninstall
	);
	const [ maxUploadMb, setMaxUploadMb ] = useState( settings.max_upload_mb );
	const [ turnstileEnabled, setTurnstileEnabled ] = useState(
		settings.turnstile_enabled
	);
	const [ turnstileTestMode, setTurnstileTestMode ] = useState(
		settings.turnstile_test_mode
	);
	const [ turnstileSiteKey, setTurnstileSiteKey ] = useState(
		settings.turnstile_site_key
	);
	// The secret is write-only: the server never sends it back (the client
	// gets turnstile_secret_set), so the field starts empty and the payload
	// only carries the key once the author actually types — an untouched
	// field keeps the stored secret, a cleared one erases it.
	const [ turnstileSecretKey, setTurnstileSecretKey ] = useState( '' );
	const [ secretDirty, setSecretDirty ] = useState( false );
	const secretSet = !! settings.turnstile_secret_set;
	const [ turnstileTheme, setTurnstileTheme ] = useState(
		settings.turnstile_theme
	);
	const [ turnstileAppearance, setTurnstileAppearance ] = useState(
		settings.turnstile_appearance
	);
	const [ turnstileSize, setTurnstileSize ] = useState(
		settings.turnstile_size
	);

	/* Site-wide form defaults (v2): what every form inherits until its own
	   Submission panel overrides it — resolved live at submit time, so a
	   change here reaches already-published inheriting forms too. Stored
	   snake_case under settings.defaults. */
	const [ formDefaults, setFormDefaults ] = useState(
		defaultsState( settings.defaults )
	);
	const setDefault = ( key ) => ( value ) =>
		setFormDefaults( ( current ) => ( { ...current, [ key ]: value } ) );
	const setAutoDefault = ( key ) => ( value ) =>
		setFormDefaults( ( current ) => ( {
			...current,
			auto_responder: { ...current.auto_responder, [ key ]: value },
		} ) );

	const secretMissing = secretDirty
		? ! turnstileSecretKey.trim()
		: ! secretSet;
	// Danger-zone wipe: typed confirm arms the button; the server validates
	// the same token, and the batched route is re-called until drained.
	const [ wipeConfirm, setWipeConfirm ] = useState( '' );
	const [ wiping, setWiping ] = useState( false );
	const [ wipeNotice, setWipeNotice ] = useState( null );
	const wipeAll = async () => {
		setWiping( true );
		setWipeNotice( null );
		let total = 0;
		try {
			// The cap exists so a wedged server cannot spin here forever, but a
			// run that ENDS on the cap has not finished — reporting success
			// then tells the operator their submissions are gone while rows
			// remain, which is the one thing a delete-everything button must
			// never get wrong.
			let remaining = 0;
			for ( let round = 0; round < 40; round++ ) {
				const result = await formsApi.removeAll();
				const deleted = result?.deleted ?? 0;
				total += deleted;
				remaining = result?.remaining ?? 0;
				if ( ! result || ! result.remaining ) {
					break;
				}
				// A round that deleted nothing while rows remain means those
				// rows cannot be deleted — spinning 40 rounds over them only
				// delays telling the operator so.
				if ( ! deleted ) {
					break;
				}
			}
			if ( remaining ) {
				setWipeNotice( {
					status: 'warning',
					text: sprintf(
						/* translators: 1: deleted count, 2: remaining count. */
						__(
							'%1$d deleted, but %2$d could not be removed. Run it again to continue.',
							'blocklane'
						),
						total,
						remaining
					),
				} );
				return;
			}
			setWipeNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %d: number of deleted submissions. */
					_n(
						'%d submission deleted.',
						'%d submissions deleted.',
						total,
						'blocklane'
					),
					total
				),
			} );
			setWipeConfirm( '' );
		} catch ( err ) {
			setWipeNotice( { status: 'error', text: errorMessage( err ) } );
		} finally {
			setWiping( false );
		}
	};

	// Placeholder mirrors the field's actual state: forced test key, saved
	// write-only secret, or empty.
	let secretPlaceholder;
	if ( turnstileTestMode ) {
		secretPlaceholder = __( 'Cloudflare test key in use', 'blocklane' );
	} else if ( secretSet ) {
		secretPlaceholder = __(
			'Saved — enter a new key to replace it',
			'blocklane'
		);
	}

	// Test mode supplies Cloudflare's dummy keys itself, so missing stored
	// keys are only a problem once it's off.
	const turnstileKeysMissing =
		turnstileEnabled &&
		! turnstileTestMode &&
		( ! turnstileSiteKey.trim() || secretMissing );

	// A banner CTA can target a section ('turnstile'): bring it into view on
	// mount — one-shot, the SEO Overview→Settings targeting pattern. Settings
	// are already loaded when this tab renders (the shell gates on overview),
	// so mount is the moment.
	useEffect( () => {
		if ( ! initialPanel ) {
			return;
		}
		document
			.querySelector(
				`.blocklane-pro-forms-page__panel-${ initialPanel }`
			)
			?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}, [ initialPanel ] );

	const save = () => {
		setIsBusy( true );
		setSaveError( '' );
		setSaved( false );
		onSave( {
			purge_days: purgeDays,
			max_upload_mb: maxUploadMb,
			delete_on_uninstall: deleteOnUninstall,
			email_from_name: emailFromName.trim(),
			email_from_address: emailFromAddress.trim(),
			turnstile_enabled: turnstileEnabled,
			turnstile_test_mode: turnstileTestMode,
			// While test mode is on the key fields are disabled and forced
			// to the test pair for DISPLAY — the payload omits both so the
			// stored real keys ride through every save untouched.
			...( turnstileTestMode
				? {}
				: {
						turnstile_site_key: turnstileSiteKey.trim(),
						// Write-only: only a touched field travels (partial
						// saves keep the stored secret).
						...( secretDirty
							? {
									turnstile_secret_key:
										turnstileSecretKey.trim(),
								}
							: {} ),
					} ),
			turnstile_theme: turnstileTheme,
			turnstile_appearance: turnstileAppearance,
			turnstile_size: turnstileSize,
			defaults: formDefaults,
		} )
			.then( ( stored ) => {
				setPurgeDays( stored.purge_days );
				setMaxUploadMb( stored.max_upload_mb );
				setDeleteOnUninstall( stored.delete_on_uninstall );
				setEmailFromName( stored.email_from_name );
				setEmailFromAddress( stored.email_from_address );
				setTurnstileEnabled( stored.turnstile_enabled );
				setTurnstileTestMode( stored.turnstile_test_mode );
				setTurnstileSiteKey( stored.turnstile_site_key );
				setTurnstileTheme( stored.turnstile_theme );
				setTurnstileAppearance( stored.turnstile_appearance );
				setTurnstileSize( stored.turnstile_size );
				setFormDefaults( defaultsState( stored.defaults ) );
				// The just-typed secret is now the stored one — back to the
				// write-only resting state (the "Saved" placeholder).
				setTurnstileSecretKey( '' );
				setSecretDirty( false );
				setSaved( true );
			} )
			.catch( ( err ) => setSaveError( errorMessage( err ) ) )
			.finally( () => setIsBusy( false ) );
	};

	return (
		<div className="blocklane-pro-forms-page__sections">
			{ saveError ? (
				<Notice status="error" onRemove={ () => setSaveError( '' ) }>
					{ saveError }
				</Notice>
			) : null }
			{ saved ? (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Forms settings saved.', 'blocklane' ) }
				</Notice>
			) : null }

			<PanelBody
				title={ __( 'Form defaults', 'blocklane' ) }
				className="blocklane-pro-forms-page__panel-defaults"
			>
				<p className="blocklane-pro-forms-page__section-intro">
					{ __(
						'Every form starts from these values. A form’s own Submission settings override them; changes here apply immediately to forms that don’t.',
						'blocklane'
					) }
				</p>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'From name', 'blocklane' ) }
					help={ __(
						'The sender name on form emails. Empty uses the site title.',
						'blocklane'
					) }
					value={ emailFromName }
					onChange={ setEmailFromName }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="email"
					label={ __( 'From address', 'blocklane' ) }
					help={ __(
						'The sender address on form emails. Empty uses the WordPress default. Use an address at a domain this site is allowed to send for (SPF/DKIM), or providers will flag the mail.',
						'blocklane'
					) }
					value={ emailFromAddress }
					onChange={ setEmailFromAddress }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Recipients', 'blocklane' ) }
					help={ __(
						'Comma-separated email addresses. Empty sends to the site admin email.',
						'blocklane'
					) }
					value={ formDefaults.recipients }
					onChange={ setDefault( 'recipients' ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Email subject', 'blocklane' ) }
					help={ __(
						'Empty uses “New submission” with the form name and site title.',
						'blocklane'
					) }
					value={ formDefaults.subject }
					onChange={ setDefault( 'subject' ) }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'After submission', 'blocklane' ) }
					value={ formDefaults.success_action }
					options={ [
						{
							label: __( 'Show a message', 'blocklane' ),
							value: 'message',
						},
						{
							label: __( 'Redirect', 'blocklane' ),
							value: 'redirect',
						},
					] }
					onChange={ setDefault( 'success_action' ) }
				/>
				{ 'redirect' === formDefaults.success_action && (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="url"
						label={ __( 'Redirect URL', 'blocklane' ) }
						value={ formDefaults.redirect_url }
						onChange={ setDefault( 'redirect_url' ) }
					/>
				) }
				{ 'message' === formDefaults.success_action && (
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Replace form with message', 'blocklane' ) }
						help={ __(
							'After a successful submission, hide the form and leave only the success message.',
							'blocklane'
						) }
						checked={ formDefaults.replace_on_success }
						onChange={ setDefault( 'replace_on_success' ) }
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Store submissions', 'blocklane' ) }
					help={ __(
						'Keep a copy in the submissions inbox. The inbox is the safety net when email fails.',
						'blocklane'
					) }
					checked={ formDefaults.store_submissions }
					onChange={ setDefault( 'store_submissions' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Email notification', 'blocklane' ) }
					help={ __(
						'Email each submission to the recipients.',
						'blocklane'
					) }
					checked={ formDefaults.notify_admin }
					onChange={ setDefault( 'notify_admin' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Auto-responder', 'blocklane' ) }
					help={ __(
						'Send a confirmation email to the submitter, using the field marked as reply-to.',
						'blocklane'
					) }
					checked={ formDefaults.auto_responder.enabled }
					onChange={ setAutoDefault( 'enabled' ) }
				/>
				{ formDefaults.auto_responder.enabled && (
					<>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Auto-responder subject',
								'blocklane'
							) }
							value={ formDefaults.auto_responder.subject }
							onChange={ setAutoDefault( 'subject' ) }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __(
								'Auto-responder message',
								'blocklane'
							) }
							help={ __(
								'A summary of the submitted values is appended.',
								'blocklane'
							) }
							value={ formDefaults.auto_responder.message }
							onChange={ setAutoDefault( 'message' ) }
						/>
					</>
				) }
			</PanelBody>
			<PanelBody
				title={ __( 'Spam protection', 'blocklane' ) }
				className="blocklane-pro-forms-page__panel-turnstile"
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Cloudflare Turnstile', 'blocklane' ) }
					help={ __(
						'A privacy-friendly human check on every form, on top of the built-in silent protections. Free — create a widget at Cloudflare to get keys.',
						'blocklane'
					) }
					checked={ turnstileEnabled }
					onChange={ setTurnstileEnabled }
				/>
				{ turnstileEnabled && (
					<>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Test mode', 'blocklane' ) }
							help={ __(
								'Runs Turnstile on Cloudflare’s test keys: the widget renders and every check passes, no keys needed. For building and previewing — turn it off and enter real keys before the site takes real traffic.',
								'blocklane'
							) }
							checked={ turnstileTestMode }
							onChange={ setTurnstileTestMode }
						/>
						{ turnstileTestMode && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Test mode is on: every visitor passes the human check. Your saved keys are kept and come back when it’s off.',
									'blocklane'
								) }
							</Notice>
						) }
						{ /* While test mode is on the fields are disabled and
						     display what is actually in effect — Cloudflare's
						     forced test pair — instead of the stored keys,
						     which are preserved untouched underneath (the
						     save payload omits them entirely). */ }
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Site key', 'blocklane' ) }
							value={
								turnstileTestMode
									? TURNSTILE_TEST_SITE_KEY
									: turnstileSiteKey
							}
							onChange={ setTurnstileSiteKey }
							disabled={ turnstileTestMode }
							autoComplete="off"
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="password"
							label={ __( 'Secret key', 'blocklane' ) }
							placeholder={ secretPlaceholder }
							value={
								turnstileTestMode ? '' : turnstileSecretKey
							}
							onChange={ ( value ) => {
								setTurnstileSecretKey( value );
								setSecretDirty( true );
							} }
							disabled={ turnstileTestMode }
							autoComplete="new-password"
						/>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Widget theme', 'blocklane' ) }
							help={ __(
								'Auto follows each visitor’s system dark-mode preference. Pick Light or Dark to match a fixed site design.',
								'blocklane'
							) }
							value={ turnstileTheme }
							options={ [
								{
									label: __( 'Auto', 'blocklane' ),
									value: 'auto',
								},
								{
									label: __( 'Light', 'blocklane' ),
									value: 'light',
								},
								{
									label: __( 'Dark', 'blocklane' ),
									value: 'dark',
								},
							] }
							onChange={ setTurnstileTheme }
						/>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Widget visibility', 'blocklane' ) }
							help={ __(
								'“Only when needed” keeps the widget hidden unless Cloudflare asks the visitor to interact. The widget style itself (Managed, Non-Interactive, or Invisible) is set on the widget in your Cloudflare dashboard.',
								'blocklane'
							) }
							value={ turnstileAppearance }
							options={ [
								{
									label: __( 'Always show', 'blocklane' ),
									value: 'always',
								},
								{
									label: __(
										'Only when needed',
										'blocklane'
									),
									value: 'interaction-only',
								},
							] }
							onChange={ setTurnstileAppearance }
						/>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Widget size', 'blocklane' ) }
							value={ turnstileSize }
							options={ [
								{
									label: __( 'Normal (300px)', 'blocklane' ),
									value: 'normal',
								},
								{
									label: __(
										'Flexible (full width)',
										'blocklane'
									),
									value: 'flexible',
								},
								{
									label: __( 'Compact', 'blocklane' ),
									value: 'compact',
								},
							] }
							onChange={ setTurnstileSize }
						/>
						{ turnstileKeysMissing && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Both keys are required. Until they are set, forms keep working without Turnstile.',
									'blocklane'
								) }
							</Notice>
						) }
					</>
				) }
			</PanelBody>
			<PanelBody
				title={ __( 'Storage', 'blocklane' ) }
				className="blocklane-pro-forms-page__panel-storage"
			>
				<NumberControl
					__next40pxDefaultSize
					label={ __(
						'Auto-delete submissions after (days)',
						'blocklane'
					) }
					help={ __(
						'0 keeps submissions forever. Deletion runs daily and cannot be undone.',
						'blocklane'
					) }
					min={ 0 }
					max={ 3650 }
					value={ purgeDays }
					onChange={ ( value ) =>
						setPurgeDays( value ? parseInt( value, 10 ) : 0 )
					}
				/>
				{ /* The site-wide upload cap governs the File Upload field, a
				     block of block:form-file. An edition without that unit has
				     nothing for the cap to govern, so the control is not shown
				     — a live control that does nothing is the shape the charter
				     rules out. PRESENCE decides, not the catalog: the catalog
				     has an entry only for a LABELED absent unit, so blanking the
				     label would have flipped this control live over nothing
				     (#1043); hasUnit() reads the payload's unit list and fails
				     closed. The stored value still rides through a save
				     untouched (a writer preserves what it does not offer). */ }
				{ hasUnit( 'block:form-file' ) && (
					<NumberControl
						__next40pxDefaultSize
						label={ __( 'Maximum upload size (MB)', 'blocklane' ) }
						help={ __(
							'Caps every File Upload field site-wide. 0 lets each field use its own limit, up to the server maximum.',
							'blocklane'
						) }
						min={ 0 }
						max={ 1024 }
						value={ maxUploadMb }
						onChange={ ( value ) =>
							setMaxUploadMb( value ? parseInt( value, 10 ) : 0 )
						}
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Delete data on uninstall', 'blocklane' ) }
					help={ __(
						'Off keeps submissions and uploaded files if the plugin is ever uninstalled — they belong to the site. Turn on only if this site must leave nothing behind.',
						'blocklane'
					) }
					checked={ deleteOnUninstall }
					onChange={ setDeleteOnUninstall }
				/>
				{ wipeNotice ? (
					<Notice
						status={ wipeNotice.status }
						isDismissible
						onRemove={ () => setWipeNotice( null ) }
					>
						{ wipeNotice.text }
					</Notice>
				) : null }
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Delete all submissions', 'blocklane' ) }
					help={ __(
						'Removes every submission and its uploaded files, permanently. Type DELETE to arm the button.',
						'blocklane'
					) }
					placeholder="DELETE"
					autoComplete="off"
					value={ wipeConfirm }
					onChange={ setWipeConfirm }
				/>
				<Button
					variant="secondary"
					isDestructive
					__next40pxDefaultSize
					disabled={ 'DELETE' !== wipeConfirm || wiping }
					isBusy={ wiping }
					onClick={ wipeAll }
				>
					{ wiping
						? __( 'Deleting…', 'blocklane' )
						: __( 'Delete all submissions', 'blocklane' ) }
				</Button>
			</PanelBody>
			<HStack
				justify="flex-end"
				className="blocklane-pro-forms-page__save-row"
			>
				<Button
					variant="primary"
					__next40pxDefaultSize
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ save }
				>
					{ __( 'Save Forms Settings', 'blocklane' ) }
				</Button>
			</HStack>
		</div>
	);
}

export function FormsScreen( { initialTab } ) {
	const [ overview, setOverview ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ items, setItems ] = useState( [] );
	const [ totalItems, setTotalItems ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ selection, setSelection ] = useState( [] );
	const [ openItem, setOpenItem ] = useState( null );
	const [ overviewReload, setOverviewReload ] = useState( 0 );
	const pageStart = useRef( null );

	// The shared tab shell (components/use-tab-shell.js): anchor tabs, URL
	// sync, Space-key restore, and one-shot Settings-section targeting for
	// the banner CTAs ('turnstile').
	const shell = useTabShell( {
		screen: 'forms',
		param: 'formstab',
		tabs: TABS,
		defaultTab: 'submissions',
		initialTab,
	} );
	const { tab, targetPanel: settingsPanel, switchTab } = shell;

	const formFilter =
		view.filters?.find( ( f ) => 'form' === f.field )?.value || '';
	const originFilter =
		view.filters?.find( ( f ) => 'origin' === f.field )?.value || '';
	const statusFilter =
		view.filters?.find( ( f ) => 'status' === f.field )?.value || '';

	// CSV export honors the ACTIVE filters — what you see is what you get.
	// apiFetch (parse:false) carries the nonce a bare <a href> would lack;
	// the blob round-trip is the downloadFile pattern.
	const [ exporting, setExporting ] = useState( false );
	const exportCsv = () => {
		setExporting( true );
		formsApi
			.exportCsv( {
				form_id: formFilter,
				origin_id: originFilter,
				status: statusFilter,
				search: view.search || '',
			} )
			.then( ( response ) => response.blob() )
			.then( ( blob ) => {
				const url = URL.createObjectURL( blob );
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = `form-submissions-${ new Date()
					.toISOString()
					.slice( 0, 10 ) }.csv`;
				link.click();
				URL.revokeObjectURL( url );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setExporting( false ) );
	};

	useEffect( () => {
		let stale = false;
		formsApi
			.overview()
			.then( ( data ) => {
				if ( ! stale ) {
					setOverview( data );
					setError( '' );
				}
			} )
			.catch( ( err ) => ! stale && setError( errorMessage( err ) ) );
		return () => {
			stale = true;
		};
	}, [ overviewReload ] );

	useEffect( () => {
		// Each tab pays only for its own data (the ported SEO idiom): a
		// deep-load of the Settings tab must not run the list query for a
		// table it never renders. Switching back refetches — `tab` is a dep.
		if ( 'submissions' !== tab ) {
			return undefined;
		}
		let stale = false;
		setLoading( true );
		formsApi
			.submissions( {
				form_id: formFilter,
				origin_id: originFilter,
				status: statusFilter,
				search: view.search || '',
				page: view.page,
				per_page: view.perPage,
			} )
			.then( ( data ) => {
				if ( stale ) {
					return;
				}
				setItems( data.items || [] );
				setTotalItems( data.total || 0 );
				setTotalPages( data.total_pages || 0 );
				setSelection( [] );
				setError( '' ); // clear any stale banner once fresh data lands
			} )
			.catch( ( err ) => ! stale && setError( errorMessage( err ) ) )
			.finally( () => ! stale && setLoading( false ) );
		return () => {
			stale = true;
		};
	}, [
		tab,
		formFilter,
		originFilter,
		statusFilter,
		view.search,
		view.page,
		view.perPage,
	] );

	useEffect( () => {
		pageStart.current?.focus();
	}, [] );

	const patchItem = useCallback( ( updated ) => {
		setItems( ( current ) =>
			current.map( ( item ) =>
				item.id === updated.id ? updated : item
			)
		);
		setOpenItem( ( current ) =>
			current && current.id === updated.id ? updated : current
		);
	}, [] );

	// Bulk ops run their per-row requests concurrently and patch the list
	// locally, then refresh only the overview counts — no full list refetch
	// (the rows already reflect the change).
	const setStatuses = useCallback(
		async ( selected, status ) => {
			const results = await Promise.all(
				selected.map( ( item ) =>
					formsApi.setStatus( item.id, status ).catch( ( err ) => {
						setError( errorMessage( err ) );
						return null;
					} )
				)
			);
			results.forEach( ( result ) => result && patchItem( result.item ) );
			setSelection( [] );
			setOverviewReload( ( n ) => n + 1 );
		},
		[ patchItem ]
	);

	const deleteItems = useCallback( async ( selected ) => {
		const ids = new Set(
			(
				await Promise.all(
					selected.map( ( item ) =>
						formsApi
							.remove( item.id )
							.then( () => item.id )
							.catch( ( err ) => {
								setError( errorMessage( err ) );
								return null;
							} )
					)
				)
			).filter( ( id ) => null !== id )
		);
		setItems( ( current ) =>
			current.filter( ( item ) => ! ids.has( item.id ) )
		);
		setTotalItems( ( total ) => Math.max( 0, total - ids.size ) );
		setOpenItem( ( current ) =>
			current && ids.has( current.id ) ? null : current
		);
		setSelection( [] );
		setOverviewReload( ( n ) => n + 1 );
	}, [] );

	const openEntry = useCallback(
		( item ) => {
			setOpenItem( item );
			if ( 'unread' === item.status ) {
				formsApi
					.setStatus( item.id, 'read' )
					.then( ( { item: updated } ) => patchItem( updated ) )
					.catch( () => {} );
			}
		},
		[ patchItem ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'form',
				label: __( 'Form', 'blocklane' ),
				enableSorting: false,
				getValue: ( { item } ) => item.form_id,
				elements: ( overview?.forms || [] ).map( ( form ) => ( {
					value: form.form_id,
					label: form.form_name,
				} ) ),
				filterBy: { operators: [ 'is' ], isPrimary: true },
				render: ( { item } ) => (
					<strong
						className={
							'unread' === item.status
								? 'blocklane-pro-forms-screen__row is-unread'
								: 'blocklane-pro-forms-screen__row'
						}
					>
						{ item.form_name }
					</strong>
				),
			},
			{
				id: 'excerpt',
				label: __( 'Submission', 'blocklane' ),
				enableSorting: false,
				enableGlobalSearch: true,
				getValue: ( { item } ) => item.excerpt,
				render: ( { item } ) => (
					<span className="blocklane-pro-forms-screen__excerpt">
						{ item.excerpt || '—' }
					</span>
				),
			},
			{
				id: 'origin',
				label: __( 'Submitted from', 'blocklane' ),
				enableSorting: false,
				getValue: ( { item } ) => String( item.origin_id || '' ),
				elements: ( overview?.origins || [] ).map( ( origin ) => ( {
					value: String( origin.origin_id ),
					label: origin.title,
				} ) ),
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) => {
					const label = originLabel( item );
					if ( ! label ) {
						return '—';
					}
					return item.origin?.url ? (
						<ExternalLink href={ item.origin.url }>
							{ label }
						</ExternalLink>
					) : (
						label
					);
				},
			},
			{
				id: 'status',
				label: __( 'Status', 'blocklane' ),
				enableSorting: false,
				getValue: ( { item } ) => item.status,
				elements: [
					{ value: 'unread', label: __( 'Unread', 'blocklane' ) },
					{ value: 'read', label: __( 'Read', 'blocklane' ) },
				],
				filterBy: { operators: [ 'is' ], isPrimary: true },
				render: ( { item } ) =>
					'unread' === item.status
						? __( 'Unread', 'blocklane' )
						: __( 'Read', 'blocklane' ),
			},
			{
				id: 'date',
				label: __( 'Date', 'blocklane' ),
				enableSorting: false,
				getValue: ( { item } ) => item.created,
				render: ( { item } ) => item.date,
			},
		],
		[ overview ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view',
				label: __( 'View', 'blocklane' ),
				icon: seen,
				isPrimary: true,
				callback: ( [ item ] ) => openEntry( item ),
			},
			{
				id: 'mark-read',
				label: __( 'Mark read', 'blocklane' ),
				icon: seen,
				supportsBulk: true,
				isEligible: ( item ) => 'unread' === item.status,
				callback: ( selected ) => setStatuses( selected, 'read' ),
			},
			{
				id: 'mark-unread',
				label: __( 'Mark unread', 'blocklane' ),
				icon: unseen,
				supportsBulk: true,
				isEligible: ( item ) => 'read' === item.status,
				callback: ( selected ) => setStatuses( selected, 'unread' ),
			},
			{
				id: 'delete',
				label: __( 'Delete', 'blocklane' ),
				icon: trash,
				supportsBulk: true,
				isDestructive: true,
				modalHeader: __( 'Delete submissions', 'blocklane' ),
				RenderModal: ( { items: selected, closeModal } ) => (
					<DeleteModal
						items={ selected }
						closeModal={ closeModal }
						onConfirm={ deleteItems }
					/>
				),
			},
		],
		[ openEntry, setStatuses, deleteItems ]
	);

	if ( ! isToolScreenOn( 'forms' ) ) {
		return (
			<ScreenDisabledNotice
				title={ __( 'Forms', 'blocklane' ) }
				body={ __(
					'Turning it off unregisters the form blocks and this inbox; stored submissions stay in the database, untouched.',
					'blocklane'
				) }
				slug="forms"
			/>
		);
	}

	const settings = overview?.settings;
	// The first-failure flags, one per channel: 'admin' (the owner's lead
	// notification — the critical one) and 'auto-responder' (the visitor's
	// confirmation copy). Not dismissible — each clears on that channel's
	// next successful send, or when the owner turns the channel off in the
	// site defaults. While either is up the softer SMTP hint stays hidden
	// (this banner already carries the same advice, with proof).
	const mailBroken = overview?.mail?.broken || null;
	const adminMailBroken = mailBroken?.admin;
	const responderMailBroken = mailBroken?.[ 'auto-responder' ];
	// "Nothing is lost" is only true while storage is on — with the site
	// default off, a broken admin channel means submissions may not be
	// kept anywhere, and the banner must say THAT instead.
	const storageDefaultOff = false === settings?.defaults?.store_submissions;
	let mailBannerText = '';
	if ( adminMailBroken || responderMailBroken ) {
		const parts = [];
		if ( adminMailBroken ) {
			parts.push(
				adminMailBroken.message
					? sprintf(
							/* translators: %s: the mailer's error message. */
							__(
								'Form emails are failing to send (“%s”).',
								'blocklane'
							),
							adminMailBroken.message
						)
					: __( 'Form emails are failing to send.', 'blocklane' )
			);
			parts.push(
				storageDefaultOff
					? __(
							'Store submissions is off in the site defaults, so new submissions may not be kept anywhere — turn storage back on or fix the mailer.',
							'blocklane'
						)
					: __(
							'Nothing is lost — every submission is stored in this inbox.',
							'blocklane'
						)
			);
		} else {
			parts.push(
				responderMailBroken.message
					? sprintf(
							/* translators: %s: the mailer's error message. */
							__(
								'The auto-responder is failing to send (“%s”) — visitors are not getting their confirmation emails.',
								'blocklane'
							),
							responderMailBroken.message
						)
					: __(
							'The auto-responder is failing to send — visitors are not getting their confirmation emails.',
							'blocklane'
						)
			);
		}
		parts.push(
			__(
				'An SMTP plugin usually fixes delivery; this clears automatically once an email sends.',
				'blocklane'
			)
		);
		mailBannerText = parts.join( ' ' );
	}
	const showSmtpHint =
		overview &&
		! mailBroken &&
		! overview.smtp.configured &&
		! overview.settings.smtp_hint_dismissed;
	// Test mode running: the loudest state — the site is accepting every
	// visitor on purpose, which must never be forgettable.
	const turnstileTestModeOn =
		settings?.turnstile_enabled && settings?.turnstile_test_mode;
	// Cloudflare's dummy keys stored as if they were real ones (test mode
	// OFF): the widget "works" but accepts everyone — the exact footgun test
	// mode exists to replace.
	const turnstileTestKeys =
		settings?.turnstile_enabled &&
		! settings?.turnstile_test_mode &&
		!! overview?.turnstile?.test_keys;
	const turnstileMisconfigured =
		settings?.turnstile_enabled &&
		! settings?.turnstile_test_mode &&
		( ! settings.turnstile_site_key || ! settings.turnstile_secret_set );
	// The first-failure/probe flag — only meaningful while Turnstile is on
	// and configured (the missing-key banner covers the other case; in test
	// mode verification cannot meaningfully fail, so the flag is stale noise).
	const turnstileBroken =
		( ! turnstileMisconfigured &&
			! turnstileTestModeOn &&
			settings?.turnstile_enabled &&
			overview?.turnstile?.broken ) ||
		null;

	const downloadFile = ( item, index, filename ) => {
		formsApi
			.downloadFile( item.id, index )
			.then( ( response ) => response.blob() )
			.then( ( blob ) => {
				const url = URL.createObjectURL( blob );
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = filename || 'file';
				link.click();
				URL.revokeObjectURL( url );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) );
	};

	// Resolves with the normalized saved settings — the Settings tab
	// re-syncs its form from them; callers own their error handling.
	const saveSettings = ( payload ) =>
		formsApi
			.saveSettings( payload )
			.then( ( { settings: saved, turnstile, mail } ) => {
				setOverview( ( current ) =>
					current
						? {
								...current,
								settings: saved,
								// The save route re-probes a typed secret
								// and clears mail flags for channels the
								// save turned off; carry both so the
								// banners appear/clear without a reload.
								...( turnstile ? { turnstile } : {} ),
								...( mail ? { mail } : {} ),
							}
						: current
				);
				return saved;
			} );

	return (
		<div className="blocklane-pro-page">
			<ScreenHeader screen="forms" pageStart={ pageStart } />
			<ScreenTabs
				screen="forms"
				label={ __( 'Forms sections', 'blocklane' ) }
				tabs={ TABS }
				shell={ shell }
			/>
			<ScreenTabPanel
				screen="forms"
				slug={ tab }
				className="blocklane-pro-forms-page__content"
			>
				{ turnstileTestModeOn && (
					<Notice
						status="warning"
						className="blocklane-pro-forms-page__notice"
						isDismissible={ false }
						actions={ [
							{
								label: __( 'Open settings', 'blocklane' ),
								onClick: () =>
									switchTab( 'settings', 'turnstile' ),
							},
						] }
					>
						{ __(
							'Turnstile test mode is on — the test widget shows and every visitor passes the human check. Turn it off and enter real keys before this site takes real traffic.',
							'blocklane'
						) }
					</Notice>
				) }
				{ turnstileTestKeys && (
					<Notice
						status="warning"
						className="blocklane-pro-forms-page__notice"
						isDismissible={ false }
						actions={ [
							{
								label: __( 'Open settings', 'blocklane' ),
								onClick: () =>
									switchTab( 'settings', 'turnstile' ),
							},
						] }
					>
						{ __(
							'Turnstile is configured with Cloudflare’s test keys, which accept every visitor — the site has no real spam protection. Enter real keys, or turn on Test mode while building.',
							'blocklane'
						) }
					</Notice>
				) }
				{ turnstileMisconfigured && (
					<Notice
						status="warning"
						className="blocklane-pro-forms-page__notice"
						isDismissible={ false }
						actions={ [
							{
								label: __( 'Open settings', 'blocklane' ),
								onClick: () =>
									switchTab( 'settings', 'turnstile' ),
							},
						] }
					>
						{ settings.turnstile_secret_orphaned
							? __(
									'Turnstile is enabled but the stored secret key can no longer be read — the site’s security keys changed, so it must be entered again. Forms are submitting without it.',
									'blocklane'
								)
							: __(
									'Turnstile is enabled but a key is missing — forms are submitting without it.',
									'blocklane'
								) }
					</Notice>
				) }
				{ turnstileBroken && (
					<Notice
						status="warning"
						className="blocklane-pro-forms-page__notice"
						isDismissible={ false }
						actions={ [
							{
								label: __( 'Open settings', 'blocklane' ),
								onClick: () =>
									switchTab( 'settings', 'turnstile' ),
							},
						] }
					>
						{ 'unreachable' === turnstileBroken.reason
							? __(
									'Cloudflare could not be reached for Turnstile verification — forms are accepting submissions without the human check. This clears automatically once verification succeeds.',
									'blocklane'
								)
							: __(
									'Cloudflare rejected the Turnstile secret key — verification is failing and forms are accepting submissions without the human check. Re-enter the secret key.',
									'blocklane'
								) }
					</Notice>
				) }
				{ !! mailBannerText && (
					<Notice
						status={
							adminMailBroken && storageDefaultOff
								? 'error'
								: 'warning'
						}
						className="blocklane-pro-forms-page__notice"
						isDismissible={ false }
					>
						{ mailBannerText }
					</Notice>
				) }
				{ showSmtpHint && (
					<Notice
						status="warning"
						className="blocklane-pro-forms-page__notice"
						onRemove={ () =>
							saveSettings( {
								smtp_hint_dismissed: true,
							} ).catch( ( err ) =>
								setError( errorMessage( err ) )
							)
						}
					>
						{ __(
							'No mailer customization detected. Form notifications use PHP mail, which many hosts deliver poorly — an SMTP plugin makes delivery reliable. Submissions are always stored here either way.',
							'blocklane'
						) }
					</Notice>
				) }
				{ error && (
					<Notice
						status="error"
						className="blocklane-pro-forms-page__notice"
						onRemove={ () => setError( '' ) }
					>
						{ error }
					</Notice>
				) }
				{ ! overview && ! error ? (
					<div className="blocklane-pro-forms-page__loading">
						<Spinner />
					</div>
				) : (
					<>
						{ 'submissions' === tab && (
							<DataViews
								data={ items }
								fields={ fields }
								actions={ actions }
								view={ view }
								onChangeView={ setView }
								isLoading={ loading }
								defaultLayouts={ defaultLayouts }
								paginationInfo={ { totalItems, totalPages } }
								getItemId={ ( item ) => String( item.id ) }
								isItemClickable={ () => true }
								onClickItem={ openEntry }
								selection={ selection }
								onChangeSelection={ setSelection }
								header={
									<Button
										variant="secondary"
										size="compact"
										disabled={ exporting || ! totalItems }
										isBusy={ exporting }
										onClick={ exportCsv }
									>
										{ exporting
											? __( 'Exporting…', 'blocklane' )
											: __( 'Export CSV', 'blocklane' ) }
									</Button>
								}
							/>
						) }
						{ 'settings' === tab && settings && (
							<FormsSettingsTab
								settings={ settings }
								onSave={ saveSettings }
								initialPanel={ settingsPanel }
							/>
						) }
					</>
				) }
			</ScreenTabPanel>
			{ openItem && (
				<EntryModal
					item={ openItem }
					onClose={ () => setOpenItem( null ) }
					onToggleStatus={ ( item ) =>
						setStatuses(
							[ item ],
							'read' === item.status ? 'unread' : 'read'
						)
					}
					onDelete={ ( item ) => deleteItems( [ item ] ) }
					onDownload={ downloadFile }
				/>
			) }
		</div>
	);
}
