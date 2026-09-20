module.exports = {
	// Replicate @wordpress/scripts' built-in default exactly (its
	// config/.stylelintrc.json) — a local config replaces it entirely, and
	// this one exists ONLY to add the vendor exclusion below (mirrors
	// .eslintrc.js). selector-class-pattern is what wp-scripts itself
	// disables, not a local choice.
	extends: [ '@wordpress/stylelint-config/scss-stylistic' ],
	rules: {
		'selector-class-pattern': null,
		// ACCEPTED RISK — see issue #31 for the symptom signature and the
		// re-enable trigger (splitting the dashboard sheet per screen).
		// The dashboard is one large stylesheet where many components share
		// a rightmost element (li, svg, code, .components-button), so this
		// rule flags pairs from unrelated screens that can never match the
		// same node — and where the pair IS related, its members differ in
		// specificity, so source order never decides the winner anyway.
		// Satisfying it would mean reshuffling whole component blocks for
		// zero cascade change. Cost: a genuine written-later-but-loses
		// ordering mistake goes unlinted — if a style visibly fails to
		// apply and DevTools shows it crossed out, check #31 first.
		'no-descending-specificity': null,
	},
	overrides: [
		{
			files: [ '**/*.scss' ],
			rules: {
				// A CSS-native rule misapplied to sass: sass @import INLINES
				// the file at that position, so a mid-file import is legal
				// and its position IS the deliberate cascade order
				// (style.scss layers control sheets after base rules on
				// purpose). "Fixing" it by hoisting imports would silently
				// reorder the built stylesheet.
				'no-invalid-position-at-import-rule': null,
			},
		},
	],
	ignoreFiles: [
		// Vendored third-party library — keep diffable against upstream.
		'inc/plugin-update-checker/**',
	],
};
