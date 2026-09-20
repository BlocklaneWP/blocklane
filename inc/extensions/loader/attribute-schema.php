<?php
/**
 * The attribute schema registrar — every extension's block attributes,
 * registered on the core blocks in BOTH editions from one generated table.
 *
 * WHY. The block editor keeps only the attributes the RUNNING build has
 * registered: core's getBlockAttributes() builds a parsed block's attribute
 * map from blockType.attributes alone, and getCommentAttributes() writes only
 * those back at save. So a page authored under Pro and saved once under the
 * free edition lost every Pro extension attribute — hover transitions,
 * animations, video modals, tab layout, and popup bindings, the last of which
 * free's own popups runtime reads (#858). Core inlines the PHP registry's
 * attributes into the editor before the JS block library registers anything
 * (wp-admin/edit-form-blocks.php's unstable__bootstrapServerSideBlockDefinitions
 * call on the wp-blocks handle, and the blocks store keeps the FIRST
 * definition it is given), so a PHP data table registered through
 * register_block_type_args IS the editor's schema for core blocks. Four Pro
 * loaders already relied on that without saying so; this file is the one
 * place it happens now.
 *
 * WHAT IT IS NOT. Data preservation, never a capability — the third entry of
 * the allowlist the two mu-runtimes opened (Amendment 13): a names-and-types
 * table plus this registrar, which declares no control, no save filter, no
 * render and no stylesheet. A free user who types one of these keys into the
 * code editor gets a stored attribute and nothing else. That is also why
 * there is NO edition filter and NO toggle gate in the loop below, alone in
 * this directory: a save in either edition, with any toggle in any state,
 * must write back what the other edition wrote.
 *
 * ABOVE the safe-mode return in frontend-loader.php (D3): a data-only filter
 * has no behavior to silence, and under BLOCKLANE_PRO_SAFE_MODE the editor
 * bundle is not enqueued, so without this a Pro save in safe mode lost the
 * JS-only attributes exactly the way free did.
 *
 * SOURCE OF TRUTH: inc/extensions/src/controls/<dir>/attributes.json, one per
 * extension; the JS controls import the same file, and bin/generate-edition.php
 * renders them all into inc/extensions/attribute-schema.php (data only,
 * identical bytes in both editions, --check keeps it fresh). The two
 * predicates that are code — hover-color's text-color support test and
 * advanced-group's link/stack test — mirror this file (MIRROR CONTRACT
 * comments there name it).
 *
 * A function file (function_exists-wrapped, no classes, no namespace), the
 * runtime rule.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'blocklane_pro_ext_attribute_schema' ) ) {

	/**
	 * The rendered table, read once per request.
	 *
	 * @return array<string, list<array{blocks: mixed, attributes: array<string, array<string, mixed>>}>> unit id => groups.
	 */
	function blocklane_pro_ext_attribute_schema(): array {
		static $schema = null;
		if ( null === $schema ) {
			$file   = dirname( __DIR__ ) . '/attribute-schema.php';
			$schema = is_file( $file ) ? (array) require $file : array();
		}
		return $schema;
	}
}

if ( ! function_exists( 'blocklane_pro_ext_attribute_schema_matches' ) ) {

	/**
	 * Does a group's predicate match this block type? Three forms, no more:
	 * an explicit block-name list; "*"; { "support": "color.text" } — the
	 * block's color support is truthy with `text` not false, or it carries
	 * the legacy textColor attribute (hover-color's predicate, mirrored in
	 * its index.js).
	 *
	 * @param mixed                $predicate  The group's `blocks` value.
	 * @param string               $block_type Block name.
	 * @param array<string, mixed> $args       The block type args being registered.
	 */
	function blocklane_pro_ext_attribute_schema_matches( mixed $predicate, string $block_type, array $args ): bool {
		if ( '*' === $predicate ) {
			return true;
		}
		if ( ! is_array( $predicate ) ) {
			return false;
		}
		if ( isset( $predicate['support'] ) ) {
			if ( 'color.text' !== $predicate['support'] ) {
				return false;
			}
			$color = $args['supports']['color'] ?? null;
			if ( $color && ( ! is_array( $color ) || ! isset( $color['text'] ) || false !== $color['text'] ) ) {
				return true;
			}
			return isset( $args['attributes']['textColor'] );
		}
		return in_array( $block_type, $predicate, true );
	}
}

if ( ! function_exists( 'blocklane_pro_ext_attribute_schema_args' ) ) {

	/**
	 * register_block_type_args, priority 10, 2 args: add every matching
	 * group's attributes to the block type. Ours win on a name collision,
	 * matching the JS `{ ...settings.attributes, ...OURS }`. An object-typed
	 * attribute whose rendered default is array() gets stdClass: an empty PHP
	 * array JSON-encodes as [] in the server block definitions, which is not
	 * an object (the trap advanced-grid.php documented).
	 *
	 * @param array<string, mixed> $args       Block type args.
	 * @param string               $block_type Block name.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_ext_attribute_schema_args( array $args, string $block_type ): array {
		foreach ( blocklane_pro_ext_attribute_schema() as $groups ) {
			foreach ( $groups as $group ) {
				if ( ! blocklane_pro_ext_attribute_schema_matches( $group['blocks'] ?? null, $block_type, $args ) ) {
					continue;
				}
				$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
				foreach ( $group['attributes'] as $name => $schema ) {
					if ( 'object' === ( $schema['type'] ?? '' ) && array() === ( $schema['default'] ?? null ) ) {
						$schema['default'] = new \stdClass();
					}
					$attributes[ (string) $name ] = $schema;
				}
				$args['attributes'] = $attributes;
			}
		}
		return $args;
	}
	add_filter( 'register_block_type_args', 'blocklane_pro_ext_attribute_schema_args', 10, 2 );
}
