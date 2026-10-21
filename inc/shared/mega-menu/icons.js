/**
 * Inline SVGs shared by the editor: the toggle chevron (mirrors render.php)
 * and the block's inserter icon.
 */

export const ToggleIcon = () => (
	<svg
		viewBox="0 0 12 12"
		width="12"
		height="12"
		aria-hidden="true"
		focusable="false"
		fill="none"
	>
		<path
			d="M2 4.25 6 8l4-3.75"
			stroke="currentColor"
			strokeWidth="1.5"
			fill="none"
		/>
	</svg>
);

export const BlockIcon = () => (
	<svg viewBox="0 0 24 24" width="24" height="24" fill="none">
		<path
			d="M5 6.5h14M5 10h6"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
		<rect
			x="5"
			y="13"
			width="14"
			height="6.5"
			rx="1.25"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
	</svg>
);
