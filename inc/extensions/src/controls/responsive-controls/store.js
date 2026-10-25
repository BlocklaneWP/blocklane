/**
 * Responsive editing-breakpoint store.
 *
 * A global "which breakpoint am I editing" value that the responsive controls
 * read, so per-breakpoint overrides can be authored even where the core Site
 * Editor device switcher is unavailable — most notably when focus-editing a
 * template part (header/footer), where WordPress locks the device preview.
 *
 * @package
 */

import { createReduxStore, register } from '@wordpress/data';

export const RESPONSIVE_STORE = 'blocklane-pro/responsive';

const DEFAULT_STATE = {
	// Which breakpoint is being edited (the device switcher value).
	breakpoint: 'desktop',
	// The site-wide tablet/mobile breakpoint sizes (px), seeded from PHP. Held
	// here so every panel + the inline editor share one reactive source rather
	// than a mutated module global.
	globalBreakpoints: window.blocklaneProExtensions?.breakpoints || {
		tablet: 768,
		mobile: 480,
	},
};

const actions = {
	setBreakpoint( breakpoint ) {
		return { type: 'SET_BREAKPOINT', breakpoint };
	},
	setGlobalBreakpoints( breakpoints ) {
		return { type: 'SET_GLOBAL_BREAKPOINTS', breakpoints };
	},
};

function reducer( state = DEFAULT_STATE, action ) {
	if ( action.type === 'SET_BREAKPOINT' ) {
		return { ...state, breakpoint: action.breakpoint || 'desktop' };
	}
	if ( action.type === 'SET_GLOBAL_BREAKPOINTS' ) {
		return {
			...state,
			globalBreakpoints: {
				tablet: action.breakpoints.tablet,
				mobile: action.breakpoints.mobile,
			},
		};
	}
	return state;
}

const selectors = {
	getBreakpoint( state ) {
		return state.breakpoint;
	},
	getGlobalBreakpoints( state ) {
		return state.globalBreakpoints;
	},
};

let isRegistered = false;

/**
 * Register the store (idempotent).
 */
export function registerResponsiveStore() {
	if ( isRegistered ) {
		return;
	}
	isRegistered = true;
	register(
		createReduxStore( RESPONSIVE_STORE, { reducer, actions, selectors } )
	);
}

// Register on import so useSelect( RESPONSIVE_STORE ) is safe immediately.
registerResponsiveStore();
