/**
 * THIRD-PARTY-NOTICES.txt, written by the build beside every bundle.
 *
 * wp-scripts bundles some packages instead of loading them from WordPress
 * (@wordpress/dataviews and @wordpress/icons among them, which pull in MIT
 * code such as @ariakit/react and clsx), and its Terser config keeps no
 * license comment. A hand-kept credits list cannot know what webpack put in a
 * bundle; this plugin can, because it reads the module graph of the
 * compilation that wrote it. For every output directory it writes one
 * THIRD-PARTY-NOTICES.txt naming:
 *
 *   - every emitted .js/.css bundle with the first 16 hex of its SHA-256 and
 *     the npm packages webpack put in it — so bin/dist-check.php can prove the
 *     file was written for THESE bytes, and that every package a bundle names
 *     has its license below. A stylesheet's packages include the ones it
 *     pulled in by @import: postcss-import, part of wp-scripts' preset, inlines an
 *     @import and reports the file through the loader's addDependency, so the
 *     imported stylesheet is never a module of its own and the module walk
 *     alone cannot see it (#1841). Its record is the importing module's build
 *     dependencies, read with Module#addCacheDependencies — the API webpack's
 *     own Compilation uses to collect them;
 *   - every first-party source whose header says "Portions of this file are
 *     derived from …", with the bundle it went into, because the minifier
 *     strips that header from the shipped copy;
 *   - every package, with its version and license from its own package.json
 *     (npm ci installs exactly the lockfile's versions) and the text of its
 *     own license file. A package with no license file gets the license's
 *     standard terms if this file knows them (MIT); any other such package
 *     fails the build, so a human decides rather than the build guessing.
 *
 * One output directory can be written by two compilers (--experimental-modules
 * builds a script and a module config into the same directory), so the
 * results are collected per directory across compilers and the file is
 * rewritten as each compiler finishes: the last one writes the union.
 *
 * Wired into every webpack build: the package's root webpack.config.js (the
 * config wp-scripts uses for every `--webpack-src-dir` build) and
 * inc/extensions/webpack.config.js. Build tooling: bin/ never ships.
 *
 * @package blocklane_pro
 */

const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );
const { Compilation } = require( 'webpack' );

const NAME = 'BlocklaneThirdPartyNotices';
const FILE = 'THIRD-PARTY-NOTICES.txt';

/* The standard terms of a license, for a package that ships no license file. */
const STANDARD_TERMS = {
	MIT: [
		'Permission is hereby granted, free of charge, to any person obtaining a copy',
		'of this software and associated documentation files (the "Software"), to deal',
		'in the Software without restriction, including without limitation the rights',
		'to use, copy, modify, merge, publish, distribute, sublicense, and/or sell',
		'copies of the Software, and to permit persons to whom the Software is',
		'furnished to do so, subject to the following conditions:',
		'',
		'The above copyright notice and this permission notice shall be included in all',
		'copies or substantial portions of the Software.',
		'',
		'THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR',
		'IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,',
		'FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE',
		'AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER',
		'LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,',
		'OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE',
		'SOFTWARE.',
	].join( '\n' ),
};

/* output directory => { bundles: Map<file, {hash, packages:Set}>, packages: Map<key, info>, derived: Map<file, {notice, bundles:Set}> } */
const byOutput = new Map();

/* An emitted file's name as it sits on disk: wp-scripts names chunks
   "169.js?ver=<hash>" and mini-css-extract names some "./style-index.css". */
const onDisk = ( name ) => name.split( '?' )[ 0 ].replace( /^\.\//, '' );

const LICENSE_FILE = /^(licen[cs]e|copying)([-.][\w.-]*)?$/i;
const STYLESHEET = /\.(css|scss|sass|less)$/i;
const DERIVED = /Portions of this file are derived from ([^\n]*)\n[ \t]*\*?[ \t]*([^\n]*)/;

/**
 * The installed package a module path belongs to, or null for first-party code.
 *
 * @param {string} file Absolute module path (query stripped).
 * @return {?{root: string, name: string}} The package root and name.
 */
function packageOf( file ) {
	const marker = `${ path.sep }node_modules${ path.sep }`;
	const at = file.lastIndexOf( marker );
	if ( at < 0 ) {
		return null;
	}
	const rest = file.slice( at + marker.length ).split( path.sep );
	const name = rest[ 0 ].startsWith( '@' ) ? `${ rest[ 0 ] }/${ rest[ 1 ] }` : rest[ 0 ];
	return { root: file.slice( 0, at + marker.length ) + name, name };
}

/**
 * Name, version, license and license text of an installed package.
 *
 * @param {string} root Package root directory.
 * @return {{name: string, version: string, license: string, text: string, from: string}} The notice parts.
 */
function describe( root ) {
	const pkg = JSON.parse( fs.readFileSync( path.join( root, 'package.json' ), 'utf8' ) );
	let license = pkg.license;
	if ( ! license && Array.isArray( pkg.licenses ) ) {
		license = pkg.licenses.map( ( l ) => l.type || l ).join( ' OR ' );
	}
	license = typeof license === 'object' && license ? license.type : String( license || 'UNKNOWN' );
	const files = fs
		.readdirSync( root )
		.filter( ( f ) => LICENSE_FILE.test( f ) && fs.statSync( path.join( root, f ) ).isFile() )
		.sort();
	if ( files.length ) {
		return {
			name: pkg.name,
			version: pkg.version,
			license,
			text: files.map( ( f ) => fs.readFileSync( path.join( root, f ), 'utf8' ).trim() ).join( '\n\n' ),
			from: `its ${ files.join( ', ' ) }`,
		};
	}
	if ( ! STANDARD_TERMS[ license ] ) {
		throw new Error(
			`${ NAME }: ${ pkg.name } ${ pkg.version } is bundled, ships no license file, and declares "${ license }", whose terms this plugin does not carry — add them to STANDARD_TERMS after checking the package, or stop bundling it.`
		);
	}
	const author = typeof pkg.author === 'object' && pkg.author ? pkg.author.name : pkg.author;
	return {
		name: pkg.name,
		version: pkg.version,
		license,
		text: `${ author ? `Copyright (c) ${ author }\n\n` : '' }${ STANDARD_TERMS[ license ] }`,
		from: `no license file; its package.json declares "${ license }"${ author ? ` and names its author` : '' }, and these are that license's standard terms`,
	};
}

/**
 * Render one output directory's notices.
 *
 * @param {Object} entry The collected state of one output directory.
 * @return {string} The file's text.
 */
function render( entry ) {
	const lines = [
		'Third-party software in this directory\'s build output',
		'',
		'Written by the build (bin/third-party-notices.cjs) from the modules webpack',
		'bundled; never edited by hand. bin/dist-check.php reads it back: each bundle',
		'below must match the shipped file\'s SHA-256, and every package a bundle names',
		'must have its license text in this file.',
		'',
		'Bundles (file, SHA-256 prefix, the npm packages in it):',
	];
	for ( const [ file, bundle ] of [ ...entry.bundles ].sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) ) ) {
		const names = [ ...bundle.packages ].sort();
		lines.push( `  ${ file } ${ bundle.hash }: ${ names.length ? names.join( ', ' ) : '(no third-party package)' }` );
	}
	if ( entry.derived.size ) {
		lines.push( '', 'First-party sources derived from other work (their header notices; the minifier strips them from the bundle):' );
		for ( const [ file, derived ] of [ ...entry.derived ].sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) ) ) {
			lines.push( `  ${ file } -> ${ [ ...derived.bundles ].sort().join( ', ' ) }:`, `    ${ derived.notice }` );
		}
	}
	lines.push( '', 'Packages:' );
	const seen = new Map();
	for ( const [ key, info ] of [ ...entry.packages ].sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) ) ) {
		lines.push( '', `-- ${ key } (${ info.license }) --`, `License text: ${ info.from }.`, '' );
		if ( seen.has( info.text ) ) {
			lines.push( `(The same text as ${ seen.get( info.text ) } above.)` );
		} else {
			seen.set( info.text, key );
			lines.push( info.text );
		}
	}
	return lines.join( '\n' ) + '\n';
}

class ThirdPartyNoticesPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap( NAME, ( compilation ) => {
			compilation.hooks.processAssets.tap( { name: NAME, stage: Compilation.PROCESS_ASSETS_STAGE_REPORT }, () => {
				const out = compiler.options.output.path;
				if ( ! byOutput.has( out ) ) {
					byOutput.set( out, { bundles: new Map(), packages: new Map(), derived: new Map() } );
				}
				const entry = byOutput.get( out );
				const context = compiler.options.context || process.cwd();
				const sources = new Map();
				// The stylesheets each stylesheet module's build read (its own
				// file and every @import postcss-import or sass inlined into it).
				const readBy = new Map();
				for ( const m of compilation.modules ) {
					const resource = typeof m.resource === 'string' ? m.resource.split( '?' )[ 0 ] : '';
					if ( ! STYLESHEET.test( resource ) ) {
						continue;
					}
					const read = readBy.get( resource ) || new Set();
					const drop = { addAll() {} };
					m.addCacheDependencies(
						{
							addAll: ( deps ) => {
								for ( const dep of deps ) {
									if ( STYLESHEET.test( dep ) ) {
										read.add( dep );
									}
								}
							},
						},
						drop,
						drop,
						drop
					);
					readBy.set( resource, read );
				}
				const credit = ( owner, files ) => {
					const info = describe( owner.root );
					const key = `${ info.name } ${ info.version }`;
					entry.packages.set( key, info );
					files.forEach( ( f ) => entry.bundles.get( f )?.packages.add( key ) );
				};
				// A CSS module's bytes land in the chunk's .css files, every other
				// module's in its .js files.
				const visit = ( module, chunkFiles ) => {
					if ( module.modules ) {
						module.modules.forEach( ( inner ) => visit( inner, chunkFiles ) );
						return;
					}
					const isCss = String( module.type || '' ).startsWith( 'css' );
					const files = chunkFiles.filter( ( f ) => f.endsWith( isCss ? '.css' : '.js' ) );
					const named = typeof module.nameForCondition === 'function' ? module.nameForCondition() : module.resource;
					if ( ! named || ! path.isAbsolute( named ) ) {
						return;
					}
					const file = named.split( '?' )[ 0 ];
					if ( isCss ) {
						for ( const dep of readBy.get( file ) || [] ) {
							const imported = packageOf( dep );
							if ( imported ) {
								credit( imported, files );
							}
						}
					}
					const owner = packageOf( file );
					if ( owner ) {
						credit( owner, files );
						return;
					}
					if ( ! sources.has( file ) ) {
						sources.set( file, fs.existsSync( file ) ? fs.readFileSync( file, 'utf8' ) : '' );
					}
					const match = DERIVED.exec( sources.get( file ) );
					if ( match ) {
						const rel = path.relative( context, file ).split( path.sep ).join( '/' );
						const derived = entry.derived.get( rel ) || { notice: `Portions of this file are derived from ${ match[ 1 ].trim() } ${ match[ 2 ].trim() }`.trim(), bundles: new Set() };
						files.forEach( ( f ) => derived.bundles.add( f ) );
						entry.derived.set( rel, derived );
					}
				};
				for ( const asset of compilation.getAssets() ) {
					const name = onDisk( asset.name );
					if ( /\.(js|css)$/.test( name ) ) {
						entry.bundles.set( name, {
							hash: crypto.createHash( 'sha256' ).update( asset.source.buffer() ).digest( 'hex' ).slice( 0, 16 ),
							packages: new Set(),
						} );
					}
				}
				for ( const chunk of compilation.chunks ) {
					const chunkFiles = [ ...chunk.files ].map( onDisk ).filter( ( f ) => /\.(js|css)$/.test( f ) );
					for ( const module of compilation.chunkGraph.getChunkModulesIterable( chunk ) ) {
						visit( module, chunkFiles );
					}
				}
			} );
		} );
		compiler.hooks.done.tap( NAME, () => {
			const out = compiler.options.output.path;
			if ( byOutput.has( out ) ) {
				fs.mkdirSync( out, { recursive: true } );
				fs.writeFileSync( path.join( out, FILE ), render( byOutput.get( out ) ) );
			}
		} );
	}
}

/**
 * Add the plugin to a webpack config, or to each config of an array, and
 * give the build its one name (see below).
 *
 * @param {Object|Object[]} config A webpack config, or the array an
 *                                 --experimental-modules build exports.
 * @return {Object|Object[]} The same shape, each config carrying the plugin.
 */
function withNotices( config ) {
	if ( Array.isArray( config ) ) {
		return config.map( withNotices );
	}
	return {
		...config,
		// The one wrapper every build config goes through, so it also owns the
		// one name the build goes by, whatever package.json calls it: webpack
		// derives output.uniqueName (the chunk-loading global, and a seed of
		// every deterministic chunk id) from the package name, which is
		// "blocklane-pro" in the tree and "blocklane" in the published source,
		// so the same sources built under each name differed in every chunk and
		// the rebuilt source could never match the zip (spec 2026-10-06 BA-7,
		// `publish-free-source.sh --rebuild`).
		output: { ...( config.output || {} ), uniqueName: 'blocklane' },
		plugins: [ ...( config.plugins || [] ), new ThirdPartyNoticesPlugin() ],
	};
}

module.exports = { ThirdPartyNoticesPlugin, withNotices, FILE };
