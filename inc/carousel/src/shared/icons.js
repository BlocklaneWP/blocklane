/**
 * Block icons for the carousel suite that core's library does not carry —
 * Phosphor glyphs (MIT, see CREDITS.md): slideshow (regular) for the shell
 * and slideshow (fill) for the track. The slide and controls blocks use
 * core's wordpress/icons package (contents, queryPagination) in their own
 * registrations. Inlined as elements so the inserter renders them with the
 * theme's current text color.
 */

export const carouselIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 256 256"
		fill="currentColor"
		aria-hidden="true"
		focusable="false"
	>
		<path d="M192,48H64A16,16,0,0,0,48,64V192a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64A16,16,0,0,0,192,48Zm0,144H64V64H192V192ZM240,56V200a8,8,0,0,1-16,0V56a8,8,0,0,1,16,0ZM32,56V200a8,8,0,0,1-16,0V56a8,8,0,0,1,16,0Z" />
	</svg>
);

export const trackIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 256 256"
		fill="currentColor"
		aria-hidden="true"
		focusable="false"
	>
		<path d="M208,64V192a16,16,0,0,1-16,16H64a16,16,0,0,1-16-16V64A16,16,0,0,1,64,48H192A16,16,0,0,1,208,64Zm24-16a8,8,0,0,0-8,8V200a8,8,0,0,0,16,0V56A8,8,0,0,0,232,48ZM24,48a8,8,0,0,0-8,8V200a8,8,0,0,0,16,0V56A8,8,0,0,0,24,48Z" />
	</svg>
);
