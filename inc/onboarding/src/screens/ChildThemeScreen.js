/**
 * Create Child Theme screen.
 *
 * Collects the child theme's header details and creates + activates a child of
 * the active parent theme via POST blocklane-pro/v1/create-child-theme.
 * Renders in the dashboard's two-column shell (white form column + info panel),
 * with a confirm step and a success summary.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useRef, cloneElement } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import {
	Flex,
	FlexItem,
	TextControl,
	TextareaControl,
	Button,
	Notice,
	Modal,
	CheckboxControl,
	__experimentalDivider as Divider,
} from '@wordpress/components';

import { childTheme as childThemeApi } from '../api/client';
import { buildRouteUrl } from '../router';
import { HelpTab, useHelpPreference } from '../components/HelpTab';
import { errorMessage } from '../api/errors';

const Field = ( { label, required, help, children } ) => {
	// Associate the label with the control inside (TextControl and
	// TextareaControl forward `id` to their native input).
	const fieldId = useInstanceId( Field, 'blocklane-pro-child-theme-field' );
	return (
		<div className="blocklane-pro-child-theme__field">
			<label
				htmlFor={ fieldId }
				className={ required ? 'is-required' : '' }
			>
				{ label }
			</label>
			{ cloneElement( children, { id: fieldId } ) }
			{ help ? (
				<p className="blocklane-pro-child-theme__help">{ help }</p>
			) : null }
		</div>
	);
};

const SummaryRow = ( { label, value, last } ) => (
	<>
		<Flex gap={ 4 } className="blocklane-pro-child-theme__summary-row">
			<FlexItem>
				<strong>{ label }</strong>
			</FlexItem>
			<FlexItem>
				<span>{ value }</span>
			</FlexItem>
		</Flex>
		{ ! last ? <Divider /> : null }
	</>
);

export const ChildThemeScreen = () => {
	const settings = window.blocklaneProAdmin || {};
	// Server-resolved fallbacks (what generation writes when a field is left
	// blank) — shown as placeholders so the form previews the actual output.
	const defaults = settings.childTheme || {};
	// The bundled branded child screenshot — the same file generation copies
	// into every child (server-localized with a cache-buster).
	const screenshot =
		defaults.screenshot ||
		`${ settings.pluginUrl || '' }/inc/child-theme/screenshot.png`;

	// The Advanced screen's "Child Theme Generator" toggle. When off, the
	// server hides the submenu item and unregisters the tool's REST routes;
	// this screen stays deep-linkable and points at the toggle instead of
	// rendering a form that couldn't submit. wp_localize_script stringifies
	// the flag ("1" on / "" off); fail open when it's absent entirely.
	const toolEnabled = !! ( settings.childThemeTool ?? true );

	// Full-page link to the toggle's detail panel on the Advanced screen,
	// built from the current URL so the page slug isn't hardcoded here.
	const advancedUrl = buildRouteUrl( {
		screen: 'advanced',
		adv: 'child-theme-tool',
	} );

	const [ themeName, setThemeName ] = useState( '' );
	const [ helpOpen, setHelpOpen ] = useHelpPreference( 'child-theme' );
	const [ themeUrl, setThemeUrl ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ author, setAuthor ] = useState( '' );
	const [ authorUrl, setAuthorUrl ] = useState( '' );
	const [ version, setVersion ] = useState( '1.0.0' );
	const [ textDomain, setTextDomain ] = useState( '' );

	const [ childThemeCreated, setChildThemeCreated ] = useState( false );
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const [ customizations, setCustomizations ] = useState( null );
	const [ importCustomizations, setImportCustomizations ] = useState( true );
	const [ importResult, setImportResult ] = useState( null );
	// The style.css header values the server actually wrote — blank optional
	// fields resolve server-side (parent theme / site), so the summary reads
	// from these rather than re-deriving fallbacks here.
	const [ resultHeaders, setResultHeaders ] = useState( null );

	const pageStart = useRef( null );

	useEffect( () => {
		pageStart.current?.focus();
	}, [] );

	useEffect( () => {
		if ( ! toolEnabled ) {
			return; // Routes aren't registered while the tool is off.
		}
		childThemeApi
			.customizations()
			.then( setCustomizations )
			.catch( ( err ) => {
				// Swallowing this hid the import-customizations offer with no
				// trace. The generator itself still works, so surface it as a
				// dismissible warning rather than blocking the screen — and
				// prefer the server's own message over the generic copy: a
				// capability error is actionable, "could not check" is not.
				setError(
					err?.message ||
						__(
							'Could not check the parent theme for Site Editor customizations — the import option is unavailable.',
							'blocklane'
						)
				);
			} );
	}, [ toolEnabled ] );

	const hasCustomizations =
		!! customizations &&
		( customizations.hasGlobalStyles ||
			!! customizations.templates?.length ||
			!! customizations.parts?.length );

	const importSummary = () => {
		const bits = [];
		if ( customizations?.hasGlobalStyles ) {
			bits.push( __( 'your global styles', 'blocklane' ) );
		}
		if ( customizations?.templates?.length ) {
			bits.push(
				sprintf(
					/* translators: %d: number of edited templates */
					_n(
						'%d edited template',
						'%d edited templates',
						customizations.templates.length,
						'blocklane'
					),
					customizations.templates.length
				)
			);
		}
		if ( customizations?.parts?.length ) {
			bits.push(
				sprintf(
					/* translators: %d: number of edited template parts */
					_n(
						'%d template part',
						'%d template parts',
						customizations.parts.length,
						'blocklane'
					),
					customizations.parts.length
				)
			);
		}
		return bits.join( ', ' );
	};

	// Summary values: prefer the header the server actually wrote (blank
	// optional fields resolve server-side — parent theme / site), then fall
	// back to what was entered.
	const written = ( header, entered ) =>
		( resultHeaders && resultHeaders[ header ] ) || entered || '—';

	const createChildTheme = () => {
		setIsBusy( true );
		setError( '' );
		childThemeApi
			.create( {
				themeName,
				themeUrl,
				description,
				author,
				authorUrl,
				version,
				textDomain,
				importCustomizations: hasCustomizations && importCustomizations,
			} )
			.then( ( response ) => {
				setImportResult( response?.imported ?? null );
				setResultHeaders( response?.headers ?? null );
				setChildThemeCreated( true );
			} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
			} )
			.finally( () => {
				setIsBusy( false );
			} );
	};

	if ( ! toolEnabled ) {
		return (
			<Flex
				align="stretch"
				gap="0"
				className="blocklane-pro-extensions blocklane-pro-child-theme"
			>
				<FlexItem className="blocklane-pro-extensions__sidebar">
					<section className="blocklane-pro-extensions__section">
						<header className="blocklane-pro-extensions__header">
							<h1
								className="blocklane-pro-extensions__title"
								ref={ pageStart }
								tabIndex={ -1 }
							>
								{ __( 'Create a Child Theme', 'blocklane' ) }
							</h1>
						</header>
						<Notice status="info" isDismissible={ false }>
							{ __( 'This tool is turned off.', 'blocklane' ) }
						</Notice>
						<p className="blocklane-pro-child-theme__help">
							{ __(
								'The Child Theme Generator is hidden and its endpoints are disabled. Turn it back on from the Advanced screen whenever you need to generate another child theme.',
								'blocklane'
							) }
						</p>
						<Button
							variant="primary"
							__next40pxDefaultSize
							href={ advancedUrl }
						>
							{ __( 'Open Advanced settings', 'blocklane' ) }
						</Button>
					</section>
				</FlexItem>
			</Flex>
		);
	}

	return (
		<Flex
			align="stretch"
			gap="0"
			className={
				helpOpen
					? 'blocklane-pro-extensions blocklane-pro-child-theme'
					: 'blocklane-pro-extensions blocklane-pro-child-theme is-help-closed'
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
							{ __( 'Create a Child Theme', 'blocklane' ) }
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

					{ childThemeCreated ? (
						<Flex
							direction="column"
							gap={ 4 }
							align="stretch"
							className="blocklane-pro-child-theme__result"
						>
							<Notice status="success" isDismissible={ false }>
								{ __(
									'Child theme created and activated.',
									'blocklane'
								) }
							</Notice>

							{ importResult === true ? (
								<p className="blocklane-pro-child-theme__help">
									{ __(
										'Your customizations were copied into the child theme’s files.',
										'blocklane'
									) }
								</p>
							) : null }
							{ importResult === false ? (
								<p className="blocklane-pro-child-theme__help">
									{ __(
										'Some customizations could not be copied — check the child theme’s files.',
										'blocklane'
									) }
								</p>
							) : null }

							<Flex
								gap={ 6 }
								align="flex-start"
								className="blocklane-pro-child-theme__summary"
							>
								<Flex
									direction="column"
									gap={ 0 }
									style={ { width: '100%' } }
								>
									<SummaryRow
										label={ __(
											'Theme Name',
											'blocklane'
										) }
										value={ written(
											'Theme Name',
											themeName
										) }
									/>
									<SummaryRow
										label={ __( 'Theme URL', 'blocklane' ) }
										value={ written(
											'Theme URI',
											themeUrl
										) }
									/>
									<SummaryRow
										label={ __(
											'Description',
											'blocklane'
										) }
										value={ written(
											'Description',
											description
										) }
									/>
									<SummaryRow
										label={ __( 'Author', 'blocklane' ) }
										value={ written( 'Author', author ) }
									/>
									<SummaryRow
										label={ __(
											'Author URL',
											'blocklane'
										) }
										value={ written(
											'Author URI',
											authorUrl
										) }
									/>
									<SummaryRow
										label={ __( 'Version', 'blocklane' ) }
										value={ written( 'Version', version ) }
									/>
									<SummaryRow
										label={ __(
											'Text Domain',
											'blocklane'
										) }
										value={ written(
											'Text Domain',
											textDomain
										) }
										last
									/>
								</Flex>
								<img
									src={ screenshot }
									alt={ __(
										'Child theme preview',
										'blocklane'
									) }
									className="blocklane-pro-child-theme__screenshot"
								/>
							</Flex>

							<p className="blocklane-pro-child-theme__help">
								{ __(
									'Done with this tool? You can hide it from the menu — flip “Child Theme Generator” off under Advanced, and back on whenever you need it again.',
									'blocklane'
								) }{ ' ' }
								<a href={ advancedUrl }>
									{ __( 'Open Advanced', 'blocklane' ) }
								</a>
							</p>
						</Flex>
					) : (
						<div className="blocklane-pro-child-theme__fields">
							<Field
								label={ __(
									'Theme Name (optional)',
									'blocklane'
								) }
								help={ __(
									'The name of your child theme as it appears in the WordPress admin.',
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ themeName }
									onChange={ setThemeName }
									placeholder={
										defaults.parentName
											? sprintf(
													/* translators: %s: parent theme name. */
													__(
														'%s Child',
														'blocklane'
													),
													defaults.parentName
												)
											: __(
													'My Child Theme',
													'blocklane'
												)
									}
								/>
							</Field>

							<Field
								label={ __(
									'Theme URL (optional)',
									'blocklane'
								) }
								help={ __(
									'The URL where users can learn more about your theme.',
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="url"
									value={ themeUrl }
									onChange={ setThemeUrl }
									placeholder="https://example.com"
								/>
							</Field>

							<Field
								label={ __(
									'Description (optional)',
									'blocklane'
								) }
								help={ __(
									'A brief description of your child theme.',
									'blocklane'
								) }
							>
								<TextareaControl
									__nextHasNoMarginBottom
									value={ description }
									onChange={ setDescription }
									placeholder={
										defaults.parentName
											? sprintf(
													/* translators: %s: parent theme name. */
													__(
														'A child theme of %s.',
														'blocklane'
													),
													defaults.parentName
												)
											: __(
													'A child theme.',
													'blocklane'
												)
									}
								/>
							</Field>

							<Field
								label={ __( 'Author (optional)', 'blocklane' ) }
								help={ __(
									'The name of the theme author.',
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ author }
									onChange={ setAuthor }
									placeholder={ __(
										'Your name',
										'blocklane'
									) }
								/>
							</Field>

							<Field
								label={ __(
									'Author URL (optional)',
									'blocklane'
								) }
								help={ __(
									"The URL of the theme author's website.",
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="url"
									value={ authorUrl }
									onChange={ setAuthorUrl }
									placeholder="https://example.com"
								/>
							</Field>

							<Field
								label={ __(
									'Version (optional)',
									'blocklane'
								) }
								help={ __(
									'The version number of your child theme.',
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ version }
									onChange={ setVersion }
									placeholder="1.0.0"
								/>
							</Field>

							<Field
								label={ __(
									'Text Domain (optional)',
									'blocklane'
								) }
								help={ __(
									'The text domain used for theme translations.',
									'blocklane'
								) }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ textDomain }
									onChange={ setTextDomain }
									placeholder={
										defaults.textDomain || 'blocklane-child'
									}
								/>
							</Field>

							{ hasCustomizations ? (
								<div className="blocklane-pro-child-theme__import">
									<CheckboxControl
										__nextHasNoMarginBottom
										label={ sprintf(
											/* translators: %s: name of the theme being imported from */
											__(
												'Import customizations from %s',
												'blocklane'
											),
											customizations.themeName ||
												__(
													'the parent theme',
													'blocklane'
												)
										) }
										checked={ importCustomizations }
										onChange={ setImportCustomizations }
									/>
									<p className="blocklane-pro-child-theme__help">
										{ sprintf(
											/* translators: %s: list of customizations, e.g. "your global styles, 2 edited templates" */
											__(
												'Bakes %s into the child theme’s files so it keeps the look you’ve built. Your current theme is left unchanged.',
												'blocklane'
											),
											importSummary()
										) }
									</p>
								</div>
							) : null }

							<Flex
								justify="flex-end"
								className="blocklane-pro-child-theme__actions"
							>
								<Button
									variant="primary"
									__next40pxDefaultSize
									disabled={ isBusy }
									isBusy={ isBusy }
									onClick={ () => setIsModalOpen( true ) }
								>
									{ __(
										'Create and Activate Child Theme',
										'blocklane'
									) }
								</Button>
							</Flex>
						</div>
					) }
				</section>
			</FlexItem>

			<HelpTab
				isOpen={ helpOpen }
				onToggle={ () => setHelpOpen( ! helpOpen ) }
				panelId="blocklane-pro-help-panel-child-theme"
			/>
			<FlexItem
				className="blocklane-pro-extensions__preview"
				id="blocklane-pro-help-panel-child-theme"
				aria-hidden={ ! helpOpen }
			>
				<section className="blocklane-pro-extensions__preview-content">
					<p>
						<strong>{ __( 'Overview', 'blocklane' ) }</strong>
					</p>
					<p>
						{ __(
							'Create and activate a child theme based on your active theme so your customizations survive parent-theme updates.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'What is a child theme?', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ __(
							'A child theme inherits the functionality and styling of another theme (the parent) while letting you make customizations that survive parent-theme updates.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'Do I need a child theme?', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ __(
							'These days most users don’t — many customizations can be done directly in the block and site editor.',
							'blocklane'
						) }
					</p>
					<Divider />
					<p>
						<strong>
							{ __( 'I lost my customizations!', 'blocklane' ) }
						</strong>
					</p>
					<p>
						{ __(
							'Global style changes are tied to your active theme. Activate your child theme first, then make customizations.',
							'blocklane'
						) }
					</p>
				</section>
			</FlexItem>

			{ isModalOpen && (
				<Modal
					title={ __( 'Heads up!', 'blocklane' ) }
					onRequestClose={ () => setIsModalOpen( false ) }
					className="blocklane-pro-child-theme-modal"
				>
					<p>
						{ __(
							"Global style changes (colors, typography, etc.) are tied to your active theme. If you activate a child theme after making customizations, those changes won't appear — you'd need to re-activate the previous theme to see them again.",
							'blocklane'
						) }
					</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button
							variant="tertiary"
							onClick={ () => setIsModalOpen( false ) }
						>
							{ __( 'Cancel', 'blocklane' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								setIsModalOpen( false );
								createChildTheme();
							} }
						>
							{ __( 'Create and Activate', 'blocklane' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</Flex>
	);
};
