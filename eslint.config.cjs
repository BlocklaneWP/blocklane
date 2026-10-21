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

// No editor or front-end source may make the browser fetch from another host
// on render (#1745: the video-modal inspector loaded img.youtube.com the
// moment a block was selected, with no consent and no disclosure). A remote
// URL as a literal or the head of a template in the src, srcSet, poster,
// href, xlinkHref or data of an element that fetches it — img, iframe,
// video, audio, source, image, use, link, script — and a remote url( … )
// in any JSX string. A link (<a href>) fetches nothing until clicked and
// stays allowed. Spread into EVERY scoped `no-restricted-syntax` list below:
// a scoped list REPLACES the options of an earlier match for the same file
// (see the onboarding objects' comment), so a list that left it out would
// switch it off for its files.
// esquery reads a regex up to its first `/`, escaped or not: the slashes of
// a URL are written \x2F.
const REMOTE_HEAD = '/^\\s*(?:https?:)?\\x2F\\x2F/i';
const REMOTE_URL = '/url\\(\\s*[\'"]?\\s*(?:https?:)?\\x2F\\x2F/i';
const FETCHING =
	'JSXOpeningElement[name.name=/^(img|iframe|video|audio|source|image|use|link|script)$/] > JSXAttribute[name.name=/^(src|srcSet|poster|href|xlinkHref|data)$/]';
const REMOTE_MEDIA = [
	{
		selector: [
			`${ FETCHING } > Literal[value=${ REMOTE_HEAD }]`,
			`${ FETCHING } > JSXExpressionContainer > Literal[value=${ REMOTE_HEAD }]`,
			`${ FETCHING } > JSXExpressionContainer > TemplateLiteral[quasis.0.value.raw=${ REMOTE_HEAD }]`,
		].join( ', ' ),
		message:
			'A remote URL in a media element makes every editor or visitor request another host on render, before any click (#1745). Load it behind an explicit action, or ship the asset.',
	},
	{
		selector: [
			`JSXAttribute Literal[value=${ REMOTE_URL }]`,
			`JSXAttribute TemplateElement[value.raw=${ REMOTE_URL }]`,
		].join( ', ' ),
		message:
			'A remote url( … ) in a JSX style makes the browser request another host on render (#1745).',
	},
];

// The dashboard's page shell has one door (admin-dashboard spec 2026-10-10
// §6 "Must be absent after C1"): components/BlocklanePage.js renders the
// page h1 through admin-ui's Page, and nothing else in the app may draw one
// — a second <h1> is a second header, the copy-a-shell shape C1 retired.
// The page's own file and its test (whose stand-in for core's Page draws
// the h1 core's does) are the exemption below.
const PAGE_H1 = {
	selector: [
		'JSXOpeningElement[name.name="h1"]',
		'CallExpression[callee.name="createElement"][arguments.0.value="h1"]',
	].join( ', ' ),
	message:
		'The page h1 is rendered by components/BlocklanePage.js alone (spec 2026-10-10 §6); render the screen through BlocklanePage, and use an h2 or lower inside it.',
};
// A plugin may not unlock core's private APIs; the bundled @wordpress/ui
// registers its own consent under its own module name, which dist-check
// pins (Amendment AF). Neither a lock-unlock module nor the opt-in may be
// imported by the app's own code.
const PRIVATE_APIS = {
	selector: [
		'ImportDeclaration[source.value=/(^|\\x2F)(lock-unlock|private-apis)(\\.m?js)?$/]',
		'ImportDeclaration[source.value="@wordpress/private-apis"]',
		// `lock` and `unlock` are also icon names; they can only come
		// from a lock-unlock module, which the source rule above names.
		'ImportSpecifier[imported.name=/^(privateApis|__dangerousOptInToUnstableAPIsOnlyForCoreModules)$/]',
	].join( ', ' ),
	message:
		'No privateApis or unlock in the dashboard (spec 2026-10-10 §3, Amendment AF): use public components only.',
};

// Settings retired the Advanced and Extensions screens (the C-settings spec
// (2026-10-12) P8). Two rules of their own, so they never replace a file's
// `no-restricted-syntax` list (a later object's list REPLACES an earlier
// one's): core's no-restricted-syntax registered again under the
// `blocklane/` prefix, each name with its own selectors and its own scope.
// eslint-disable-next-line import/no-extraneous-dependencies -- ESLint is the runtime this config is loaded by (wp-scripts' own dependency), never a bundle import.
const { builtinRules } = require( 'eslint/use-at-your-own-risk' );
const RETIRED_PLACES = [
	{
		selector:
			'Property[key.name="screen"][value.value=/^(advanced|extensions)$/]',
		message:
			'The Advanced and Extensions screens are retired: link to Settings with settingsHref( <row> ). Only components/legacy-routes.js reads an old address (the C-settings spec P8).',
	},
	{
		selector: [
			'Property[key.name=/^(adv|ext|advtab|exttab)$/]',
			'Property[key.value=/^(adv|ext|advtab|exttab)$/]',
			'CallExpression > Literal[value=/^(adv|ext|advtab|exttab)$/]',
		].join( ', ' ),
		message:
			"adv, ext, advtab and exttab are the retired screens' params: Settings selects a row with `item` and derives its tab. Only components/legacy-routes.js reads them (the C-settings spec P8).",
	},
];
const SETTINGS_HREF = {
	selector:
		'CallExpression[callee.name=/^(routeUrl|buildRouteUrl)$/] > ObjectExpression > Property[key.name="screen"][value.value="settings"]',
	message:
		'A link into Settings is built by settingsHref( item ) (screens/settings/href.js), the one builder: it never writes a tab (the C-settings spec P8, F11).',
};
// One save door (spec 2026-10-17-pro-downgrade-inert-attributes §3.4, §8
// V9): inc/extensions/src/components/save-contributions.js is the only
// `blocks.getSaveContent.extraProps` filter, so the hook's name may not be
// spelled anywhere else, as a string or a template. A second filter is a
// second place saved markup is written, the shape the free edition cannot
// carry. Spread into EVERY scoped list below, beside REMOTE_MEDIA.
const SAVE_DOOR = [
	{
		selector:
			'Literal[value="blocks.getSaveContent.extraProps"], TemplateElement[value.raw="blocks.getSaveContent.extraProps"]',
		message:
			'blocks.getSaveContent.extraProps has one filter, inc/extensions/src/components/save-contributions.js: add a contribution there.',
	},
];
// The files that may spell it. The module; the vector test, which runs the
// hook (it registers nothing); and, UNTIL the module's import commit removes
// their filters, the three controls that hold today's (the clean-room run
// cr-2026-10-20-save-contributions writes the module). save-contributions-
// lint.test.js pins this list, so it can only shrink.
const SAVE_DOOR_FILES = [
	'inc/extensions/src/components/save-contributions.js',
	'inc/extensions/src/components/save-contributions-vectors.test.js',
	'inc/extensions/src/controls/paragraph-hover-decoration/index.js',
	'inc/extensions/src/controls/text-wrap/index.js',
	'inc/extensions/src/controls/advanced-tabs/index.js',
];

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
				'@wordpress/a11y',
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
				'@wordpress/escape-html',
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
				'@wordpress/server-side-render',
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
	{
		// Every editor and front-end source outside the onboarding app (whose
		// three objects below carry the same list beside their own).
		files: [ 'inc/**/src/**/*.js', 'inc/**/src/**/*.jsx' ],
		ignores: [ 'inc/onboarding/src/**' ],
		rules: {
			'no-restricted-syntax': [ 'error', ...REMOTE_MEDIA, ...SAVE_DOOR ],
		},
	},
	{
		// The save door's own files: REMOTE_MEDIA alone (a later scoped list
		// replaces the one above for these files).
		files: SAVE_DOOR_FILES,
		rules: {
			'no-restricted-syntax': [ 'error', ...REMOTE_MEDIA ],
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
			'inc/onboarding/src/components/BlocklanePage.js',
			'inc/onboarding/src/components/BlocklanePage.test.js',
		],
		rules: {
			'no-restricted-syntax': [
				'error',
				...REMOTE_MEDIA,
				PAGE_H1,
				PRIVATE_APIS,
				...SAVE_DOOR,
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
				...REMOTE_MEDIA,
				PAGE_H1,
				PRIVATE_APIS,
				...SAVE_DOOR,
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
				...REMOTE_MEDIA,
				PAGE_H1,
				PRIVATE_APIS,
				...SAVE_DOOR,
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
		// The page's own file and its test: everything the app's other files
		// carry except PAGE_H1 (the page is where the h1 comes from).
		files: [
			'inc/onboarding/src/components/BlocklanePage.js',
			'inc/onboarding/src/components/BlocklanePage.test.js',
		],
		rules: {
			'no-restricted-syntax': [
				'error',
				...REMOTE_MEDIA,
				PRIVATE_APIS,
				...SAVE_DOOR,
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
		// admin-ui's Breadcrumbs imports @wordpress/route, which the
		// dependency-extraction plugin turns into the `wp-route` script handle
		// WordPress 7.1 does not register (the whole app would fail to
		// enqueue; bin/asset-deps-check.php is the named exit), needs a
		// TanStack router the dashboard does not have, and renders its own h1
		// (spec 2026-10-10 Amendment AF). The breadcrumb is @wordpress/ui's.
		files: [ 'inc/onboarding/src/**/*.js' ],
		rules: {
			'no-restricted-imports': [
				'error',
				{
					paths: [
						{
							name: '@wordpress/admin-ui',
							importNames: [ 'Breadcrumbs' ],
							message:
								'admin-ui Breadcrumbs pulls the unregistered `wp-route` handle and a router dependency (Amendment AF); pass a @wordpress/ui Breadcrumb to BlocklanePage.',
						},
					],
				},
			],
		},
	},
	{
		plugins: {
			blocklane: {
				rules: {
					'retired-places': builtinRules.get(
						'no-restricted-syntax'
					),
					'settings-href': builtinRules.get( 'no-restricted-syntax' ),
				},
			},
		},
	},
	{
		files: [ 'inc/onboarding/src/**/*.js' ],
		ignores: [
			'inc/onboarding/src/components/legacy-routes.js',
			'inc/onboarding/src/**/*.test.js',
			'inc/onboarding/src/**/*.test-only.js',
		],
		rules: {
			'blocklane/retired-places': [ 'error', ...RETIRED_PLACES ],
		},
	},
	{
		files: [ 'inc/onboarding/src/**/*.js' ],
		ignores: [
			'inc/onboarding/src/screens/settings/href.js',
			'inc/onboarding/src/**/*.test.js',
			'inc/onboarding/src/**/*.test-only.js',
		],
		rules: {
			'blocklane/settings-href': [ 'error', SETTINGS_HREF ],
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
