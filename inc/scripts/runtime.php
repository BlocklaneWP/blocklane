<?php
/**
 * Custom Scripts — the content runtime.
 *
 * Scripts is content in the strongest sense: analytics, pixels and
 * verification meta the site owner pasted in, emitted on the front end from
 * one option. It emitted from a generated mu-plugin until 2026-08; now
 * Scripts::boot() reads the row and hooks wp_head / wp_body_open / wp_footer
 * directly. Self-gating (safe mode, admin, the master switch), and ungated by
 * license like every other content runtime.
 *
 * A function file, not a class file, because this is a `content` row in
 * Modules::content() — the row's `classes` entry preflights Scripts itself,
 * so by the time this runs the class is proven loaded.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\blocklane_pro\Scripts::boot();
