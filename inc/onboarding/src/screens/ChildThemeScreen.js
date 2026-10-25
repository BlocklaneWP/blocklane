/**
 * Create Child Theme: one form, one confirm, one result.
 *
 * Authored for the C-child-theme package (spec 2026-10-12-c-child-theme.md)
 * from the Figma frames on page "Create Child Theme" (`87:617`: the default,
 * import, confirm, error, customizations-error, created, created-imported,
 * created-import-partial and off frames), the design spec's Amendment M and
 * decisions D17–D20, and core's own controls. Layout and tokens come from
 * the frames; every sentence comes from child-theme-copy.js, and the About
 * card's from child-theme-about.js, drawn by the shared About (AN 1) (the
 * frames still draw a retired copy; spec §1).
 *
 * What core renders, core renders: each field is a TextControl or
 * TextareaControl with its own label and help (BaseControl's 11px uppercase
 * label, 40px input, help line), the import offer a CheckboxControl with its
 * help, the confirm core's ConfirmDialog with its title shown (R4) and its
 * busy state (DC1), a failed check for Site Editor changes the one LoadError
 * (V22), and the turned-off screen BlocklanePage's disabled treatment with
 * its "Open Blocklane Settings" link to this tool's row (D20). The sheet
 * blocklane-page/child-theme/style.css only lays these out.
 *
 * Creating writes a child theme and switches the site to it through POST
 * blocklane-pro/v1/create-child-theme; blank fields are filled on the
 * server (Helper::child_theme_fallbacks(), localized as the placeholders).
 */

import { sprintf } from '@wordpress/i18n';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	TextControl,
	TextareaControl,
	CheckboxControl,
	Button,
	Notice,
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';

import { childTheme as childThemeApi } from '../api/client';
import { errorMessage } from '../api/errors';
import { BlocklanePage } from '../components/BlocklanePage';
import { About } from '../components/About';
import { LoadError } from '../components/LoadError';
import { isToolScreenOn } from '../components/tool-screens';
import { settingsHref } from './settings/href';
import { childThemeCopy } from './child-theme-copy';
import { ABOUT, LEARN_MORE_LABEL } from './child-theme-about';

/** The Settings row that turns this tool on and off. */
const TOOL_ROW = 'child-theme-tool';

/*
 * Fill a child-theme-copy.js format. The formats are not literals here, so
 * the lint rule that reads sprintf() formats cannot check them;
 * child-theme-copy.test.js pins the placeholders each one carries.
 */
const fill = ( format, ...values ) => sprintf( format, ...values );

/** Every field starts empty, so each shows the value a blank field writes. */
const EMPTY = {
	themeName: '',
	themeUrl: '',
	description: '',
	author: '',
	authorUrl: '',
	version: '',
	textDomain: '',
};

/**
 * The form's fields in the frames' order: the request key, the anchor slug,
 * the style.css header the server writes it to (the summary reads that
 * header back), the label and help, and how it is entered.
 *
 * @param {Object} copy The screen's copy.
 * @return {Array<Object>} The fields.
 */
const fieldsOf = ( copy ) => [
	{
		key: 'themeName',
		slug: 'theme-name',
		header: 'Theme Name',
		label: copy.fieldThemeNameLabel,
		help: copy.fieldThemeNameHelp,
	},
	{
		key: 'themeUrl',
		slug: 'theme-url',
		header: 'Theme URI',
		label: copy.fieldThemeUrlLabel,
		help: copy.fieldThemeUrlHelp,
		type: 'url',
	},
	{
		key: 'description',
		slug: 'description',
		header: 'Description',
		label: copy.fieldDescriptionLabel,
		help: copy.fieldDescriptionHelp,
		multiline: true,
	},
	{
		key: 'author',
		slug: 'author',
		header: 'Author',
		label: copy.fieldAuthorLabel,
		help: copy.fieldAuthorHelp,
	},
	{
		key: 'authorUrl',
		slug: 'author-url',
		header: 'Author URI',
		label: copy.fieldAuthorUrlLabel,
		help: copy.fieldAuthorUrlHelp,
		type: 'url',
	},
	{
		key: 'version',
		slug: 'version',
		header: 'Version',
		label: copy.fieldVersionLabel,
		help: copy.fieldVersionHelp,
	},
	{
		key: 'textDomain',
		slug: 'text-domain',
		header: 'Text Domain',
		label: copy.fieldTextDomainLabel,
		help: copy.fieldTextDomainHelp,
	},
];

/** The details card: the shared About with the screen's own copy (AN 1). */
const DETAILS = {
	content: (
		<About
			intro={ ABOUT.intro }
			groups={ ABOUT.groups }
			note={ ABOUT.note }
			learnMore={ { url: ABOUT.learnMoreUrl, label: LEARN_MORE_LABEL } }
		/>
	),
};

export const ChildThemeScreen = () => {
	const copy = childThemeCopy();
	const fields = fieldsOf( copy );
	const admin = window.blocklaneProAdmin || {};
	const localized = admin.childTheme || {};
	// The header each blank field writes, keyed by request key and shown
	// verbatim as that field's placeholder (Helper::child_theme_fallbacks(),
	// the one source the generator reads too).
	const placeholders = localized.placeholders || {};
	// The screenshot generation copies into every child, cache-busted.
	const screenshot =
		localized.screenshot ||
		`${ admin.pluginUrl || '' }/inc/child-theme/screenshot.png`;
	const toolOn = isToolScreenOn( 'child-theme' );

	const [ values, setValues ] = useState( EMPTY );
	const [ customizations, setCustomizations ] = useState( null );
	// A failed check: { detail }, the server's message (LoadError's second
	// line, as every load error carries it); false otherwise.
	const [ checkFailed, setCheckFailed ] = useState( false );
	const [ importChanges, setImportChanges ] = useState( true );
	const [ confirming, setConfirming ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	// What the server wrote: `imported` (true, false for a partial copy, or
	// null when nothing was asked for) and the style.css headers.
	const [ result, setResult ] = useState( null );
	// A refused create is reported above the form, while focus is back on
	// Create at its foot: bring the notice into view so it is seen, not
	// only announced (core's Notice speaks it).
	const errorRef = useRef( null );
	useEffect( () => {
		if ( error ) {
			errorRef.current?.scrollIntoView?.( { block: 'nearest' } );
		}
	}, [ error ] );

	// What the Site Editor has saved for the active theme, for the import
	// offer. A failure leaves the form whole (creating still works) and
	// says so with a Retry, never a silently missing offer.
	const loadCustomizations = useCallback( () => {
		setCheckFailed( false );
		childThemeApi
			.customizations()
			.then( setCustomizations )
			.catch( ( err ) =>
				setCheckFailed( { detail: errorMessage( err ) } )
			);
	}, [] );

	useEffect( () => {
		// While the tool is off its routes are not registered.
		if ( toolOn ) {
			loadCustomizations();
		}
	}, [ toolOn, loadCustomizations ] );

	if ( ! toolOn ) {
		return (
			<BlocklanePage
				screen="child-theme"
				title={ copy.pageTitle }
				disabled={ {
					notice: copy.offNotice,
					body: copy.offBody,
					slug: TOOL_ROW,
				} }
			/>
		);
	}

	const templates = customizations?.templates?.length || 0;
	const parts = customizations?.parts?.length || 0;
	const hasCustomizations =
		!! customizations?.hasGlobalStyles || templates > 0 || parts > 0;

	// The import help names what would be copied: one item alone, or two or
	// three through the copy's own list patterns (translators order them).
	const counted = childThemeCopy( { templates, parts } );
	const items = [];
	if ( customizations?.hasGlobalStyles ) {
		items.push( counted.importGlobalStyles );
	}
	if ( templates ) {
		items.push( fill( counted.importTemplates, templates ) );
	}
	if ( parts ) {
		items.push( fill( counted.importParts, parts ) );
	}
	let itemList = items[ 0 ] || '';
	if ( 2 === items.length ) {
		itemList = fill( counted.importListTwo, ...items );
	} else if ( 3 === items.length ) {
		itemList = fill( counted.importListThree, ...items );
	}

	const create = () => {
		if ( busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		childThemeApi
			.create( {
				...values,
				importCustomizations: hasCustomizations && importChanges,
			} )
			.then( ( response ) =>
				setResult( {
					imported: response?.imported ?? null,
					headers: response?.headers || {},
				} )
			)
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => {
				setBusy( false );
				setConfirming( false );
			} );
	};

	// The written header wins (the server fills blank fields), then what
	// was typed.
	const written = ( field ) =>
		result.headers[ field.header ] || values[ field.key ] || '—';

	const form = (
		<div className="blocklane-child-theme__column" data-column>
			<p className="blocklane-child-theme__note" data-optional-note>
				{ copy.optionalNote }
			</p>
			{ fields.map( ( field ) => {
				const shared = {
					__nextHasNoMarginBottom: true,
					label: field.label,
					help: field.help,
					value: values[ field.key ],
					placeholder: placeholders[ field.key ],
					onChange: ( next ) =>
						setValues( ( now ) => ( {
							...now,
							[ field.key ]: next,
						} ) ),
				};
				return (
					<div key={ field.key } data-field={ field.slug }>
						{ field.multiline ? (
							<TextareaControl { ...shared } rows={ 5 } />
						) : (
							<TextControl
								{ ...shared }
								__next40pxDefaultSize
								type={ field.type || 'text' }
							/>
						) }
					</div>
				);
			} ) }
			{ hasCustomizations ? (
				<div className="blocklane-child-theme__import" data-import>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ fill(
							copy.importLabel,
							customizations.themeName ||
								copy.importSourceFallback
						) }
						help={ fill( copy.importHelp, itemList ) }
						checked={ importChanges }
						onChange={ setImportChanges }
					/>
				</div>
			) : null }
			<div className="blocklane-child-theme__actions" data-actions>
				<Button
					variant="primary"
					__next40pxDefaultSize
					data-create
					onClick={ () => setConfirming( true ) }
				>
					{ copy.createButton }
				</Button>
			</div>
		</div>
	);

	const done = result ? (
		<div className="blocklane-child-theme__result" data-result>
			<div data-notice="created">
				<Notice status="success" isDismissible={ false }>
					{ copy.successNotice }
				</Notice>
			</div>
			{ true === result.imported ? (
				<p className="blocklane-child-theme__small" data-import-note>
					{ copy.importDoneNote }
				</p>
			) : null }
			{ false === result.imported ? (
				<div data-import-note>
					<Notice status="warning" isDismissible={ false }>
						{ copy.importPartialNote }
					</Notice>
				</div>
			) : null }
			<div className="blocklane-child-theme__summary" data-summary>
				<dl className="blocklane-child-theme__rows" data-summary-rows>
					{ fields.map( ( field ) => (
						<div
							key={ field.key }
							className="blocklane-child-theme__row"
							data-row={ field.slug }
						>
							<dt>{ field.label }</dt>
							<dd>{ written( field ) }</dd>
						</div>
					) ) }
				</dl>
				<img
					className="blocklane-child-theme__screenshot"
					data-screenshot
					src={ screenshot }
					alt={ copy.screenshotAlt }
				/>
			</div>
			<p className="blocklane-child-theme__small" data-hide-tool-note>
				{ copy.hideToolNote }{ ' ' }
				<a href={ settingsHref( TOOL_ROW ) }>{ copy.hideToolLink }</a>
			</p>
		</div>
	) : null;

	return (
		<BlocklanePage
			screen="child-theme"
			title={ copy.pageTitle }
			details={ DETAILS }
		>
			<div className="blocklane-child-theme">
				{ checkFailed && ! result ? (
					<LoadError
						message={ copy.customizationsError }
						detail={ checkFailed.detail }
						onRetry={ loadCustomizations }
					/>
				) : null }
				{ error ? (
					<div data-notice="error" ref={ errorRef }>
						<Notice
							status="error"
							onRemove={ () => setError( '' ) }
						>
							{ error }
						</Notice>
					</div>
				) : null }
				{ done || form }
			</div>
			<ConfirmDialog
				isOpen={ confirming }
				title={ copy.confirmTitle }
				__experimentalHideHeader={ false }
				size="medium"
				isBusy={ busy }
				confirmButtonText={ copy.confirmSubmit }
				cancelButtonText={ copy.confirmCancel }
				onConfirm={ create }
				onCancel={ () => ! busy && setConfirming( false ) }
			>
				{ copy.confirmBody }
			</ConfirmDialog>
		</BlocklanePage>
	);
};
