<?php
/**
 * Loader for the bundled enshrined/svg-sanitize library.
 *
 * Third-party library — NOT our code. Bundled unmodified under its own license:
 *   enshrined/svg-sanitize  (c) Daryll Doyle and contributors
 *   GPL-2.0-or-later  —  https://github.com/darylldoyle/svg-sanitizer
 *   Version: 1.0.0 (2026-09-01). Vendored class files only; the upstream
 *   src/svg-scanner.php CLI tool is deliberately not shipped.
 *   License text: ./LICENSE
 *
 * We only consume its classes (via this autoloader) from our own wrapper; the
 * library files are never modified.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		$prefix = 'enshrined\\svgSanitize\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = __DIR__ . '/src/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
