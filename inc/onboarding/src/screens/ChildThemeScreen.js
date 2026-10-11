/**
 * Create Child Theme screen.
 *
 * Collects the child theme's header details and creates + activates a child of
 * the active parent theme via POST blocklane-pro/v1/create-child-theme.
 * Renders in the dashboard's two-column shell (white form column + info panel),
 * with a confirm step and a success summary.
 */

import { sprintf } from '@wordpress/i18n';
import {
	useState,
	useEffect,
	useRef,
	cloneElement,
	Fragment,
} from '@wordpress/element';
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
import { childThemeCopy } from './child-theme-copy';

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

/*
 * Fill the placeholders of a child-theme-copy.js string. The formats are not
 * literals here, so the lint rule that checks sprintf() formats cannot read
 * them; child-theme-copy.test.js pins which placeholders each carries.
 */
const fill = ( format, ...values ) => sprintf( format, ...values );

/*
 * One, two or three items as one phrase, through the copy's own list
 * patterns: translators reorder the positional placeholders and choose the
 * separators, so no English joining happens here.
 */
const asList = ( copy, items ) => {
	if ( 3 === items.length ) {
		return fill( copy.importListThree, ...items );
	}
	if ( 2 === items.length ) {
		return fill( copy.importListTwo, ...items );
	}
	return items[ 0 ] || '';
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
	// Every string this screen shows comes from child-theme-copy.js.
	const copy = childThemeCopy();
	const settings = window.blocklaneProAdmin || {};
	const defaults = settings.childTheme || {};
	// What generation writes for each field left blank, keyed by field name
	// and localized from Helper::child_theme_fallbacks(), the one source the
	// generator reads too. Each is shown verbatim as its field's placeholder,
	// so a placeholder is the header a blank field writes, in any language.
	const placeholders = defaults.placeholders || {};
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
	// Empty, like every field, so the placeholder shows the version a blank
	// field writes (Helper::child_theme_fallbacks()).
	const [ version, setVersion ] = useState( '' );
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
					err?.message || childThemeCopy().customizationsError
				);
			} );
	}, [ toolEnabled ] );

	const hasCustomizations =
		!! customizations &&
		( customizations.hasGlobalStyles ||
			!! customizations.templates?.length ||
			!! customizations.parts?.length );

	const importSummary = () => {
		const templates = customizations?.templates?.length || 0;
		const parts = customizations?.parts?.length || 0;
		const counted = childThemeCopy( { templates, parts } );
		const bits = [];
		if ( customizations?.hasGlobalStyles ) {
			bits.push( counted.importGlobalStyles );
		}
		if ( templates ) {
			bits.push( fill( counted.importTemplates, templates ) );
		}
		if ( parts ) {
			bits.push( fill( counted.importParts, parts ) );
		}
		return asList( counted, bits );
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
								{ copy.pageTitle }
							</h1>
						</header>
						<Notice status="info" isDismissible={ false }>
							{ copy.offNotice }
						</Notice>
						<p className="blocklane-pro-child-theme__help">
							{ copy.offBody }
						</p>
						<Button
							variant="primary"
							__next40pxDefaultSize
							href={ advancedUrl }
						>
							{ copy.offButton }
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
							{ copy.pageTitle }
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
								{ copy.successNotice }
							</Notice>

							{ importResult === true ? (
								<p className="blocklane-pro-child-theme__help">
									{ copy.importDoneNote }
								</p>
							) : null }
							{ importResult === false ? (
								<p className="blocklane-pro-child-theme__help">
									{ copy.importPartialNote }
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
										label={ copy.summaryThemeName }
										value={ written(
											'Theme Name',
											themeName
										) }
									/>
									<SummaryRow
										label={ copy.summaryThemeUrl }
										value={ written(
											'Theme URI',
											themeUrl
										) }
									/>
									<SummaryRow
										label={ copy.summaryDescription }
										value={ written(
											'Description',
											description
										) }
									/>
									<SummaryRow
										label={ copy.summaryAuthor }
										value={ written( 'Author', author ) }
									/>
									<SummaryRow
										label={ copy.summaryAuthorUrl }
										value={ written(
											'Author URI',
											authorUrl
										) }
									/>
									<SummaryRow
										label={ copy.summaryVersion }
										value={ written( 'Version', version ) }
									/>
									<SummaryRow
										label={ copy.summaryTextDomain }
										value={ written(
											'Text Domain',
											textDomain
										) }
										last
									/>
								</Flex>
								<img
									src={ screenshot }
									alt={ copy.screenshotAlt }
									className="blocklane-pro-child-theme__screenshot"
								/>
							</Flex>

							<p className="blocklane-pro-child-theme__help">
								{ copy.hideToolNote }{ ' ' }
								<a href={ advancedUrl }>
									{ copy.hideToolLink }
								</a>
							</p>
						</Flex>
					) : (
						<div className="blocklane-pro-child-theme__fields">
							<Field
								label={ copy.fieldThemeNameLabel }
								help={ copy.fieldThemeNameHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ themeName }
									onChange={ setThemeName }
									placeholder={ placeholders.themeName }
								/>
							</Field>

							<Field
								label={ copy.fieldThemeUrlLabel }
								help={ copy.fieldThemeUrlHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="url"
									value={ themeUrl }
									onChange={ setThemeUrl }
									placeholder={ placeholders.themeUrl }
								/>
							</Field>

							<Field
								label={ copy.fieldDescriptionLabel }
								help={ copy.fieldDescriptionHelp }
							>
								<TextareaControl
									__nextHasNoMarginBottom
									value={ description }
									onChange={ setDescription }
									placeholder={ placeholders.description }
								/>
							</Field>

							<Field
								label={ copy.fieldAuthorLabel }
								help={ copy.fieldAuthorHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ author }
									onChange={ setAuthor }
									placeholder={ placeholders.author }
								/>
							</Field>

							<Field
								label={ copy.fieldAuthorUrlLabel }
								help={ copy.fieldAuthorUrlHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="url"
									value={ authorUrl }
									onChange={ setAuthorUrl }
									placeholder={ placeholders.authorUrl }
								/>
							</Field>

							<Field
								label={ copy.fieldVersionLabel }
								help={ copy.fieldVersionHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ version }
									onChange={ setVersion }
									placeholder={ placeholders.version }
								/>
							</Field>

							<Field
								label={ copy.fieldTextDomainLabel }
								help={ copy.fieldTextDomainHelp }
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									value={ textDomain }
									onChange={ setTextDomain }
									placeholder={ placeholders.textDomain }
								/>
							</Field>

							{ hasCustomizations ? (
								<div className="blocklane-pro-child-theme__import">
									<CheckboxControl
										__nextHasNoMarginBottom
										label={ fill(
											copy.importLabel,
											customizations.themeName ||
												copy.importSourceFallback
										) }
										checked={ importCustomizations }
										onChange={ setImportCustomizations }
									/>
									<p className="blocklane-pro-child-theme__help">
										{ fill(
											copy.importHelp,
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
									{ copy.createButton }
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
						<strong>{ copy.helpOverviewHeading }</strong>
					</p>
					<p>{ copy.helpOverviewBody }</p>
					{ copy.faq.map( ( entry ) => (
						<Fragment key={ entry.question }>
							<Divider />
							<p>
								<strong>{ entry.question }</strong>
							</p>
							<p>{ entry.answer }</p>
						</Fragment>
					) ) }
				</section>
			</FlexItem>

			{ isModalOpen && (
				<Modal
					title={ copy.confirmTitle }
					onRequestClose={ () => setIsModalOpen( false ) }
					className="blocklane-pro-child-theme-modal"
				>
					<p>{ copy.confirmBody }</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button
							variant="tertiary"
							onClick={ () => setIsModalOpen( false ) }
						>
							{ copy.confirmCancel }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								setIsModalOpen( false );
								createChildTheme();
							} }
						>
							{ copy.confirmSubmit }
						</Button>
					</Flex>
				</Modal>
			) }
		</Flex>
	);
};
