/**
 * Project ESLint config (flat, wp-scripts 36 / ESLint 9).
 *
 * The @wordpress/* packages below are RUNTIME EXTERNALS: the
 * dependency-extraction webpack plugin maps every import onto the copy
 * WordPress ships, so none of them is (or should be) installed here.
 * wp-scripts 34's stricter import resolution flags them as unresolved /
 * extraneous without this exemption — `import/core-modules` satisfies both
 * rules while keeping them live for real packages. Locally installed
 * @wordpress packages that ARE bundled (icons, interface, dataviews) resolve
 * normally and are deliberately not listed.
 *
 * TESTS ARE JEST. scripts 36 points its unit-test lint rules at Vitest; this
 * plugin keeps Jest (`test-unit-jest`, jest.config.cjs), so the default's
 * Vitest entries are dropped and eslint-plugin-jest's recommended rules
 * apply to the test files instead — the migration guide's "Keep Jest lint
 * rules" step.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );
const jestPlugin = require( 'eslint-plugin-jest' );
const globals = require( 'globals' );

const TEST_FILES = [
	'**/@(test|__tests__)/**/*.{js,jsx,ts,tsx,mjs,cjs}',
	'**/*.@(test|spec).{js,jsx,ts,tsx,mjs,cjs}',
];

module.exports = [
	...defaultConfig.filter( ( c ) => 'vitest/recommended' !== c.name ),
	{ ...jestPlugin.configs[ 'flat/recommended' ], files: TEST_FILES },
	{
		// Vendored library — not ours to lint.
		ignores: [ 'inc/plugin-update-checker/**' ],
	},
	{
		// Everything in this plugin executes in a browser (editor bundles,
		// admin assets, front-end loaders); the DOM globals also serve as
		// defined types for jsdoc annotations (HTMLElement, Element, …).
		languageOptions: {
			globals: { ...globals.browser },
		},
		settings: {
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/block-library',
				'@wordpress/block-serialization-default-parser',
				'@wordpress/blocks',
				'@wordpress/commands',
				'@wordpress/components',
				'@wordpress/compose',
				'@wordpress/core-data',
				'@wordpress/data',
				'@wordpress/date',
				'@wordpress/dom-ready',
				'@wordpress/editor',
				'@wordpress/element',
				'@wordpress/hooks',
				'@wordpress/html-entities',
				'@wordpress/i18n',
				'@wordpress/interactivity',
				'@wordpress/keyboard-shortcuts',
				'@wordpress/media-utils',
				'@wordpress/notices',
				'@wordpress/plugins',
				'@wordpress/preferences',
				'@wordpress/primitives',
				'@wordpress/rich-text',
				'@wordpress/url',
			],
		},
		rules: {
			// The __experimental components below ARE the editor-canonical
			// UI (ToolsPanel is the inspector idiom core itself builds with);
			// wp-scripts 34 turned the unsafe-API rule on by default. Each
			// name is an explicit allowance — a NEW experimental API still
			// fails lint until it is added here deliberately.
			'@wordpress/no-unsafe-wp-apis': [
				'error',
				{
					'@wordpress/components': [
						'__experimentalAlignmentMatrixControl',
						'__experimentalConfirmDialog',
						'__experimentalDivider',
						'__experimentalGrid',
						'__experimentalHStack',
						'__experimentalInputControl',
						'__experimentalInputControlPrefixWrapper',
						'__experimentalInputControlSuffixWrapper',
						'__experimentalNumberControl',
						'__experimentalParseQuantityAndUnitFromRawValue',
						'__experimentalSpacer',
						'__experimentalText',
						'__experimentalToggleGroupControl',
						'__experimentalToggleGroupControlOption',
						'__experimentalToggleGroupControlOptionIcon',
						'__experimentalToolsPanel',
						'__experimentalToolsPanelItem',
						'__experimentalTruncate',
						'__experimentalUnitControl',
						'__experimentalUseCustomUnits',
						'__experimentalVStack',
					],
					'@wordpress/block-editor': [
						'__experimentalColorGradientSettingsDropdown',
						'__experimentalLinkControl',
						'__experimentalUseBorderProps',
						'__experimentalUseColorProps',
						'__experimentalUseCustomUnits',
						'__experimentalUseMultipleOriginColorsAndGradients',
					],
				},
			],
			// ESLint 9 made caughtErrors: 'all' the default; the codebase's
			// empty `catch ( e )` sites are deliberate swallows with the
			// binding kept for debuggability. Everything else the rule
			// guards stays on.
			'no-unused-vars': [
				'error',
				// caughtErrors 'none': ESLint 9 flipped the default; empty
				// `catch ( e )` sites keep the binding for debuggability.
				// ignoreRestSiblings: the `{ key: omitted, ...rest }` idiom
				// is how a key is deliberately excluded from a payload.
				{ caughtErrors: 'none', ignoreRestSiblings: true },
			],
			// Reading a `const`/`let` above its declaration is a TEMPORAL DEAD
			// ZONE throw, not an undefined — and inside a React component it
			// takes the whole screen down with "Cannot access 'x' before
			// initialization": a minified name, no clue which variable, and a
			// blank dashboard. That shipped once (2026-09-18): a hero-panel
			// branch in HomeScreen read `isLicensed` nine lines above its
			// `const`, lint was green, and the dashboard white-screened for
			// Garrett. Function DECLARATIONS are genuinely hoisted and stay
			// allowed; variables and classes are not. No site is exempt
			// (#863): ESLint applies an `eslint-suppressions.json` beside
			// this file unasked, and its per-file COUNT lets a new TDZ read
			// ride on a removed one. There is none, and a recreated one fails
			// `npm run check:edition` — no manifest unit owns it and no
			// bin/dist-check.php shape names it.
			'no-use-before-define': [
				'error',
				{ functions: false, variables: true, classes: true },
			],
			'jsdoc/no-undefined-types': [
				'error',
				{ definedTypes: [ 'JSX', 'jQuery' ] },
			],
		},
	},
	// The edition payload's two keys have ONE reader each (#1043). `absent` is
	// the catalog — COPY, an entry only for a LABELED absent unit — and its
	// reader is screens/pro-row.js; `units` is PRESENCE and its reader is
	// hasUnit() in src/edition.js. A second reader of either is a second
	// place a label reaches the UI or a presence test is drawn from a
	// marketing field. Three objects, not two: a scoped `no-restricted-syntax`
	// REPLACES the option list for a file it matches, so a file matched by
	// two overlapping objects would keep only the later one's selectors — the
	// two readers therefore get their own objects carrying the OTHER key's
	// selector, and every other file carries both. wp-scripts 35's default
	// config sets no core `no-restricted-syntax` options for these files
	// (`npx eslint --print-config` shows only jsdoc/no-restricted-syntax), so
	// there is no base list to spread in front. The jest-only catalog fixture
	// (`*.test-only.js`) is outside the reader rule: it WRITES the payload a
	// test's screens read, and its `.units` is the MANIFEST's unit table, not
	// the payload's presence list; no bundle ever reaches it.
	{
		files: [ 'inc/onboarding/src/**/*.js' ],
		ignores: [
			'inc/onboarding/src/screens/pro-row.js',
			'inc/onboarding/src/edition.js',
			'inc/onboarding/src/**/*.test-only.js',
		],
		rules: {
			'no-restricted-syntax': [
				'error',
				{
					selector:
						'MemberExpression[property.name="absent"], MemberExpression[property.value="absent"], ObjectPattern > Property[key.name="absent"]',
					message:
						'edition().absent is the catalog (copy) with one reader, screens/pro-row.js; presence is hasUnit() from src/edition.js (#1043).',
				},
				{
					selector:
						'MemberExpression[property.name="units"], MemberExpression[property.value="units"], ObjectPattern > Property[key.name="units"]',
					message:
						'edition().units is the presence signal with one reader, hasUnit() in src/edition.js — call hasUnit( unitId ) (#1043).',
				},
			],
		},
	},
	{
		// The catalog's reader may not read presence.
		files: [ 'inc/onboarding/src/screens/pro-row.js' ],
		rules: {
			'no-restricted-syntax': [
				'error',
				{
					selector:
						'MemberExpression[property.name="units"], MemberExpression[property.value="units"], ObjectPattern > Property[key.name="units"]',
					message:
						'edition().units is the presence signal with one reader, hasUnit() in src/edition.js — call hasUnit( unitId ) (#1043).',
				},
			],
		},
	},
	{
		// The presence reader may not read the catalog.
		files: [ 'inc/onboarding/src/edition.js' ],
		rules: {
			'no-restricted-syntax': [
				'error',
				{
					selector:
						'MemberExpression[property.name="absent"], MemberExpression[property.value="absent"], ObjectPattern > Property[key.name="absent"]',
					message:
						'edition().absent is the catalog (copy) with one reader, screens/pro-row.js; presence is hasUnit() from src/edition.js (#1043).',
				},
			],
		},
	},
	{
		// Jest-only fixture modules (screens/catalog-fixture.test-only.js):
		// imported by tests before the screens, never by a bundle, and they
		// read this tree from disk (inc/edition.php, edition-manifest.json),
		// so they run under Node, not in a browser.
		files: [ '**/*.test-only.js' ],
		languageOptions: {
			globals: { ...globals.node },
		},
	},
	{
		// Non-built admin/front-end assets: served as-is (no webpack, no
		// babel), running with WordPress's bundled jQuery. The ES5 idiom
		// (var) stands — these files predate the build pipeline by design.
		files: [
			'inc/**/assets/**/*.js',
			'inc/extensions/loader/**/*-frontend.js',
		],
		languageOptions: {
			globals: {
				jQuery: 'readonly',
			},
		},
		rules: {
			'no-var': 'off',
		},
	},
];
