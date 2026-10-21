/**
 * Site Visibility — who can see the front end (a password page, a
 * maintenance page, or everyone) plus core's "discourage search engines"
 * setting, which Settings → Reading shares.
 *
 * Authored for C-site-privacy of the admin-dashboard redesign (spec
 * the C-site-privacy spec (2026-10-12); design spec Amendments O, P
 * D25–D27, code item 6, R3/R3a/R3b, R6, V2, V5, W11, X4, X25) from the
 * Site Visibility frames on page `87:17357`, core components and `--wpds-*`
 * tokens (S/blocklane-page/site-privacy/style.css). Composition and the one
 * form state live here; the parts and every sentence live in
 * ./site-privacy/.
 *
 * REST: GET/POST blocklane-pro/v1/site-lock. The GET never carries the
 * password, only `hasPassword` and `canReveal` (false for a legacy hash);
 * the eye fetches the cleartext from /site-lock/reveal on demand. A save
 * reloads the page, because the toolbar indicator is drawn by the server
 * from the SAVED state.
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useReducer } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { Badge } from '@wordpress/ui';

import { siteLock } from '../api/client';
import { errorMessage } from '../api/errors';
import { BlocklanePage } from '../components/BlocklanePage';
import { LoadError } from '../components/LoadError';
import { NoticeDetail } from '../components/NoticeDetail';
import { isToolScreenOn } from '../components/tool-screens';
import { AboutSiteVisibility } from './site-privacy/AboutSiteVisibility';
import { SearchEnginesCard } from './site-privacy/SearchEnginesCard';
import { VisitorAccessCard } from './site-privacy/VisitorAccessCard';
import { loadError, saveError, statusSentence } from './site-privacy/copy';
import { reloadPage } from './site-privacy/reload';
import {
	STATUS_INTENT,
	passwordChanged,
	visibilityStatus,
} from './site-privacy/status';

/** How long Copy says "Copied". */
const COPIED_MS = 2000;

const initial = {
	load: 'loading',
	saved: null,
	draft: null,
	busy: false,
	notice: '',
	// The server's message under a failed load's or save's sentence: the
	// shared detail line (NoticeDetail; Settings SD13, D-SV3).
	detail: '',
	invalid: false,
	copied: false,
};

/**
 * The form a stored state opens as: nothing typed, nothing revealed.
 *
 * @param {Object} saved The GET or save reply.
 * @return {Object} The draft.
 */
const draftOf = ( saved ) => ( {
	enabled: !! saved.enabled,
	mode: saved.mode || 'coming-soon',
	password: '',
	revealed: null,
	showPassword: false,
	clearPassword: false,
	discourageSearch: !! saved.discourageSearch,
} );

/**
 * @param {Object} state  The screen.
 * @param {Object} action { type, … }.
 * @return {Object} The next screen.
 */
const reducer = ( state, action ) => {
	switch ( action.type ) {
		case 'load':
			return { ...state, load: 'loading' };
		case 'loaded':
			return {
				...state,
				load: 'ready',
				saved: action.saved,
				draft: draftOf( action.saved ),
			};
		case 'load-failed':
			return { ...state, load: 'failed', detail: action.detail || '' };
		case 'edit':
			return {
				...state,
				draft: { ...state.draft, ...action.patch },
				// Any edit but the eye answers the R6 error.
				invalid:
					state.invalid &&
					Object.keys( action.patch ).every(
						( key ) => 'showPassword' === key
					),
			};
		case 'revealed':
			return {
				...state,
				busy: false,
				draft: {
					...state.draft,
					password: action.password,
					revealed: action.password,
					showPassword: true,
				},
			};
		case 'preview':
			return {
				...state,
				busy: false,
				copied: false,
				saved: { ...state.saved, previewUrl: action.previewUrl },
			};
		case 'busy':
			return { ...state, busy: true, notice: '', detail: '' };
		case 'failed':
			return {
				...state,
				busy: false,
				notice: action.notice,
				detail: action.detail || '',
			};
		case 'dismiss':
			return { ...state, notice: '', detail: '' };
		case 'invalid':
			return { ...state, invalid: true };
		case 'copied':
			return { ...state, copied: action.copied };
	}
	return state;
};

export const SitePrivacyScreen = () => {
	const [ state, dispatch ] = useReducer( reducer, initial );
	const { saved, draft } = state;

	const load = useCallback( () => {
		dispatch( { type: 'load' } );
		siteLock
			.get()
			.then( ( reply ) => dispatch( { type: 'loaded', saved: reply } ) )
			.catch( ( err ) =>
				dispatch( { type: 'load-failed', detail: errorMessage( err ) } )
			);
	}, [] );

	useEffect( () => {
		if ( isToolScreenOn( 'site-privacy' ) ) {
			load();
		}
	}, [ load ] );

	useEffect( () => {
		if ( ! state.copied ) {
			return undefined;
		}
		const timer = window.setTimeout(
			() => dispatch( { type: 'copied', copied: false } ),
			COPIED_MS
		);
		return () => window.clearTimeout( timer );
	}, [ state.copied ] );

	if ( ! isToolScreenOn( 'site-privacy' ) ) {
		return (
			<BlocklanePage
				screen="site-privacy"
				disabled={ {
					slug: 'site-privacy',
					body: __(
						'Turning it off only hides this screen; whatever visibility you set stays in effect.',
						'blocklane'
					),
				} }
			/>
		);
	}

	const edit = ( patch ) => dispatch( { type: 'edit', patch } );

	const save = () => {
		if ( 'needs-password' === visibilityStatus( draft, saved ) ) {
			// R6: Save stays available and says what is missing.
			dispatch( { type: 'invalid' } );
			return;
		}
		dispatch( { type: 'busy' } );
		siteLock
			.save( {
				enabled: draft.enabled,
				mode: draft.mode,
				// A revealed, unedited password is the saved one: send none.
				password: passwordChanged( draft ) ? draft.password : '',
				clearPassword: draft.clearPassword,
				discourageSearch: draft.discourageSearch,
			} )
			// The toolbar indicator is the server's, from the saved state.
			.then( () => reloadPage() )
			.catch( ( err ) =>
				dispatch( {
					type: 'failed',
					notice: saveError(),
					detail: errorMessage( err ),
				} )
			);
	};

	const toggleEye = () => {
		if ( draft.showPassword ) {
			edit( { showPassword: false } );
			return;
		}
		if ( '' === draft.password && saved.hasPassword && saved.canReveal ) {
			dispatch( { type: 'busy' } );
			siteLock
				.reveal()
				.then( ( reply ) =>
					dispatch( {
						type: 'revealed',
						password: reply?.password || '',
					} )
				)
				.catch( ( err ) =>
					dispatch( { type: 'failed', notice: errorMessage( err ) } )
				);
			return;
		}
		edit( { showPassword: true } );
	};

	const copy = () => {
		if ( ! saved.previewUrl || ! window.navigator?.clipboard ) {
			return;
		}
		window.navigator.clipboard
			.writeText( saved.previewUrl )
			.then( () => dispatch( { type: 'copied', copied: true } ) )
			.catch( () => {
				// Refused (an insecure context, or denied): the URL stays in
				// its field to copy by hand.
			} );
	};

	// Rotates the key only: the STORED mode and switch are sent back, so a
	// pending edit is never saved by the side door.
	const regenerate = () => {
		dispatch( { type: 'busy' } );
		siteLock
			.save( {
				enabled: !! saved.enabled,
				mode: saved.mode,
				regeneratePreview: true,
			} )
			.then( ( reply ) =>
				dispatch( {
					type: 'preview',
					previewUrl: reply?.previewUrl || '',
				} )
			)
			.catch( ( err ) =>
				dispatch( {
					type: 'failed',
					notice: saveError(),
					detail: errorMessage( err ),
				} )
			);
	};

	let body;
	if ( 'failed' === state.load ) {
		body = (
			<LoadError
				message={ loadError() }
				detail={ state.detail }
				onRetry={ load }
			/>
		);
	} else if ( 'loading' === state.load ) {
		body = (
			<div data-loading className="blocklane-site-visibility__loading">
				<Spinner />
			</div>
		);
	} else {
		const status = visibilityStatus( draft, saved );
		body = (
			<div className="blocklane-site-visibility__form">
				<div data-status className="blocklane-site-visibility__status">
					<Badge
						intent={ STATUS_INTENT[ status ] }
						className="blocklane-site-visibility__badge"
					>
						{ statusSentence( status ) }
					</Badge>
				</div>
				<div
					data-panel="site-visibility"
					className="blocklane-site-visibility__cards"
				>
					<VisitorAccessCard
						draft={ draft }
						saved={ saved }
						busy={ state.busy }
						invalid={ state.invalid }
						copied={ state.copied }
						onChange={ edit }
						onToggleEye={ toggleEye }
						onCopy={ copy }
						onRegenerate={ regenerate }
					/>
					<SearchEnginesCard
						checked={ draft.discourageSearch }
						onChange={ ( value ) =>
							edit( { discourageSearch: value } )
						}
					/>
				</div>
				<div
					data-save-row
					className="blocklane-site-visibility__save-row"
				>
					<Button
						variant="primary"
						__next40pxDefaultSize
						isBusy={ state.busy }
						disabled={ state.busy }
						onClick={ save }
					>
						{ __( 'Save', 'blocklane' ) }
					</Button>
				</div>
			</div>
		);
	}

	return (
		<BlocklanePage
			screen="site-privacy"
			details={ { content: <AboutSiteVisibility /> } }
		>
			<div data-column className="blocklane-site-visibility">
				{ state.notice ? (
					// Above the form, outside it, as `93:9875` draws it.
					<div data-notice="error">
						<Notice
							status="error"
							onRemove={ () => dispatch( { type: 'dismiss' } ) }
						>
							{ state.notice }
							<NoticeDetail>{ state.detail }</NoticeDetail>
						</Notice>
					</div>
				) : null }
				{ body }
			</div>
		</BlocklanePage>
	);
};
