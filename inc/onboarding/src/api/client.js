/**
 * Thin REST client for blocklane-pro/v1 endpoints — the transport, plus the
 * route objects every edition carries.
 *
 * apiFetch handles cookies + the X-WP-Nonce header (installed via
 * createNonceMiddleware in index.js), so callers just describe shape.
 *
 * A unit's routes do NOT live here. Each unit's route object is in its own
 * file beside this one, owned by the unit in edition-manifest.json and
 * importing `get`/`post` from here, so an edition without the unit carries
 * neither the route shapes nor the paths. A route shape for an endpoint the
 * artifact does not register is not inert: it is a description of a paid
 * feature, and a request that can only 404. bin/ui-needles.php derives every
 * route literal of an absent unit's api file as a needle, so dist-check fails
 * a bundle and publish-free-source a source file that carries one.
 */

import apiFetch from '@wordpress/api-fetch';

export const NAMESPACE = '/blocklane-pro/v1';

export const get = ( path, options = {} ) =>
	apiFetch( { path: `${ NAMESPACE }${ path }`, ...options } );

export const post = ( path, data ) =>
	apiFetch( { path: `${ NAMESPACE }${ path }`, method: 'POST', data } );

export const extensions = {
	list: () => get( '/extensions' ),
	toggle: ( slug, enabled ) => post( '/extensions', { slug, enabled } ),
	getBreakpoints: () => get( '/breakpoints' ),
	saveBreakpoints: ( tablet, mobile ) =>
		post( '/breakpoints', { tablet, mobile } ),
	getGridCanvasTools: () => get( '/grid-canvas-tools' ),
	saveGridCanvasTools: ( enabled ) =>
		post( '/grid-canvas-tools', { enabled } ),
};

export const childTheme = {
	create: ( data ) => post( '/create-child-theme', data ),
	customizations: () => get( '/child-theme/customizations' ),
};

export const siteLock = {
	get: () => get( '/site-lock' ),
	// The cleartext password never rides on the settings GET — it's fetched
	// on demand (admin clicks the eye). `canReveal: false` with
	// `hasPassword: true` marks a legacy hash that can't be decrypted.
	reveal: () => get( '/site-lock/reveal' ),
	// `password` only updates the stored hash when non-empty; `clearPassword`
	// wipes it.
	// `regeneratePreview` rotates the shareable preview key (revokes old links).
	// `discourageSearch` mirrors core's blog_public option; omitted when undefined
	// so preview-only saves don't reset it (the server also guards on null).
	save: ( {
		enabled,
		mode,
		password = '',
		clearPassword = false,
		regeneratePreview = false,
		discourageSearch,
	} ) =>
		post( '/site-lock', {
			enabled,
			mode,
			password,
			clear_password: clearPassword,
			regenerate_preview: regeneratePreview,
			...( undefined !== discourageSearch && {
				discourage_search: discourageSearch,
			} ),
		} ),
};

export const security = {
	get: () => get( '/security' ),
	save: ( settings ) => post( '/security', { settings } ),
};

export const forms = {
	// Screen bootstrap: forms present in the table, settings, SMTP state.
	overview: () => get( '/forms' ),
	saveSettings: ( data ) => post( '/forms', data ),
	submissions: ( {
		form_id: formId = '',
		origin_id: originId = 0,
		status = '',
		search = '',
		page = 1,
		per_page: perPage = 20,
	} ) =>
		get(
			`/forms/submissions?${ new URLSearchParams( {
				form_id: formId,
				origin_id: originId,
				status,
				search,
				page,
				per_page: perPage,
			} ).toString() }`
		),
	setStatus: ( id, status ) =>
		post( `/forms/submissions/${ id }`, { status } ),
	remove: ( id ) => get( `/forms/submissions/${ id }`, { method: 'DELETE' } ),
	// The typed confirmation is validated SERVER-side; batched — re-call
	// while the response reports remaining > 0.
	removeAll: () =>
		get( `/forms/submissions/all?confirm=DELETE`, { method: 'DELETE' } ),
	// parse: false — the route streams the stored file; apiFetch carries the
	// nonce a bare <a href> would lack.
	downloadFile: ( id, index ) =>
		get( `/forms/submissions/${ id }/files/${ index }`, { parse: false } ),
	// Streams the filtered inbox as CSV (same parse:false blob pattern).
	exportCsv: ( {
		form_id: formId = '',
		origin_id: originId = 0,
		status = '',
		search = '',
	} ) =>
		get(
			`/forms/submissions/export?${ new URLSearchParams( {
				form_id: formId,
				origin_id: originId,
				status,
				search,
			} ).toString() }`,
			{ parse: false }
		),
};

export const seo = {
	get: () => get( '/seo' ),
	// `settings` is the blocklane_pro_seo map (partial saves allowed);
	// `search_visible` mirrors core's blog_public and is only written when
	// present in the payload.
	save: ( data ) => post( '/seo', data ),
	overview: () => get( '/seo/overview' ),
	content: ( {
		search = '',
		type = '',
		schema = '',
		page = 1,
		per_page: perPage = 20,
		orderby = 'title',
		order = 'asc',
	} ) =>
		get(
			`/seo/content?${ new URLSearchParams( {
				search,
				type,
				schema,
				page,
				per_page: perPage,
				orderby,
				order,
			} ).toString() }`
		),
	// One row's per-post fields from the Content tab's Edit SEO modal.
	saveContentRow: ( data ) => post( '/seo/content', data ),
	// Import from another SEO plugin: sources with data, then run one.
	importSources: () => get( '/seo/import' ),
	runImport: ( source ) => post( '/seo/import', { source } ),
};

export const advanced = {
	get: () => get( '/advanced' ),
	save: ( settings ) => post( '/advanced', { settings } ),
};
