/**
 * Open-animation vocabulary for the shared blocklaneProAnimate* keyframes
 * (Animation extension bundle) — used by every surface that fires them from
 * an open state instead of a scroll trigger: the Mega Menu panel, the core
 * navigation overlay, and Popups. Values are allowlisted again server-side
 * by each consumer (render.php / runtime-mobile-menu.php / runtime.php).
 */
import { __ } from '@wordpress/i18n';

export const ANIMATION_OPTIONS = [
	{ label: __( 'None', 'blocklane' ), value: '' },
	{ label: __( 'Fade', 'blocklane' ), value: 'fadeIn' },
	{ label: __( 'Slide down', 'blocklane' ), value: 'fadeInDown' },
	{ label: __( 'Slide up', 'blocklane' ), value: 'fadeInUp' },
	{ label: __( 'Slide from left', 'blocklane' ), value: 'fadeInLeft' },
	{
		label: __( 'Slide from right', 'blocklane' ),
		value: 'fadeInRight',
	},
	{ label: __( 'Zoom', 'blocklane' ), value: 'zoomIn' },
];
