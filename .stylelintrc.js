const path = require( 'path' );

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
	// Resolved from this file, not from the linting package: BlocklaneWP
	// Site's config is this one (require), and stylelint resolves a relative
	// plugin path against the extending package's directory, where bin/ has
	// no such rule.
	plugins: [ path.join( __dirname, 'bin/stylelint-shell-allow.mjs' ) ],
	overrides: [
		{
			// The page shell's stylesheets (spec 2026-10-10 §5, §6, C1): tokens
			// only. No named color and no color function, no !important, and
			// a px length, a hex color or a font-weight literal only where
			// design/shell-allow.json lists it for that property
			// (bin/stylelint-shell-allow.mjs, Amendment AJ). core's
			// color-no-hex is off here because the allow-list rule is the
			// stricter one: an unlisted hex still fails, by name.
			// A screen whose unit owns its directory keeps its sheet there, so
			// the sheet leaves an edition with the unit, under the same rule
			// (the C-content-types spec (2026-10-13) §3.6, DCT1).
			files: [
				'inc/onboarding/src/blocklane-page/**/*.css',
				'inc/onboarding/src/screens/content-types/*.css',
			],
			rules: {
				'color-named': 'never',
				'function-disallowed-list': [
					'rgb',
					'rgba',
					'hsl',
					'hsla',
					'hwb',
					'lab',
					'lch',
					'oklab',
					'oklch',
					'color',
					'color-mix',
				],
				'declaration-no-important': true,
				'blocklane/shell-allow-literal': true,
			},
		},
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
