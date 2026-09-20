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
import './style.css';

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
	createRoot( mount ).render( <App /> );
}
