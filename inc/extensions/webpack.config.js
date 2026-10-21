/**
 * Extensions webpack config. Multiple entry points:
 * - index.js   -> the editor controls bundle (build/index.js + style-index.css)
 * - editor.js  -> editor-only styles (build/editor.css), enqueued in the editor
 * - *-frontend -> per-extension frontend behavior bundles, enqueued on the front end
 *
 * A *-frontend entry whose source is an ES module (a line that starts with
 * `import` or `export`: a unit's Interactivity API store) is built as a
 * script module, in the form the companion's blocks use (wp-scripts
 * --experimental-modules): build/<entry>.js, with a build/<entry>.asset.php
 * that says `'type' => 'module'` and names `@wordpress/interactivity` as a
 * dependency. A classic source stays a classic script. Which one an entry
 * is, is read from its source, never listed: a unit's clean-room import
 * turns its frontend.js into a store, and the build follows.
 *
 * withNotices() adds the build's THIRD-PARTY-NOTICES.txt writer (bin/third-party-notices.cjs).
 *
 * publicPath 'auto' (plus the window.__blocklaneProExtensionsBuildUrl the handler sets)
 * lets dynamically-imported chunks resolve. CopyPlugin ships the cover-term-image
 * preview asset into build/images so it survives rebuilds.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const fs = require( 'fs' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const { withNotices } = require( '../../bin/third-party-notices.cjs' );

/**
 * Keep only the entries whose source is actually present.
 *
 * One tree builds two plugins, and the free build runs in a staged copy from
 * which the generator has already deleted every unit that edition does not
 * carry. So "does the file exist" is exactly the right question here: in pro
 * every entry resolves, and in a free stage the Pro-only ones are gone. The
 * alternative — teaching this config to read the edition manifest — would put
 * a second copy of the edition rules in a second language.
 *
 * @param {Object} entries Webpack entry map, name => absolute source path.
 * @return {Object} The same map, minus entries whose source is absent.
 */
const present = ( entries ) =>
	Object.fromEntries(
		Object.entries( entries ).filter( ( [ , file ] ) =>
			fs.existsSync( file )
		)
	);

// With --experimental-modules wp-scripts exports [ script, module ] configs.
const [ scriptConfig, moduleConfig ] = Array.isArray( defaultConfig )
	? defaultConfig
	: [ defaultConfig, null ];

/**
 * Whether a source is an ES module: some line starts with `import` or
 * `export`. A classic script (an IIFE) never does.
 *
 * @param {string} file Absolute source path.
 * @return {boolean} True for a module.
 */
const isModule = ( file ) =>
	/^\s*(?:import|export)[\s{*]/m.test( fs.readFileSync( file, 'utf8' ) );

const entries = present( {
	index: path.resolve( __dirname, 'src', 'index.js' ),
	editor: path.resolve( __dirname, 'src', 'editor.js' ),
	'animation-frontend': path.resolve(
		__dirname,
		'src',
		'controls',
		'animation',
		'frontend.js'
	),
	'advanced-group-frontend': path.resolve(
		__dirname,
		'src',
		'controls',
		'advanced-group',
		'frontend.js'
	),
} );
const pick = ( wantModule ) =>
	Object.fromEntries(
		Object.entries( entries ).filter(
			( [ name, file ] ) =>
				name.endsWith( '-frontend' ) && isModule( file ) === wantModule
		)
	);
const modules = pick( true );
if ( Object.keys( modules ).length && ! moduleConfig ) {
	throw new Error(
		'inc/extensions has a store module (a *-frontend source that imports): build it with wp-scripts --experimental-modules'
	);
}

const classic = {
	...scriptConfig,
	entry: Object.fromEntries(
		Object.entries( entries ).filter( ( [ name ] ) => ! modules[ name ] )
	),
	output: {
		...scriptConfig.output,
		path: path.resolve( __dirname, 'build' ),
		publicPath: 'auto',
	},
	plugins: [
		...( scriptConfig.plugins || [] ),
		// Same reasoning as the entries above: cover-term-image is a Pro unit,
		// and its preview asset is simply not in a free stage.
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve(
						__dirname,
						'src',
						'controls',
						'cover-term-image',
						'preview.webp'
					),
					to: path.resolve(
						__dirname,
						'build',
						'cover-term-image',
						'preview.webp'
					),
					noErrorOnMissing: true,
				},
			],
		} ),
	],
};

module.exports = withNotices(
	Object.keys( modules ).length
		? [
				classic,
				{
					...moduleConfig,
					entry: modules,
					output: {
						...moduleConfig.output,
						path: path.resolve( __dirname, 'build' ),
					},
				},
			]
		: classic
);
