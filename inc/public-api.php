<?php
/**
 * Blocklane's public API, version 1: the functions a plugin outside this
 * one may call. Each is a one-line forward to the internal that owns the
 * answer, so a consumer never reaches an internal class and an internal
 * can change behind the same name.
 *
 * A function file in log.php's shape: no hooks, no state. inc/bootstrap.php
 * requires it right after log.php, under the coexistence guard, so only the
 * running edition declares it; it is a core path, so both editions carry it
 * (the wordpress.org build included: it is data and pure functions, with no
 * UI).
 *
 * BLOCKLANE_PRO_API is a MAJOR version: it changes only on a breaking change.
 * Additions (a new function) do not change it; a consumer checks
 * function_exists() for anything added after the version it was written
 * against.
 *
 * Version 1 holds the pattern taxonomy (spec 2026-10-17-pattern-catalog-
 * browse §4) and its families (spec 2026-10-17-pattern-families §3.3), the
 * header, overlay and panel pairs (spec 2026-10-17-header-overlay-pairs
 * §3.7; spec "Mega Menu pairs" §3.6), and the facts and toolkit doors a site plugin builds on (the
 * companion plugin spec of 2026-10-16, §3.3): tier provenance, setup,
 * the unit catalog, the extension schema, the brand glyph, block suite
 * registration and the inline asset doors. Scroll Motion's markup door,
 * blocklane_pro_scroll_motion_decorate(), lives with that extension (Pro
 * only), marked @blocklane-api 1.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLOCKLANE_PRO_API', 1 );

/**
 * What a consumer may read besides the functions, as data: the constants,
 * the hooks it may add to or fire, the post meta keys, the timing it may
 * rely on, and the pattern registry properties Blocklane validates. A seam
 * check reads this list; nothing here grants anything.
 *
 * @since API 1
 */
const BLOCKLANE_PRO_API_SURFACE = array(
	'constants' => array( 'BLOCKLANE_PRO_API', 'BLOCKLANE_PRO_VERSION', 'BLOCKLANE_PRO_SAFE_MODE', 'BLOCKLANE_PRO_POPUPS_CPT' ),
	// Filters a consumer may add to, and the action a device preview fires
	// (forms simulate their submission when it has fired: forms.md TM-D).
	// blocklane_pro_ext_reveal_doors: a page that prints its script modules
	// its own way names them under `printed`, so the reveal failsafe
	// believes them (the companion's live device; exemplar D2 (b), D6).
	'hooks'     => array( 'blocklane_pro_transparent_header_cache', 'blocklane_pro_hold_setup_patterns', 'blocklane_pro_pattern_preview_interactive', 'blocklane_pro_ext_reveal_doors' ),
	'meta'      => array( 'blocklane_pro_transparent_header', 'blocklane_pro_solid_header' ),
	'timing'    => array( 'site_lock' => 'template_redirect:0' ),
	'registry'  => array( 'enhances' ),
);

/**
 * A registered pattern's Blocklane tier: 'free', 'pro', or null for a
 * pattern Blocklane did not register (a theme's, another plugin's). Read
 * from the registrar's own record, never from the pattern's properties or a
 * filter, so no other code can claim a tier.
 *
 * @since API 1
 * @param string $name A pattern name.
 */
function blocklane_pro_api_pattern_tier( string $name ): ?string {
	return \blocklane_pro\Pattern_Tiers::of( $name );
}

/**
 * Whether parsed blocks need site setup before they work (a query of a
 * content type the site may not have).
 *
 * @since API 1
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 */
function blocklane_pro_api_pattern_needs_setup( array $blocks ): bool {
	return \blocklane_pro\Pattern_Tiers::needs_setup( $blocks );
}

/**
 * Every extension's block attributes, keyed by the unit that owns it: the
 * generated schema both editions register (identical bytes in each), in
 * its compact form. Empty when the file cannot be read.
 *
 * @since API 1
 * @return array<string, mixed>
 */
function blocklane_pro_api_extension_schema(): array {
	static $schema = null;
	if ( null === $schema ) {
		$file   = BLOCKLANE_PRO_PATH . '/inc/extensions/attribute-schema.php';
		$read   = is_readable( $file ) ? require $file : null;
		$schema = is_array( $read ) ? $read : array();
	}
	return $schema;
}

/**
 * The animation types Animation Designer renders: the values of its
 * `animationType` attribute that do anything. Any other value (a
 * hand-written "fade-up", say) renders nothing, so a consumer counting
 * effects counts only these. Read from the extension's own list
 * (blocklane_pro_animation_get_allowed_animations()), never a copy; empty
 * where the edition does not carry the extension.
 *
 * @since API 1
 * @return list<string>
 */
function blocklane_pro_api_animation_types(): array {
	if ( ! function_exists( 'blocklane_pro_animation_get_allowed_animations' ) ) {
		$file = BLOCKLANE_PRO_PATH . '/inc/extensions/loader/animation/animation-constants.php';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		require_once $file;
	}
	return array_map( 'strval', (array) blocklane_pro_animation_get_allowed_animations() );
}

/**
 * This request renders a document that is not a page of the site (a pattern
 * preview): Blocklane prints none of its SEO head output for the rest of it,
 * namely the verification and meta tags, the archive canonical, and the JSON-LD
 * (the site's WebSite and Organization graph, an entry's, breadcrumbs,
 * reviews). Call it before wp_head. It cannot be undone within the request,
 * and a second call changes nothing. It changes no robots directive (the
 * caller sets its own) and nothing outside the head output.
 *
 * @since API 1
 */
function blocklane_pro_api_quiet_head(): void {
	if ( 0 === did_action( 'blocklane_pro_quiet_head' ) ) {
		do_action( 'blocklane_pro_quiet_head' );
	}
}

/**
 * The Blocklane mark as a data URI (an SVG), for a consumer's own UI.
 *
 * @since API 1
 */
function blocklane_pro_api_brand_glyph(): string {
	return \blocklane_pro\Block_Branding::GLYPH;
}

/**
 * Register a plugin's built blocks the way Blocklane registers its own: each
 * block's built block.json under $build_dir, its stylesheets versioned by
 * file time, a name already registered skipped, and a missing build listed
 * in one admin notice that names $label, $consequence and $rebuild. Call at
 * init.
 *
 * @since API 1
 * @param string        $feature     A key of the caller's own, for its notice.
 * @param string        $block_ns    The blocks' namespace, without the slash.
 * @param string        $build_dir   The absolute path of the build directory.
 * @param list<string>  $blocks      Block slugs (directory names under build/).
 * @param string        $rebuild     The command that rebuilds them.
 * @param string        $label       The caller's name, translated.
 * @param string        $consequence What a missing build costs, one translated sentence.
 * @return list<string> The slugs whose build was missing.
 */
function blocklane_pro_api_register_block_suite( string $feature, string $block_ns, string $build_dir, array $blocks, string $rebuild, string $label, string $consequence ): array {
	return array_values( \blocklane_pro\Block_Suite::register( $feature, $block_ns, $build_dir, $blocks, $rebuild, $label, $consequence ) );
}

/**
 * The marker a block suite's shell template prints where its inner blocks go.
 *
 * @since API 1
 */
function blocklane_pro_api_block_slot(): string {
	return \blocklane_pro\Block_Suite::SLOT;
}

/**
 * Queue CSS for the document's next style print, under a handle of the
 * caller's own. Refused CSS (empty, a closing tag, a handle taken by a
 * stylesheet, or too late) is a WP_Error.
 *
 * @since API 1
 * @param string $handle The style handle.
 * @param string $css    The CSS, without a style tag.
 * @return true|\WP_Error
 */
function blocklane_pro_api_inline_style( string $handle, string $css ): bool|\WP_Error {
	return \blocklane_pro\Inline_Asset::style( $handle, $css );
}

/**
 * Print an inline script tag now, through core's printer (its attribute
 * filter runs). A body core refuses is a WP_Error.
 *
 * @since API 1
 * @param string                     $js    The script body.
 * @param array<string, string|bool> $attrs Tag attributes.
 * @return true|\WP_Error
 */
function blocklane_pro_api_print_inline_script( string $js, array $attrs = array() ): bool|\WP_Error {
	return \blocklane_pro\Inline_Asset::print_script( $js, $attrs );
}

/**
 * Every labeled unit of either edition: unit id => { kind, label, blurb,
 * editions }. Strings only (Edition::catalog()). A feature of the pattern
 * taxonomy that is a catalog unit takes its label from here.
 *
 * @since API 1
 * @return array<string, array{kind: string, label: string, blurb: string, editions: list<string>}>
 */
function blocklane_pro_api_catalog(): array {
	return \blocklane_pro\Edition::catalog();
}

/**
 * The pattern taxonomy: the data (groups and entries in page order, type
 * terms, descriptors, features, parts, retired slugs), the translated
 * labels, and the normalizer's tables for a browser twin. Call after init.
 *
 * @since API 1
 * @return array{data: array<string, mixed>, labels: array<string, array<string, string>>, normalizer: array<string, mixed>}|\WP_Error The taxonomy, or why it cannot be read.
 */
function blocklane_pro_api_pattern_taxonomy(): array|\WP_Error {
	$tax = \blocklane_pro\Pattern_Taxonomy::load();
	if ( $tax instanceof \WP_Error ) {
		return $tax;
	}
	$labels = \blocklane_pro\Pattern_Taxonomy::labels();
	if ( $labels instanceof \WP_Error ) {
		return $labels;
	}
	return array(
		'data'       => $tax['data'],
		'labels'     => $labels,
		'normalizer' => $tax['tables'],
	);
}

/**
 * Classify a registered pattern (a registry entry): its group, entry, slot,
 * rank, lead, area, source and descriptors.
 *
 * @since API 1
 * @param array<string, mixed> $pattern A registry entry.
 * @return array<string, mixed>|\WP_Error blocklane_pattern_ambiguous, blocklane_pattern_unclassified, or the taxonomy's load failure.
 */
function blocklane_pro_api_classify_pattern( array $pattern ): array|\WP_Error {
	return \blocklane_pro\Pattern_Taxonomy::classify( $pattern );
}

/**
 * Normalize text the way the taxonomy reads it: { phrase, term, kind }.
 * `term` and `kind` are null for text that is no vocabulary term.
 *
 * @since API 1
 * @param string $text Any text.
 * @return array{phrase: string, term: ?string, kind: ?string}|\WP_Error The normalized text, or the taxonomy's load failure.
 */
function blocklane_pro_api_normalize_term( string $text ): array|\WP_Error {
	return \blocklane_pro\Pattern_Taxonomy::normalize( $text );
}

/**
 * Registered pattern names in page order: { order, refused }.
 *
 * @since API 1
 * @param list<string> $names Pattern names, in pick order.
 * @return array<string, mixed>|\WP_Error blocklane_assembly_empty, blocklane_assembly_too_many, or the taxonomy's load failure.
 */
function blocklane_pro_api_assembly_order( array $names ): array|\WP_Error {
	return \blocklane_pro\Pattern_Taxonomy::assembly_order( $names );
}

/**
 * The template-part area a pattern is for: 'header', 'footer' or
 * 'navigation-overlay' from its blockTypes, or '' for any other. The
 * classifier's own structural reader, so a consumer and the taxonomy never
 * disagree.
 *
 * @since API 1
 * @param array<string, mixed> $pattern A registry entry.
 */
function blocklane_pro_api_pattern_area( array $pattern ): string {
	return \blocklane_pro\Pattern_Taxonomy::area_of( $pattern );
}

/**
 * Whether a pattern is a full page (a page's whole content or a whole
 * template): exactly the patterns the taxonomy puts in its pages group.
 *
 * @since API 1
 * @param array<string, mixed> $pattern A registry entry.
 */
function blocklane_pro_api_is_full_page( array $pattern ): bool {
	return \blocklane_pro\Pattern_Taxonomy::is_full_page( $pattern );
}

/**
 * Every surface form (label, lead, type term, synonym) of a classification's
 * entry and descriptors and of the given features, as authored: the words a
 * search expands a pattern's text with, so a synonym finds it without a
 * synonym table in the browser.
 *
 * @since API 1
 * @param array<string, mixed> $classification A blocklane_pro_api_classify_pattern() result.
 * @param list<string>         $features       Feature ids the pattern uses.
 * @return list<string>|\WP_Error The forms, or the taxonomy's load failure.
 */
function blocklane_pro_api_search_forms( array $classification, array $features = array() ): array|\WP_Error {
	return \blocklane_pro\Pattern_Taxonomy::search_forms( $classification, $features );
}

/**
 * A pattern's header, overlay and panel pairs (spec 2026-10-17-header-
 * overlay-pairs §3.7; spec "Mega Menu pairs" §3.6): its menu toggle
 * ({ always, below } or null), the overlay part that toggle opens
 * ({ slug, state, pattern } or null; state theme, inline, missing, foreign
 * or default) and the panel part each Mega Menu item opens, in document
 * order (a list of { slug, state, pattern }; state theme, supplied,
 * inline, missing or foreign). Read from the pattern's tree and core's
 * file door only, never the database. Names, slugs and states; never
 * markup.
 *
 * @since API 1
 * @param array<string, mixed> $pattern A registry entry, or any array with `name` and `content`.
 * @return array<string, mixed>|\WP_Error blocklane_pairs_ambiguous when two toggling navigations name different overlays.
 */
function blocklane_pro_api_pattern_pairs( array $pattern ): array|\WP_Error {
	return \blocklane_pro\Pattern_Pairs::of( $pattern );
}

/**
 * The registered, inserter-visible patterns that open an overlay pattern,
 * in registry order. Call after init.
 *
 * @since API 1
 * @param string $name An overlay pattern's name.
 * @return list<string>|\WP_Error blocklane_pairs_not_overlay for a name that is not a registered navigation-overlay pattern.
 */
function blocklane_pro_api_overlay_partners( string $name ): array|\WP_Error {
	return \blocklane_pro\Pattern_Pairs::partners( $name );
}

/**
 * The family a pattern belongs to (spec 2026-10-17-pattern-families §3.3):
 * { id, base, value, kind, renamed, options: list<{ value, label, pattern,
 * kind }> }, labels translated, or null for a name no family holds. A base,
 * a derived member, a sibling and a retired name `renamed` points at a
 * family's layout all resolve (kind base, derived or sibling; renamed true
 * for the retired one). From the taxonomy alone: whether each option is
 * registered on this site is the caller's to ask.
 *
 * @since API 1
 * @param string $name A pattern name.
 * @return array<string, mixed>|\WP_Error|null The family, null, or the taxonomy's load failure.
 */
function blocklane_pro_api_pattern_family( string $name ): array|\WP_Error|null {
	return \blocklane_pro\Pattern_Taxonomy::family_labeled( $name );
}

/**
 * Every family, labels translated: id => { id, base, base_value, options:
 * list<{ value, label, pattern, kind }> }, base first.
 *
 * @since API 1
 * @return array<string, array<string, mixed>>|\WP_Error The families, or the taxonomy's load failure.
 */
function blocklane_pro_api_pattern_families(): array|\WP_Error {
	return \blocklane_pro\Pattern_Taxonomy::families_labeled();
}

/**
 * The registered, inserter-visible patterns with a Mega Menu item that
 * opens a panel pattern, in registry order, each once. Call after init.
 *
 * @since API 1
 * @param string $name A Mega Menu panel pattern's name.
 * @return list<string>|\WP_Error blocklane_pairs_not_panel for a name that is not a registered core/template-part/menu pattern.
 */
function blocklane_pro_api_panel_partners( string $name ): array|\WP_Error {
	return \blocklane_pro\Pattern_Pairs::panel_partners( $name );
}
