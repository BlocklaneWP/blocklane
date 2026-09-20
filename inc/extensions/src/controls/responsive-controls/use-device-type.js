/**
 * Shared hook for the current editing device/breakpoint.
 *
 * Reads CORE's device type (the View-menu Desktop/Tablet/Mobile switcher —
 * public API, present in every editing context since 7.1, template-part focus
 * mode included). This is the same device core's "Responsive styles" mode
 * scopes its viewport states to, so the Blocklane extras and core's
 * per-viewport panels always follow ONE switcher.
 *
 * @package
 */
import { useSelect } from '@wordpress/data';

/**
 * Returns the current device type: 'Desktop', 'Tablet', or 'Mobile'.
 */
export function useDeviceType() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return (
			( editor?.getDeviceType ? editor.getDeviceType() : null ) ||
			'Desktop'
		);
	}, [] );
}

/**
 * Returns the breakpoint key for the current device type.
 * Returns null for Desktop (no override at desktop).
 */
export function useBreakpoint() {
	const deviceType = useDeviceType();
	if ( deviceType === 'Tablet' ) {
		return 'tablet';
	}
	if ( deviceType === 'Mobile' ) {
		return 'mobile';
	}
	return null;
}
