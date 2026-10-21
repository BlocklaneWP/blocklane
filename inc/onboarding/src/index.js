/**
 * Blocklane — admin dashboard React entry.
 *
 * Mounts the App into <div id="blocklane-pro-app"> rendered by
 * Settings::render_app_root(). Installs the X-WP-Nonce middleware so
 * apiFetch authenticates against our manage_options-gated REST routes.
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { registerCoreBlocks } from '@wordpress/block-library';

import { App } from './App';
import { applyAdminCanvas } from './components/admin-canvas';
import './style.css';
// The page shell (components/BlocklanePage.js), after the older sheet so
// its tokens win where both style one element. Named style.css so
// wp-scripts puts it in style-index.css, the one sheet the page enqueues.
import './blocklane-page/style.css';
// The Settings list's parts (spec 2026-10-12-c-settings.md §3.3), each in a
// style.css of its own folder so wp-scripts bundles them into style-index.css.
import './blocklane-page/feature-list/style.css';
import './blocklane-page/feature-row/style.css';
import './blocklane-page/about/style.css';
// The SEO screen's sheet (spec 2026-10-12-c-seo.md). Imported here, not from
// the lazily loaded SeoScreen: wp-scripts names a style.css chunk after its
// entry, and a lazy chunk has no name, so the build would fail.
import './blocklane-page/seo/style.css';
// The Forms screen's own sheet (spec 2026-10-12-c-forms.md).
import './blocklane-page/forms/style.css';
// The Site Visibility screen (spec 2026-10-12-c-site-privacy.md), here and
// not in its lazy chunk, whose CSS no page enqueues.
import './blocklane-page/site-privacy/style.css';
// The Create Child Theme screen's layout (spec 2026-10-12-c-child-theme.md).
import './blocklane-page/child-theme/style.css';
// The Dashboard's own sheet (the C-home spec (2026-10-13)).
import './blocklane-page/home/style.css';

const settings = window.blocklaneProAdmin || {};

// Code-split screen chunks load from our build dir. Set webpack's public path
// from the localized plugin URL so dynamic imports resolve correctly (with
// webpack's 'auto' inference as the fallback when pluginUrl is absent).
if ( settings.pluginUrl ) {
	// eslint-disable-next-line no-undef, camelcase
	__webpack_public_path__ = settings.pluginUrl + '/inc/onboarding/build/';
}

if ( settings.restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( settings.restNonce ) );
}

// Block parse() returns [] when block types aren't registered — WP only
// auto-registers core blocks on the post/site editor screens. Register them
// explicitly so BlockPreview can render pattern markup on our admin page.
registerCoreBlocks();

const mount = document.getElementById( 'blocklane-pro-app' );

if ( mount ) {
	mount.classList.add( 'blocklane-pro-root' );
	// The canvas follows the admin color scheme (components/admin-canvas.js).
	applyAdminCanvas( mount );
	createRoot( mount ).render( <App /> );
}
