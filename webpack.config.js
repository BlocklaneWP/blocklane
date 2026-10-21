/**
 * The webpack config every `wp-scripts build --webpack-src-dir=…` script in
 * package.json runs: wp-scripts' own default (it reads the --webpack-src-dir,
 * --webpack-copy-php and --experimental-modules flags itself), plus the
 * notices plugin, which writes THIRD-PARTY-NOTICES.txt beside each bundle
 * from the modules webpack actually bundled (bin/third-party-notices.cjs).
 * wp-scripts uses a webpack.config.js in the package root in place of its
 * default; inc/extensions/webpack.config.js, the one --config build, adds
 * the plugin itself.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { withNotices } = require( './bin/third-party-notices.cjs' );

module.exports = withNotices( defaultConfig );
