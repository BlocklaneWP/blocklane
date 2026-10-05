/**
 * Extensions webpack config. Multiple entry points mirroring BlocklanePro Pro's:
 * - index.js   -> the editor controls bundle (build/index.js + style-index.css)
 * - editor.js  -> editor-only styles (build/editor.css), enqueued in the editor
 * - *-frontend -> per-extension frontend behavior bundles, enqueued on the front end
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

module.exports = withNotices( {
	...defaultConfig,
	entry: present( {
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
	} ),
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		publicPath: 'auto',
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
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
						'images',
						'preview.webp'
					),
					noErrorOnMissing: true,
				},
			],
		} ),
	],
} );
