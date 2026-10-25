<?php
/**
 * The attribute schema registrar: every extension's block attributes,
 * registered on the core blocks from one generated table, by ownership.
 *
 * WHY. The block editor keeps only the attributes the RUNNING build has
 * registered: core's getBlockAttributes() builds a parsed block's attribute
 * map from blockType.attributes alone, and getCommentAttributes() writes only
 * those back at save. Core inlines the PHP registry's attributes into every
 * block editor before the JS block library registers anything
 * (unstable__bootstrapServerSideBlockDefinitions in edit-form-blocks.php,
 * site-editor.php, widgets-form-blocks.php and the Customizer's widgets; the
 * blocks store keeps the FIRST definition it is given), so a PHP data table
 * registered through register_block_type_args IS the editor's schema for core
 * blocks. This file is the one place that happens.
 *
 * OWN AND FOREIGN. The table keys each group by the unit that gives its
 * attributes meaning (bin/generate-edition.php; a manifest `schema_owner`
 * names it when that is not the unit owning the sidecar). A group is OWN in
 * an edition that carries its key unit: its full sidecar definition is
 * registered on every site, and ours wins a name collision, matching the JS
 * `{ ...settings.attributes, ...OURS }`. Any other group is FOREIGN: it is
 * registered only where blocklane_pro_pro_seen() says the Pro build has run,
 * and then only as `{ type, enum?, default? }`, the keys core's parser reads
 * (type and enum validity, defaults) and its serializer reads (it omits a
 * value equal to the default). Nothing else travels: a `role` would change
 * what the editor offers (a content role on a tab panel's description makes
 * core/tab-panel a content block). On a foreign collision the block's own
 * definition stands and ours is skipped. Where Pro never ran, no foreign
 * group is registered, and core's editor drops those attributes at the next
 * save, silently (spec 2026-10-17-pro-downgrade-inert-attributes, D4).
 *
 * WHAT IT IS NOT. Compatibility with data a companion build wrote; it
 * registers a schema only, no behavior: no control, no save filter, no
 * render and no stylesheet. A user who types one of these keys into the code
 * editor gets a stored attribute and nothing else. There is NO toggle gate
 * below, alone in this directory: a save with any toggle in any state must
 * write back what it was opened with.
 *
 * ABOVE the safe-mode return in frontend-loader.php (D3): a data-only filter
 * has no behavior to silence, and under BLOCKLANE_PRO_SAFE_MODE the editor
 * bundle is not enqueued, so without this a Pro save in safe mode lost the
 * JS-only attributes.
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
	 * @return array<string, list<array{blocks: mixed, attributes: array<string, mixed>}>> unit id => groups, each attribute in the compact form blocklane_pro_ext_attribute_definition() expands.
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

if ( ! function_exists( 'blocklane_pro_ext_attribute_definition' ) ) {

	/**
	 * Expand one rendered attribute into the definition register_block_type
	 * takes. The table is a name list (see bin/generate-edition.php's
	 * edition_compact_attribute_schema(), whose inverse this is): a bare
	 * string is a type with no default, a two-item list is a type and its
	 * default, and a keyed map is a full definition passed through as is.
	 *
	 * @param mixed $rendered One attribute's value in the rendered table.
	 * @return array<string, mixed> The attribute definition.
	 */
	function blocklane_pro_ext_attribute_definition( mixed $rendered ): array {
		if ( is_string( $rendered ) ) {
			return array( 'type' => $rendered );
		}
		if ( is_array( $rendered ) && array_is_list( $rendered ) && 2 === count( $rendered ) ) {
			return array(
				'type'    => $rendered[0],
				'default' => $rendered[1],
			);
		}
		return is_array( $rendered ) ? $rendered : array();
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
	 * register_block_type_args, priority 10, 2 args: the registrar, for the
	 * running edition. Its body is blocklane_pro_ext_attribute_schema_resolve()
	 * with this edition's unit test.
	 *
	 * @param array<string, mixed> $args       Block type args.
	 * @param string               $block_type Block name.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_ext_attribute_schema_args( array $args, string $block_type ): array {
		return blocklane_pro_ext_attribute_schema_resolve( $args, $block_type, 'blocklane_pro_edition_has' );
	}
	add_filter( 'register_block_type_args', 'blocklane_pro_ext_attribute_schema_args', 10, 2 );
}

if ( ! function_exists( 'blocklane_pro_ext_attribute_schema_resolve' ) ) {

	/**
	 * Add every matching group's attributes to a block type's args: own
	 * groups in full, foreign groups projected and only where the Pro build
	 * has run (see the file header). The unit test is an argument so the
	 * edition battery can drive the free edition's classification on a Pro
	 * site too; the filter above always passes blocklane_pro_edition_has.
	 * The predicate is asked once, and only when a foreign group exists.
	 *
	 * @param array<string, mixed>   $args       Block type args.
	 * @param string                 $block_type Block name.
	 * @param callable(string): bool $has        Whether the edition carries a unit.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_ext_attribute_schema_resolve( array $args, string $block_type, callable $has ): array {
		$seen = null;
		foreach ( blocklane_pro_ext_attribute_schema() as $unit => $groups ) {
			$own = (bool) $has( (string) $unit );
			if ( ! $own ) {
				$seen = $seen ?? blocklane_pro_pro_seen();
				if ( ! $seen ) {
					continue;
				}
			}
			foreach ( $groups as $group ) {
				$args = blocklane_pro_ext_attribute_schema_group( $args, $block_type, $group, $own );
			}
		}
		return $args;
	}
}

if ( ! function_exists( 'blocklane_pro_ext_attribute_schema_group' ) ) {

	/**
	 * Add one group's attributes when its predicate matches. OWN: the full
	 * definition, replacing a same-named attribute the block defines. FOREIGN:
	 * `{ type, enum?, default? }` only, and never over a name the block
	 * already defines. An object-typed attribute whose rendered default is
	 * array() gets stdClass: an empty PHP array JSON-encodes as [] in the
	 * server block definitions, which is not an object (the trap
	 * advanced-grid.php documented).
	 *
	 * @param array<string, mixed>                                $args       Block type args.
	 * @param string                                              $block_type Block name.
	 * @param array{blocks?: mixed, attributes: array<string, mixed>} $group  One rendered group.
	 * @param bool                                                $own        Whether the edition carries the group's key unit.
	 * @return array<string, mixed>
	 */
	function blocklane_pro_ext_attribute_schema_group( array $args, string $block_type, array $group, bool $own ): array {
		if ( ! blocklane_pro_ext_attribute_schema_matches( $group['blocks'] ?? null, $block_type, $args ) ) {
			return $args;
		}
		$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
		foreach ( $group['attributes'] as $name => $rendered ) {
			$name   = (string) $name;
			$schema = blocklane_pro_ext_attribute_definition( $rendered );
			if ( ! $own ) {
				if ( array_key_exists( $name, $attributes ) ) {
					continue; // The block's own definition stands.
				}
				$schema = array_intersect_key( $schema, array_flip( array( 'type', 'enum', 'default' ) ) );
			}
			if ( 'object' === ( $schema['type'] ?? '' ) && array() === ( $schema['default'] ?? null ) ) {
				$schema['default'] = new \stdClass();
			}
			$attributes[ $name ] = $schema;
		}
		$args['attributes'] = $attributes;
		return $args;
	}
}
