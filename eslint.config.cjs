/**
 * Project ESLint config (flat, wp-scripts 34 / ESLint 9).
 *
 * The @wordpress/* packages below are RUNTIME EXTERNALS: the
 * dependency-extraction webpack plugin maps every import onto the copy
 * WordPress ships, so none of them is (or should be) installed here.
 * wp-scripts 34's stricter import resolution flags them as unresolved /
 * extraneous without this exemption — `import/core-modules` satisfies both
 * rules while keeping them live for real packages. Locally installed
 * @wordpress packages that ARE bundled (icons, interface, dataviews) resolve
 * normally and are deliberately not listed.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );
const globals = require( 'globals' );

module.exports = [
	...defaultConfig,
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
			// allowed; variables and classes are not.
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
