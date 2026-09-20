/**
 * Scripts screen — custom header / body / footer code injection.
 *
 * Renders in the dashboard's two-column shell (same as Child Theme): a master
 * enable toggle + three code editors on the left, a "what is this / trust /
 * persistence" panel on the right. Wired to GET/POST blocklane-pro/v1/scripts,
 * which reads/writes the blocklane_pro_scripts option — the source of truth.
 * The plugin prints the code on each page load, so it runs while the plugin
 * is active and stops when it is deactivated.
 *
 * The code editors use WordPress's bundled CodeMirror (wp.codeEditor) when
 * available, falling back to a plain textarea otherwise.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
	Flex,
	FlexItem,
	Button,
	Notice,
	ToggleControl,
	Spinner,
	__experimentalDivider as Divider,
} from '@wordpress/components';

import { scripts as scriptsApi } from '../api/client';
import { HelpTab, useHelpPreference } from '../components/HelpTab';
import {
	ScreenDisabledNotice,
	isToolScreenOn,
} from '../components/ScreenDisabledNotice';
import { errorMessage } from '../api/errors';
import { pluginName } from '../edition';

const CodeField = ( {
	id,
	label,
	help,
	value,
	onChange,
	enabled,
	onToggle,
	masterOn,
} ) => {
	const taRef = useRef( null );
	const cmRef = useRef( null );

	// A section is effectively off when the master is off OR its own switch is
	// off. While the master is off, its switch is moot, so disable it.
	const dimmed = ! masterOn || ! enabled;

	useEffect( () => {
		const textarea = taRef.current;
		if ( ! textarea ) {
			return undefined;
		}

		const editor = window.wp?.codeEditor?.initialize?.( textarea, {
			codemirror: {
				mode: 'htmlmixed',
				lineNumbers: true,
				lineWrapping: true,
				autoCloseTags: true,
				autoCloseBrackets: true,
				styleActiveLine: true,
			},
		} );

		if ( editor?.codemirror ) {
			cmRef.current = editor.codemirror;
			cmRef.current.setValue( value || '' );
			cmRef.current.on( 'change', ( instance ) =>
				onChange( instance.getValue() )
			);
		}

		return () => {
			if ( cmRef.current ) {
				cmRef.current.toTextArea();
				cmRef.current = null;
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<div
			className={ `blocklane-pro-scripts__field${
				dimmed ? ' is-off' : ''
			}` }
		>
			<div className="blocklane-pro-scripts__field-head">
				<label htmlFor={ id }>{ label }</label>
				<ToggleControl
					__nextHasNoMarginBottom
					className="blocklane-pro-scripts__field-toggle"
					label={ __( 'Enabled', 'blocklane' ) }
					checked={ enabled }
					onChange={ onToggle }
					disabled={ ! masterOn }
				/>
			</div>
			<textarea
				id={ id }
				ref={ taRef }
				className="blocklane-pro-scripts__textarea"
				defaultValue={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
				spellCheck={ false }
				rows={ 6 }
			/>
			{ help ? (
				<p className="blocklane-pro-child-theme__help">{ help }</p>
			) : null }
		</div>
	);
};

export const ScriptsScreen = () => {
	const [ loading, setLoading ] = useState( true );
	const [ helpOpen, setHelpOpen ] = useHelpPreference( 'scripts' );
	const [ enabled, setEnabled ] = useState( true );
	const [ header, setHeader ] = useState( '' );
	const [ body, setBody ] = useState( '' );
	const [ footer, setFooter ] = useState( '' );
	const [ headerOn, setHeaderOn ] = useState( true );
	const [ bodyOn, setBodyOn ] = useState( true );
	const [ footerOn, setFooterOn ] = useState( true );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );
	const [ loadFailed, setLoadFailed ] = useState( false );

	const pageStart = useRef( null );

	useEffect( () => {
		scriptsApi
			.get()
			.then( ( s ) => {
				setEnabled( s?.enabled !== false );
				setHeader( s?.header?.code || '' );
				setBody( s?.body?.code || '' );
				setFooter( s?.footer?.code || '' );
				setHeaderOn( s?.header?.enabled !== false );
				setBodyOn( s?.body?.enabled !== false );
				setFooterOn( s?.footer?.enabled !== false );
			} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				// The GET failed, so the fields never loaded. Block Save so it can't
				// overwrite the stored code with these empty (never-loaded) values.
				setLoadFailed( true );
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		if ( ! loading ) {
			pageStart.current?.focus();
		}
	}, [ loading ] );

	const save = () => {
		setIsBusy( true );
		setError( '' );
		setSaved( false );
		scriptsApi
			.save( {
				enabled,
				header: { enabled: headerOn, code: header },
				body: { enabled: bodyOn, code: body },
				footer: { enabled: footerOn, code: footer },
			} )
			.then( ( s ) => {
				setEnabled( s?.enabled !== false );
				setHeaderOn( s?.header?.enabled !== false );
				setBodyOn( s?.body?.enabled !== false );
				setFooterOn( s?.footer?.enabled !== false );
				setSaved( true );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setIsBusy( false ) );
	};

	if ( ! isToolScreenOn( 'scripts' ) ) {
		return (
			<ScreenDisabledNotice
				title={ __( 'Scripts', 'blocklane' ) }
				body={ __(
					'Turning it off only hides this editor; your scripts keep printing on the front end.',
					'blocklane'
				) }
				slug="scripts"
			/>
		);
	}

	return (
		<Flex
			align="stretch"
			gap="0"
			className={
				helpOpen
					? 'blocklane-pro-extensions blocklane-pro-scripts'
					: 'blocklane-pro-extensions blocklane-pro-scripts is-help-closed'
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
							{ __( 'Scripts', 'blocklane' ) }
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
							{ __( 'Scripts saved.', 'blocklane' ) }
						</Notice>
					) : null }

					{ loading ? (
						<div className="blocklane-pro-loading">
							<Spinner />
						</div>
					) : (
						<div className="blocklane-pro-scripts__fields">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Enable all custom scripts',
									'blocklane'
								) }
								help={ __(
									'Master kill switch — when off, nothing is output regardless of the per-section switches below. Your code is kept.',
									'blocklane'
								) }
								checked={ enabled }
								onChange={ setEnabled }
							/>

							<CodeField
								id="blocklane-pro-scripts-header"
								label={ __( 'Header', 'blocklane' ) }
								value={ header }
								onChange={ setHeader }
								enabled={ headerOn }
								onToggle={ setHeaderOn }
								masterOn={ enabled }
								help={ __(
									'Output inside <head> (wp_head) — meta tags, site verification, analytics loaders.',
									'blocklane'
								) }
							/>
							<CodeField
								id="blocklane-pro-scripts-body"
								label={ __(
									'Body — after the opening <body> tag',
									'blocklane'
								) }
								value={ body }
								onChange={ setBody }
								enabled={ bodyOn }
								onToggle={ setBodyOn }
								masterOn={ enabled }
								help={ __(
									'Output immediately after <body> opens (wp_body_open) — e.g. a Tag Manager noscript.',
									'blocklane'
								) }
							/>
							<CodeField
								id="blocklane-pro-scripts-footer"
								label={ __( 'Footer', 'blocklane' ) }
								value={ footer }
								onChange={ setFooter }
								enabled={ footerOn }
								onToggle={ setFooterOn }
								masterOn={ enabled }
								help={ __(
									'Output before </body> (wp_footer) — deferred scripts, chat widgets.',
									'blocklane'
								) }
							/>

							<Flex
								justify="flex-end"
								className="blocklane-pro-child-theme__actions"
							>
								<Button
									variant="primary"
									__next40pxDefaultSize
									isBusy={ isBusy }
									disabled={ isBusy || loadFailed }
									onClick={ save }
								>
									{ __( 'Save Scripts', 'blocklane' ) }
								</Button>
							</Flex>
						</div>
					) }
				</section>
			</FlexItem>

			<HelpTab
				isOpen={ helpOpen }
				onToggle={ () => setHelpOpen( ! helpOpen ) }
				panelId="blocklane-pro-help-panel-scripts"
			/>
			<FlexItem
				className="blocklane-pro-extensions__preview"
				id="blocklane-pro-help-panel-scripts"
				aria-hidden={ ! helpOpen }
			>
				<section className="blocklane-pro-extensions__preview-content">
					<p>
						<strong>{ __( 'What is this?', 'blocklane' ) }</strong>
					</p>
					<p>
						{ __(
							'Insert code into three spots site-wide: the header, right after the body opens, and the footer. Handy for analytics (GA4, Tag Manager), ad/conversion pixels, and site-verification meta.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __(
								'Master + per-section switches',
								'blocklane'
							) }
						</strong>
					</p>
					<p>
						{ __(
							'Each section has its own switch, so you can disable just one (e.g. to isolate a footer script that’s misbehaving) without touching the others. The master switch turns everything off at once. A section only runs when both it and the master are on — and turning anything off keeps your code for later.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'Only add code you trust', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ __(
							'This runs on every front-end page exactly as written — there is no sanitizing. Pasting untrusted code can compromise your site.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'Where this code lives', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ sprintf(
							/* translators: %s: the plugin name. */
							__(
								'Your code is stored as a normal WordPress setting and printed by %s on each page load, so it runs while the plugin is active and stops when it is deactivated. To stop it sooner, clear the boxes here or switch the master toggle off.',
								'blocklane'
							),
							pluginName()
						) }
					</p>
					<p>
						{ __(
							'Deleting the plugin keeps your code: it is yours, not plugin state, so a reinstall picks it back up. Use Clean Uninstall in Advanced if you want it removed along with the plugin.',
							'blocklane'
						) }
					</p>
				</section>
			</FlexItem>
		</Flex>
	);
};
