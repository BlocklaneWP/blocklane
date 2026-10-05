<?php
/**
 * Content Types — runtime (canonical source).
 *
 * SHIPS IN BOTH EDITIONS (free-edition build-target spec, Amendment 9): a
 * site that authored content types and taxonomies under Blocklane Pro keeps them registered, routable and editable in WordPress's own screens
 * after Pro is deactivated, because the free plugin renders that data. This
 * file registers no editor UI and offers no way to author a definition; on a
 * site that never had Pro the option is empty and it registers nothing.
 *
 * SELF-CONTAINED on purpose: it does NOT reference the plugin's namespace,
 * classes or constants, only core WordPress. That rule was written when this
 * file was stamped into a must-use plugin and ran outside the plugin; the
 * stamping was removed in 2026-08 and the rule is kept, because registering
 * post types is early work and a runtime that needs no plugin class loaded
 * cannot be tripped by load order.
 *
 * The plugin requires this file in-process, so the types it registers exist
 * only while a Blocklane plugin, free or Pro, is active. Definitions come from the option, and
 * an absent row means none — there is no second source.
 *
 * Because both copies are separate files loaded in the same request, every
 * declaration lives inside a `if ( ! function_exists( ... ) )` guard. There are
 * TWO guard blocks, and the split is load-bearing: the big block keyed on
 * blocklane_pro_ct_register, and a separate guard for
 * blocklane_pro_ct_registered_own — a function that must define even when a
 * stale baked copy already defined the big block's key (see the comment at its
 * guard). Top-level function declarations are bound at COMPILE time, so a
 * runtime `return` guard above them cannot prevent a redeclaration fatal —
 * wrapping them makes them conditionally (late) bound, so the second copy is a
 * clean no-op.
 *
 * It registers, from the `blocklane_pro_content_types` option: each custom post
 * type, its fields as post meta, a starter single template, and the
 * [blocklane-field] shortcode — plus, from the `blocklane_pro_taxonomies` option,
 * each custom taxonomy (same lifecycle, same option-only source). No user code is
 * ever executed — only fixed registration logic over validated definitions.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Safe mode silences every Blocklane Pro surface, the generated runtimes included —
// define BLOCKLANE_PRO_SAFE_MODE in wp-config to troubleshoot with all of it off
// (see the plugin's Modules class). The declarations below are conditionally
// (late) bound, so returning here cleanly skips them.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

if ( ! defined( 'BLOCKLANE_PRO_CT_OPTION' ) ) {
	define( 'BLOCKLANE_PRO_CT_OPTION', 'blocklane_pro_content_types' );
}

if ( ! defined( 'BLOCKLANE_PRO_TAX_OPTION' ) ) {
	define( 'BLOCKLANE_PRO_TAX_OPTION', 'blocklane_pro_taxonomies' );
}

/*
 * The tracker lives under its OWN guard, not the big block's: a stale baked
 * copy of this file defines blocklane_pro_ct_register and would skip the
 * whole block below — silently un-defining this function and disabling the
 * controller's own-registered subtraction on exactly the shadowed sites
 * (round-3 sweep-1002). With its own guard it always exists; on a shadowed
 * site it degrades to an EMPTY record (the stale register code never feeds
 * it), which the reserved lists treat as refuse-more, never allow-collision.
 */
if ( ! function_exists( 'blocklane_pro_ct_registered_own' ) ) {

	/**
	 * The slugs THIS runtime registered in THIS request, by kind. The
	 * registries only ever grow within a request, so after a save that
	 * deletes a definition its slug is still registered (from the pre-save
	 * options at init) while no longer being "ours" by current storage —
	 * readers of the registries that want to exclude Blocklane's own
	 * entries (the controller's reserved lists) must subtract THIS list
	 * too, or a slug deleted seconds ago reports as reserved-by-others in
	 * the very response that deleted it.
	 *
	 * @param string      $kind 'type' or 'taxonomy'.
	 * @param string|null $slug Appends when given (registration time).
	 * @return string[] The recorded slugs for $kind.
	 */
	function blocklane_pro_ct_registered_own( $kind, $slug = null ) {
		static $own = array(
			'type'     => array(),
			'taxonomy' => array(),
		);
		if ( null !== $slug && isset( $own[ $kind ] ) ) {
			$own[ $kind ][] = $slug;
		}
		return isset( $own[ $kind ] ) ? $own[ $kind ] : array();
	}
}

if ( ! function_exists( 'blocklane_pro_ct_register' ) ) {

	/**
	 * Read the type definitions from the option Blocklane Pro maintains.
	 *
	 * An absent row means no types are defined. There is no second source to
	 * consult: the snapshot fallback belonged to the generated must-use copy, and
	 * that whole system was removed in 2026-08.
	 *
	 * @return array
	 */
	function blocklane_pro_ct_definitions() {
		$stored = get_option( BLOCKLANE_PRO_CT_OPTION, null );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Read the taxonomy definitions from the option Blocklane Pro maintains.
	 *
	 * Absent row means none defined — same contract as the types above.
	 *
	 * @return array
	 */
	function blocklane_pro_ct_tax_definitions() {
		$stored = get_option( BLOCKLANE_PRO_TAX_OPTION, null );
		if ( is_array( $stored ) ) {
			return $stored;
		}

		return array();
	}

	/**
	 * Per-field-type sanitize callback for register_post_meta.
	 *
	 * @param string $type Field type.
	 * @return callable
	 */
	function blocklane_pro_ct_sanitizer( $type ) {
		switch ( $type ) {
			case 'textarea':
				return 'sanitize_textarea_field';
			case 'email':
				return 'sanitize_email';
			case 'url':
			case 'image':
				return 'esc_url_raw';
			case 'number':
				return static function ( $v ) {
					return is_numeric( $v ) ? (string) ( $v + 0 ) : '';
				};
			case 'rating':
				// Whole stars, clamped to the 0–5 scale. Stored as an integer
				// string so it stays queryable/sortable; rendered as stars.
				return static function ( $v ) {
					return (string) max( 0, min( 5, (int) $v ) );
				};
			case 'gallery':
				// A comma-separated list of attachment IDs. Keep positive ints
				// only, de-duplicated, order preserved.
				return static function ( $v ) {
					$ids = array();
					foreach ( explode( ',', (string) $v ) as $raw ) {
						$id = absint( $raw );
						if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
							$ids[] = $id;
						}
					}
					return implode( ',', $ids );
				};
			case 'text':
			case 'date':
			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Sanitize callback for a whole field definition. Most types key off the
	 * type alone (above); a `select` additionally restricts the saved value to
	 * the field's own option list, so it needs the field — not just the type.
	 *
	 * @param array $field Field definition.
	 * @return callable
	 */
	function blocklane_pro_ct_field_sanitizer( $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		if ( 'select' === $type ) {
			$options = ( isset( $field['options'] ) && is_array( $field['options'] ) )
				? array_map( 'strval', $field['options'] )
				: array();
			return static function ( $v ) use ( $options ) {
				$v = sanitize_text_field( (string) $v );
				return in_array( $v, $options, true ) ? $v : '';
			};
		}

		return blocklane_pro_ct_sanitizer( $type );
	}

	/**
	 * The full derived label set for a post type — the reference content-types
	 * experiment's deriveLabels, ported. These are the DEFAULTS; stored
	 * per-type label overrides are merged atop by the caller.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string,string>
	 */
	function blocklane_pro_ct_derive_type_labels( $singular, $plural ) {
		$lc_plural   = strtolower( $plural );
		$lc_singular = strtolower( $singular );

		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			/* translators: %s: the plural post type or taxonomy label. */
			'all_items'             => sprintf( __( 'All %s', 'blocklane' ), $plural ),
			'add_new'               => __( 'Add New', 'blocklane' ),
			/* translators: %s: the singular post type or taxonomy label. */
			'add_new_item'          => sprintf( __( 'Add New %s', 'blocklane' ), $singular ),
			/* translators: %s: what is being edited: a post type or taxonomy label, or an animation preset or type name. */
			'edit_item'             => sprintf( __( 'Edit %s', 'blocklane' ), $singular ),
			/* translators: %s: Singular post type label. */
			'new_item'              => sprintf( __( 'New %s', 'blocklane' ), $singular ),
			/* translators: %s: the post type or taxonomy label, singular or plural as the menu line reads. */
			'view_item'             => sprintf( __( 'View %s', 'blocklane' ), $singular ),
			/* translators: %s: the post type or taxonomy label, singular or plural as the menu line reads. */
			'view_items'            => sprintf( __( 'View %s', 'blocklane' ), $plural ),
			/* translators: %s: the plural post type or taxonomy label. */
			'search_items'          => sprintf( __( 'Search %s', 'blocklane' ), $plural ),
			/* translators: %s: the plural post type or taxonomy label, sometimes lowercase. */
			'not_found'             => sprintf( __( 'No %s found.', 'blocklane' ), $lc_plural ),
			/* translators: %s: Plural post type label, lowercase. */
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'blocklane' ), $lc_plural ),
			/* translators: %s: the singular post type or taxonomy label, or its lowercase plural. */
			'parent_item_colon'     => sprintf( __( 'Parent %s:', 'blocklane' ), $singular ),
			/* translators: %s: Singular post type label. */
			'archives'              => sprintf( __( '%s Archives', 'blocklane' ), $singular ),
			/* translators: %s: Singular post type label. */
			'attributes'            => sprintf( __( '%s Attributes', 'blocklane' ), $singular ),
			/* translators: %s: Singular post type label, lowercase. */
			'insert_into_item'      => sprintf( __( 'Insert into %s', 'blocklane' ), $lc_singular ),
			/* translators: %s: Singular post type label, lowercase. */
			'uploaded_to_this_item' => sprintf( __( 'Uploaded to this %s', 'blocklane' ), $lc_singular ),
			'featured_image'        => __( 'Featured image', 'blocklane' ),
			'set_featured_image'    => __( 'Set featured image', 'blocklane' ),
			'remove_featured_image' => __( 'Remove featured image', 'blocklane' ),
			'use_featured_image'    => __( 'Use as featured image', 'blocklane' ),
			/* translators: %s: Plural post type label, lowercase. */
			'filter_items_list'     => sprintf( __( 'Filter %s list', 'blocklane' ), $lc_plural ),
			/* translators: %s: Plural post type label. */
			'items_list_navigation' => sprintf( __( '%s list navigation', 'blocklane' ), $plural ),
			/* translators: %s: Plural post type label. */
			'items_list'            => sprintf( __( '%s list', 'blocklane' ), $plural ),
		);
	}

	/**
	 * Merge a definition's stored label overrides atop a derived label set.
	 * Only non-empty strings override — a blank stored value means "use the
	 * derived default".
	 *
	 * @param array<string,string> $derived    Derived labels.
	 * @param array<string,mixed>  $definition Type/taxonomy definition (may carry 'labels').
	 * @return array<string,string>
	 */
	function blocklane_pro_ct_merge_labels( $derived, $definition ) {
		if ( empty( $definition['labels'] ) || ! is_array( $definition['labels'] ) ) {
			return $derived;
		}
		foreach ( $definition['labels'] as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) && '' !== $value ) {
				$derived[ $key ] = $value;
			}
		}
		return $derived;
	}

	/**
	 * Register all custom post types + their field meta from the option.
	 */
	function blocklane_pro_ct_register() {
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( empty( $type['slug'] ) || ! is_string( $type['slug'] ) ) {
				continue;
			}

			// Inactive definitions are stored but NOT registered — the
			// reference's draft semantics. Entries are parked exactly as if
			// the plugin were deactivated; nothing is deleted. Absent key
			// (pre-schema rows) means active.
			if ( isset( $type['active'] ) && ! $type['active'] ) {
				continue;
			}

			$slug = $type['slug'];
			if ( post_type_exists( $slug ) ) {
				continue; // Don't clobber an already-registered type.
			}

			$singular = isset( $type['singular'] ) && '' !== $type['singular'] ? $type['singular'] : ucfirst( $slug );
			$plural   = isset( $type['plural'] ) && '' !== $type['plural'] ? $type['plural'] : $singular . 's';
			$supports = ! empty( $type['supports'] ) && is_array( $type['supports'] ) ? $type['supports'] : array( 'title', 'editor', 'thumbnail' );
			if ( ! in_array( 'title', $supports, true ) ) {
				$supports[] = 'title';
			}

			// "Block editor body" toggle decides the editing surface:
			//  - ON  → block editor; 'custom-fields' support exposes meta to the
			//          React Fields panel (sidebar) + block bindings.
			//  - OFF → classic editor; fields render via our PHP meta box instead,
			//          so NO 'custom-fields' support (it would add the ugly legacy
			//          key/value box). Field meta is still registered below.
			$body_on = in_array( 'editor', $supports, true );
			if ( $body_on ) {
				if ( ! in_array( 'custom-fields', $supports, true ) ) {
					$supports[] = 'custom-fields';
				}
			} else {
				$supports = array_values( array_diff( $supports, array( 'custom-fields' ) ) );
			}

			$public = ! isset( $type['public'] ) || ! empty( $type['public'] );

			$args = array(
				'labels'              => blocklane_pro_ct_merge_labels(
					blocklane_pro_ct_derive_type_labels( $singular, $plural ),
					$type
				),
				'public'              => $public,
				'hierarchical'        => ! empty( $type['hierarchical'] ),
				// Always manageable in wp-admin — turning "public" off marks the type
				// internal-only, but must NOT hide its own admin screens.
				'show_ui'             => true,
				'show_in_menu'        => true,
				'publicly_queryable'  => $public,
				'exclude_from_search' => ! $public,
				// Anonymous REST read follows the public toggle; logged-in editors
				// keep it so the block editor (which requires show_in_rest) works.
				'show_in_rest'        => $public || current_user_can( 'edit_posts' ),
				'has_archive'         => $public && ! empty( $type['has_archive'] ),
				'menu_icon'           => isset( $type['icon'] ) && '' !== $type['icon'] ? $type['icon'] : 'dashicons-admin-post',
				'supports'            => array_values( array_unique( $supports ) ),
				'rewrite'             => array( 'slug' => $slug ),
			);

			if ( ! empty( $type['description'] ) && is_string( $type['description'] ) ) {
				$args['description'] = $type['description'];
			}

			register_post_type( $slug, $args );
			blocklane_pro_ct_registered_own( 'type', $slug );

			// Field meta is always registered — it powers bindings, the shortcode,
			// REST, the block-editor Fields panel, and the classic meta box.
			$fields = ! empty( $type['fields'] ) && is_array( $type['fields'] ) ? $type['fields'] : array();
			foreach ( $fields as $field ) {
				if ( empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
					continue;
				}

				register_post_meta(
					$slug,
					$field['key'],
					array(
						// Stored as a string (the sanitizers emit numeric strings) so
						// that clearing a number/rating field to '' is a valid empty
						// value — a 'number' schema rejects '' and 400s the whole save.
						'type'              => 'string',
						// The field's display name. WP (6.7+) exposes it as the REST
						// schema `title`, which core's Block Bindings "Attributes"
						// panel (6.9+) shows as the field's name in each attribute's
						// connection menu — without it the panel falls back to the
						// raw meta key.
						'label'             => ( isset( $field['label'] ) && is_string( $field['label'] ) && '' !== $field['label'] )
							? $field['label']
							: $field['key'],
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => blocklane_pro_ct_field_sanitizer( $field ),
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}

			blocklane_pro_ct_register_template( $type );
		}

		// The Field Gallery block — one registration, tied to the CPTs it
		// renders (the inserter is scoped to CPT contexts; see the filter).
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'blocklane-pro/field-gallery' ) ) {
			register_block_type(
				'blocklane-pro/field-gallery',
				array(
					'api_version'     => 3,
					'title'           => __( 'Field Gallery', 'blocklane' ),
					'description'     => __( 'The images from a gallery field on the current entry.', 'blocklane' ),
					'category'        => 'theme',
					'attributes'      => array(
						'fieldKey' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
					'uses_context'    => array( 'postId', 'postType' ),
					'supports'        => array(
						'html'    => false,
						'align'   => array( 'wide', 'full' ),
						'spacing' => array(
							'margin'  => true,
							'padding' => true,
						),
					),
					'render_callback' => 'blocklane_pro_ct_field_gallery_render',
				)
			);
		}

		// Taxonomies after the types so ours exist when they attach; attaching
		// to a type registered later (core's, another plugin's) works too — the
		// association lives on the taxonomy.
		blocklane_pro_ct_register_taxonomies();

		blocklane_pro_ct_maybe_flush();
	}

	/**
	 * The full derived label set for a taxonomy — the reference content-types
	 * experiment's taxonomy deriveLabels, ported. These are the DEFAULTS;
	 * stored per-taxonomy label overrides are merged atop by the caller.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array<string,string>
	 */
	function blocklane_pro_ct_derive_tax_labels( $singular, $plural ) {
		$lc_plural = strtolower( $plural );

		return array(
			'name'                       => $plural,
			'singular_name'              => $singular,
			'menu_name'                  => $plural,
			/* translators: %s: the plural post type or taxonomy label. */
			'all_items'                  => sprintf( __( 'All %s', 'blocklane' ), $plural ),
			/* translators: %s: what is being edited: a post type or taxonomy label, or an animation preset or type name. */
			'edit_item'                  => sprintf( __( 'Edit %s', 'blocklane' ), $singular ),
			/* translators: %s: the post type or taxonomy label, singular or plural as the menu line reads. */
			'view_item'                  => sprintf( __( 'View %s', 'blocklane' ), $singular ),
			/* translators: %s: Singular taxonomy label. */
			'update_item'                => sprintf( __( 'Update %s', 'blocklane' ), $singular ),
			/* translators: %s: the singular post type or taxonomy label. */
			'add_new_item'               => sprintf( __( 'Add New %s', 'blocklane' ), $singular ),
			/* translators: %s: Singular taxonomy label. */
			'new_item_name'              => sprintf( __( 'New %s Name', 'blocklane' ), $singular ),
			/* translators: %s: the plural post type or taxonomy label. */
			'search_items'               => sprintf( __( 'Search %s', 'blocklane' ), $plural ),
			/* translators: %s: the plural post type or taxonomy label, sometimes lowercase. */
			'not_found'                  => sprintf( __( 'No %s found.', 'blocklane' ), $lc_plural ),
			/* translators: %s: Plural taxonomy label. */
			'back_to_items'              => sprintf( __( '← Back to %s', 'blocklane' ), $plural ),
			/* translators: %s: Singular taxonomy label. */
			'parent_item'                => sprintf( __( 'Parent %s', 'blocklane' ), $singular ),
			/* translators: %s: Plural taxonomy label. */
			'popular_items'              => sprintf( __( 'Popular %s', 'blocklane' ), $plural ),
			/* translators: %s: Plural taxonomy label, lowercase. */
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'blocklane' ), $lc_plural ),
			/* translators: %s: the singular post type or taxonomy label, or its lowercase plural. */
			'parent_item_colon'          => sprintf( __( 'Parent %s:', 'blocklane' ), $singular ),
			/* translators: %s: Plural taxonomy label, lowercase. */
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'blocklane' ), $lc_plural ),
			/* translators: %s: Plural taxonomy label, lowercase. */
			'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'blocklane' ), $lc_plural ),
		);
	}

	/**
	 * Register all custom taxonomies from the option. There is no second
	 * source: the snapshot fallback belonged to the generated must-use copy and
	 * that whole system was removed in 2026-08 (see the type-side note above).
	 * An absent option row means no taxonomies, and clearing it is final.
	 */
	function blocklane_pro_ct_register_taxonomies() {
		foreach ( blocklane_pro_ct_tax_definitions() as $taxonomy ) {
			if ( empty( $taxonomy['slug'] ) || ! is_string( $taxonomy['slug'] ) ) {
				continue;
			}

			// Inactive definitions register nothing — see the type loop.
			if ( isset( $taxonomy['active'] ) && ! $taxonomy['active'] ) {
				continue;
			}

			$slug = $taxonomy['slug'];
			if ( taxonomy_exists( $slug ) ) {
				continue; // Don't clobber an already-registered taxonomy.
			}

			$singular     = isset( $taxonomy['singular'] ) && '' !== $taxonomy['singular'] ? $taxonomy['singular'] : ucfirst( $slug );
			$plural       = isset( $taxonomy['plural'] ) && '' !== $taxonomy['plural'] ? $taxonomy['plural'] : $singular . 's';
			$hierarchical = ! empty( $taxonomy['hierarchical'] );
			$public       = ! isset( $taxonomy['public'] ) || ! empty( $taxonomy['public'] );
			$types        = ! empty( $taxonomy['types'] ) && is_array( $taxonomy['types'] ) ? array_map( 'strval', $taxonomy['types'] ) : array();

			$labels = blocklane_pro_ct_merge_labels(
				blocklane_pro_ct_derive_tax_labels( $singular, $plural ),
				$taxonomy
			);

			$args = array(
				'labels'             => $labels,
				'hierarchical'       => $hierarchical,
				'public'             => $public,
				// Always manageable in wp-admin — "public" off marks the
				// taxonomy internal-only, but must NOT hide its own screens.
				'show_ui'            => true,
				// Stored per-taxonomy since the schema grew (2026-08); an
				// absent key keeps the pre-schema behavior (column shown).
				'show_admin_column'  => ! isset( $taxonomy['show_admin_column'] ) || ! empty( $taxonomy['show_admin_column'] ),
				// Nav-menus visibility follows `public` when unset — core's
				// own default for register_taxonomy.
				'show_in_nav_menus'  => isset( $taxonomy['show_in_nav_menus'] ) ? ! empty( $taxonomy['show_in_nav_menus'] ) : $public,
				// Advanced surfaces — default on (core defaults both to
				// show_ui, which we force on above).
				'show_tagcloud'      => ! isset( $taxonomy['show_tagcloud'] ) || ! empty( $taxonomy['show_tagcloud'] ),
				'show_in_quick_edit' => ! isset( $taxonomy['show_in_quick_edit'] ) || ! empty( $taxonomy['show_in_quick_edit'] ),
				'publicly_queryable' => $public,
				// Anonymous REST read follows the public toggle; logged-in
				// editors keep it so the block editor's terms panel (which
				// requires show_in_rest) works. Same rule as the types.
				'show_in_rest'       => $public || current_user_can( 'edit_posts' ),
				'rewrite'            => $public ? array( 'slug' => $slug ) : false,
			);

			if ( ! empty( $taxonomy['description'] ) && is_string( $taxonomy['description'] ) ) {
				$args['description'] = $taxonomy['description'];
			}

			// A non-empty default term name creates that term on registration
			// (and core assigns it to entries published with no term in this
			// taxonomy). Renaming it later creates a NEW term — the previous
			// default stays in the terms list; the builder UI says so.
			if ( ! empty( $taxonomy['default_term'] ) && is_string( $taxonomy['default_term'] ) ) {
				$args['default_term'] = array( 'name' => sanitize_text_field( $taxonomy['default_term'] ) );
			}

			// The reference's "Sort terms" flag — terms keep the order they
			// were assigned in. Only passed when on (absent = core default).
			if ( ! empty( $taxonomy['sort'] ) ) {
				$args['sort'] = true;
			}

			register_taxonomy( $slug, $types, $args );
			blocklane_pro_ct_registered_own( 'taxonomy', $slug );
		}
	}

	/**
	 * Register a starter single-{slug} block template binding the type's fields,
	 * with the post body via the Post Content block.
	 *
	 * @param array $type Type definition.
	 */
	function blocklane_pro_ct_register_template( $type ) {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		$slug     = $type['slug'];
		$singular = isset( $type['singular'] ) && '' !== $type['singular'] ? $type['singular'] : ucfirst( $slug );
		$supports = ! empty( $type['supports'] ) && is_array( $type['supports'] ) ? $type['supports'] : array();

		$featured = in_array( 'thumbnail', $supports, true )
			? '<!-- wp:post-featured-image {"aspectRatio":"21/9","align":"full","style":{"spacing":{"margin":{"bottom":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dspacing\u002d\u002d60, 3rem)"}}}} /-->'
			: '';

		// Mirrors the theme's Full Width layout: a clean canvas (featured image +
		// title + body). Fields are NOT auto-listed — the author places them where
		// they want via block bindings or inline field tokens.
		$content = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
			. '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dspacing\u002d\u002d70, 4rem)","bottom":"var(\u002d\u002dwp\u002d\u002dpreset\u002d\u002dspacing\u002d\u002d90, 6rem)"},"blockGap":"2rem"}},"layout":{"type":"default"}} --><main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--70, 4rem);padding-bottom:var(--wp--preset--spacing--90, 6rem)">'
			. '<!-- wp:group {"align":"full","style":{"spacing":{"blockGap":"2rem"}},"layout":{"type":"constrained"}} --><div class="wp-block-group alignfull">'
			. $featured
			. '<!-- wp:post-title {"level":1,"fontSize":"heading-xxx-large"} /-->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->'
			. '</main><!-- /wp:group -->'
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

		register_block_template(
			'blocklane-pro//single-' . $slug,
			array(
				/* translators: %s: content type singular name. */
				'title'       => sprintf( _x( '%s (Single)', 'template title', 'blocklane' ), $singular ),
				/* translators: %s: content type singular name. */
				'description' => sprintf( __( 'Starter template for a single %s.', 'blocklane' ), $singular ),
				'content'     => $content,
				'post_types'  => array( $slug ),
			)
		);
	}

	/**
	 * Flush rewrite rules once when the set of type/taxonomy rewrite slugs changes.
	 */
	function blocklane_pro_ct_maybe_flush() {
		$slugs = array();
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! empty( $type['slug'] ) ) {
				// public + has_archive both shape a type's archive rewrite;
				// hierarchical shapes its permalink structure; active decides
				// whether it registers at all — toggling any of them on an
				// existing type must flush (mirrors the taxonomy branch
				// below). Compute the absent-key defaults the way
				// registration does — unset public/active mean on.
				$type_public = ( ! isset( $type['public'] ) || ! empty( $type['public'] ) ) ? '1' : '0';
				$type_active = ( ! isset( $type['active'] ) || ! empty( $type['active'] ) ) ? '1' : '0';
				$slugs[]     = 'ct:' . $type['slug']
					. ':' . $type_public
					. ':' . ( empty( $type['has_archive'] ) ? '0' : '1' )
					. ':' . ( empty( $type['hierarchical'] ) ? '0' : '1' )
					. ':' . $type_active;
			}
		}
		foreach ( blocklane_pro_ct_tax_definitions() as $taxonomy ) {
			if ( ! empty( $taxonomy['slug'] ) ) {
				// Public + hierarchical both shape a taxonomy's rewrite rules,
				// and active decides registration — toggling any must flush.
				$tax_active = ( ! isset( $taxonomy['active'] ) || ! empty( $taxonomy['active'] ) ) ? '1' : '0';
				$slugs[]    = 'tax:' . $taxonomy['slug']
					. ':' . ( empty( $taxonomy['public'] ) ? '0' : '1' )
					. ':' . ( empty( $taxonomy['hierarchical'] ) ? '0' : '1' )
					. ':' . $tax_active;
			}
		}
		sort( $slugs );
		$hash = md5( implode( '|', $slugs ) );

		if ( get_option( 'blocklane_pro_ct_rewrite_hash' ) !== $hash ) {
			flush_rewrite_rules( false );
			// Autoload: this row is read on EVERY init (right above), so it
			// must ride the alloptions query. delete+add rather than
			// update_option because update_option cannot flip autoload on an
			// existing row (pre-2026-07 rows were written autoload=false).
			delete_option( 'blocklane_pro_ct_rewrite_hash' );
			add_option( 'blocklane_pro_ct_rewrite_hash', $hash, '', true );
		}
	}

	/**
	 * Resolve a field's type for a given record (post type → its field defs).
	 * Returns '' for unknown keys so callers fall back to plain text.
	 *
	 * @param int    $post_id Record ID.
	 * @param string $key     Field key.
	 * @return string Field type, or ''.
	 */
	function blocklane_pro_ct_field_type( $post_id, $key ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return '';
		}
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! isset( $type['slug'] ) || $type['slug'] !== $post_type ) {
				continue;
			}
			$fields = ! empty( $type['fields'] ) && is_array( $type['fields'] ) ? $type['fields'] : array();
			foreach ( $fields as $field ) {
				if ( isset( $field['key'] ) && $field['key'] === $key ) {
					return isset( $field['type'] ) ? (string) $field['type'] : 'text';
				}
			}
		}
		return '';
	}

	/**
	 * Build a 0–5 star string. Plain glyphs (★★★★☆, inherits text color) for the
	 * binding fallback; rich self-contained markup (gold filled + gray empty, with
	 * an accessible label) for the surfaces we fully control. Self-styled inline so
	 * no front-end stylesheet is required.
	 *
	 * @param int  $n    Stars 0–5.
	 * @param bool $rich Whether to emit styled, labeled markup.
	 * @return string
	 */
	function blocklane_pro_ct_stars( $n, $rich = true ) {
		$n      = max( 0, min( 5, (int) $n ) );
		$filled = str_repeat( '★', $n );
		$empty  = str_repeat( '☆', 5 - $n );

		if ( ! $rich ) {
			return $filled . $empty;
		}

		/* translators: %d: rating value out of 5. */
		$label = sprintf( __( '%d out of 5 stars', 'blocklane' ), $n );

		return '<span class="blocklane-pro-rating" role="img" aria-label="' . esc_attr( $label ) . '">'
			. '<span class="blocklane-pro-rating__on" style="color:#f5b301">' . $filled . '</span>'
			. '<span class="blocklane-pro-rating__off" style="color:#d4d4d8">' . $empty . '</span>'
			. '</span>';
	}

	/**
	 * Format a stored meta value by field type for output. Ratings become stars;
	 * everything else is plain escaped text (current behavior).
	 *
	 * @param mixed  $value Stored meta value.
	 * @param string $type  Field type.
	 * @param bool   $rich  Whether typed output may emit styled markup.
	 * @return string
	 */
	function blocklane_pro_ct_format_value( $value, $type, $rich = true ) {
		if ( 'rating' === $type ) {
			return blocklane_pro_ct_stars( (int) $value, $rich );
		}
		if ( 'gallery' === $type ) {
			return blocklane_pro_ct_gallery_html( is_scalar( $value ) ? (string) $value : '' );
		}
		return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}

	/**
	 * A gallery field's stored attachment IDs rendered as a figure of images.
	 * Empty when no IDs resolve to real attachments.
	 *
	 * @param string $value Comma-separated attachment IDs.
	 * @return string
	 */
	function blocklane_pro_ct_gallery_html( $value ) {
		$imgs = array();
		foreach ( explode( ',', $value ) as $raw ) {
			$id = absint( $raw );
			if ( $id <= 0 ) {
				continue;
			}
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'blocklane-pro-ct-gallery__img' ) );
			if ( $img ) {
				$imgs[] = '<figure class="blocklane-pro-ct-gallery__item">' . $img . '</figure>';
			}
		}

		if ( empty( $imgs ) ) {
			return '';
		}

		blocklane_pro_ct_gallery_styles();
		return '<div class="blocklane-pro-ct-gallery">' . implode( '', $imgs ) . '</div>';
	}

	/**
	 * Server render for the Field Gallery block (blocklane-pro/field-gallery).
	 * Resolves the current record (the looped postId context, else the queried
	 * object) and outputs its chosen gallery field. Renders nothing unless the
	 * chosen key is actually a gallery field on that record's type — so the
	 * block can only ever surface a real gallery field, never arbitrary meta.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Inner content (unused).
	 * @param \WP_Block $block      Block instance (for context).
	 * @return string
	 */
	function blocklane_pro_ct_field_gallery_render( $attributes, $content, $block ) {
		$key = isset( $attributes['fieldKey'] ) ? sanitize_key( $attributes['fieldKey'] ) : '';
		if ( '' === $key ) {
			return '';
		}

		$post_id = ( is_object( $block ) && ! empty( $block->context['postId'] ) )
			? (int) $block->context['postId']
			: get_queried_object_id();
		if ( ! $post_id ) {
			return '';
		}

		if ( 'gallery' !== blocklane_pro_ct_field_type( $post_id, $key ) ) {
			return '';
		}

		$html = blocklane_pro_ct_gallery_html( (string) get_post_meta( $post_id, $key, true ) );
		if ( '' === $html ) {
			return '';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * The content type a block-editor context targets, or '' — a CPT entry
	 * editor (post type is one of ours) or a single-{slug} template. Mirrors the
	 * editor's useResolvedType; used to scope where the Field Gallery block is
	 * offered.
	 *
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return string
	 */
	function blocklane_pro_ct_context_type( $context ) {
		$post = ( is_object( $context ) && isset( $context->post ) ) ? $context->post : null;
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$slugs = array();
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! empty( $type['slug'] ) ) {
				$slugs[] = $type['slug'];
			}
		}

		if ( in_array( $post->post_type, $slugs, true ) ) {
			return $post->post_type;
		}
		if ( 'wp_template' === $post->post_type
			&& preg_match( '/single-([a-z0-9_-]+)$/', (string) $post->post_name, $m )
			&& in_array( $m[1], $slugs, true ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Keep the Field Gallery block out of the inserter everywhere except our
	 * CPT contexts (entry editor + single-{slug} template) — it renders a CPT
	 * field and is meaningless anywhere else. The block stays registered (so it
	 * renders wherever validly placed); this only governs the inserter.
	 *
	 * @param bool|string[]            $allowed Allowed block types (true = all).
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	function blocklane_pro_ct_restrict_field_gallery( $allowed, $context ) {
		$block = 'blocklane-pro/field-gallery';
		$name  = ( is_object( $context ) && isset( $context->name ) ) ? $context->name : '';

		// The Site Editor is where the single-{slug} template lives — but it
		// doesn't tell this filter which template is open, so we can't narrow
		// to single-{slug} here. Leave the block available across the Site
		// Editor (it renders only on a real CPT record regardless, so a stray
		// placement is a harmless no-op), and let the post/page-editor case
		// below do the real hiding.
		if ( 'core/edit-site' === $name ) {
			return $allowed;
		}

		// Our CPT's own entry editor.
		if ( '' !== blocklane_pro_ct_context_type( $context ) ) {
			return $allowed;
		}

		// Everywhere else (post/page editor, another plugin's CPT) — hide it,
		// but ONLY by subtracting from an explicit allow-list. When $allowed is
		// true (the default — "all blocks"), do NOT materialize the server
		// registry into an array: that registry holds only PHP-registered
		// blocks, so returning it as the allow-list would silently drop every
		// JS-only-registered third-party block from the inserter. Leaving true
		// keeps all blocks available; a stray field-gallery placement outside a
		// CPT record is a harmless no-op (it renders only on a real record).
		if ( true === $allowed || ! is_array( $allowed ) ) {
			return $allowed;
		}

		return array_values( array_diff( $allowed, array( $block ) ) );
	}

	/**
	 * Default front-end grid for gallery fields (stylable; class hooks are
	 * stable). Enqueued lazily the first time a gallery actually renders (see
	 * blocklane_pro_ct_gallery_html), so pages with no gallery pay nothing.
	 * Idempotent: the static guard makes repeat calls a no-op.
	 */
	function blocklane_pro_ct_gallery_styles() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		wp_register_style( 'blocklane-pro-ct-gallery', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- src-less handle carrying only inline CSS; there is no file to version.
		wp_enqueue_style( 'blocklane-pro-ct-gallery' );
		wp_add_inline_style(
			'blocklane-pro-ct-gallery',
			'.blocklane-pro-ct-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}'
			. '.blocklane-pro-ct-gallery__item{margin:0}'
			. '.blocklane-pro-ct-gallery__img{display:block;width:100%;height:100%;object-fit:cover;border-radius:4px}'
		);
	}

	/**
	 * Keep gallery fields out of core's Block Bindings "Attributes" panel.
	 *
	 * The editor builds the core/post-meta source's fields list from the post
	 * type endpoint's OPTIONS description (schema.properties.meta.properties —
	 * see getRegisteredPostMeta in @wordpress/core-data). A gallery field stores
	 * a CSV of attachment IDs, so offering it for a scalar binding would render
	 * "3,7,21" into a paragraph — the Field Gallery block is THE gallery
	 * surface. Stripping the key from the OPTIONS description hides it from the
	 * bindings UI without touching the meta itself: REST value read/write and
	 * save validation run against the registered meta, not this description.
	 *
	 * (rest_endpoints_description would be the natural filter, but the OPTIONS
	 * handler — rest_handle_options_request → get_data_for_route — never applies
	 * it; rest_post_dispatch is the hook that sees every OPTIONS response.)
	 *
	 * @param \WP_REST_Response $response Result to send.
	 * @param \WP_REST_Server   $server   Server instance (unused).
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 * @return \WP_REST_Response
	 */
	function blocklane_pro_ct_hide_gallery_meta_schema( $response, $server, $request ) {
		unset( $server );

		if ( ! $response instanceof \WP_REST_Response || 'OPTIONS' !== $request->get_method() ) {
			return $response;
		}

		$data = $response->get_data();
		if ( empty( $data['schema']['title'] ) || empty( $data['schema']['properties']['meta']['properties'] )
			|| ! is_array( $data['schema']['properties']['meta']['properties'] ) ) {
			return $response;
		}

		// WP_REST_Posts_Controller sets the item schema's title to the post type.
		$post_type = (string) $data['schema']['title'];

		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! isset( $type['slug'] ) || $type['slug'] !== $post_type ) {
				continue;
			}
			$fields = ! empty( $type['fields'] ) && is_array( $type['fields'] ) ? $type['fields'] : array();
			foreach ( $fields as $field ) {
				if ( isset( $field['type'], $field['key'] ) && 'gallery' === $field['type'] ) {
					unset( $data['schema']['properties']['meta']['properties'][ $field['key'] ] );
				}
			}
			$response->set_data( $data );
			break;
		}

		return $response;
	}

	/**
	 * [blocklane-field key="phone" id="123"] — output a field value for a record
	 * (defaults to the queried object). Typed: ratings render as stars, all else
	 * is escaped text.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	function blocklane_pro_ct_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'key' => '',
				'id'  => 0,
			),
			$atts,
			'blocklane-field'
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			$post_id = get_queried_object_id();
		}
		$key = sanitize_key( $atts['key'] );
		if ( ! $post_id || '' === $key ) {
			return '';
		}

		$type = blocklane_pro_ct_field_type( $post_id, $key );

		// Only render fields actually defined for this record's type — never
		// arbitrary or protected post meta (a leading-underscore key, another
		// plugin's stored value, etc.).
		if ( '' === $type ) {
			return '';
		}

		// With an explicit id, don't leak an unpublished record the current viewer
		// isn't allowed to read.
		if ( absint( $atts['id'] ) && 'publish' !== get_post_status( $post_id )
			&& ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		$value = get_post_meta( $post_id, $key, true );

		return blocklane_pro_ct_format_value( $value, $type, true );
	}

	/**
	 * Make the active theme's custom templates (Full Width, With Sidebar, …)
	 * available to our content types, so the post editor offers them as
	 * swap targets. Theme templates declare postTypes (e.g. page/post) that
	 * exclude a freshly-registered CPT; we append our slugs to each.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme.json data.
	 * @return \WP_Theme_JSON_Data
	 */
	function blocklane_pro_ct_extend_custom_templates( $theme_json ) {
		$slugs = array();
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! empty( $type['slug'] ) ) {
				$slugs[] = $type['slug'];
			}
		}
		if ( empty( $slugs ) || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'get_data' ) ) {
			return $theme_json;
		}

		$data = $theme_json->get_data();
		if ( empty( $data['customTemplates'] ) || ! is_array( $data['customTemplates'] ) ) {
			return $theme_json;
		}

		foreach ( $data['customTemplates'] as &$tpl ) {
			$existing       = isset( $tpl['postTypes'] ) && is_array( $tpl['postTypes'] ) ? $tpl['postTypes'] : array();
			$tpl['postTypes'] = array_values( array_unique( array_merge( $existing, $slugs ) ) );
		}
		unset( $tpl );

		return $theme_json->update_with( $data );
	}

	/**
	 * Resolve inline field tokens (<span data-blocklane-field="key">) to the current
	 * record's value, dropping the wrapper. Authored via the editor's "Insert
	 * field" merge-tag format; works in a record's body and in its template.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	function blocklane_pro_ct_resolve_inline_fields( $block_content, $block ) {
		if ( false === strpos( (string) $block_content, 'data-blocklane-field' ) ) {
			return $block_content;
		}

		// Prefer the block's postId context (set inside a Query Loop / post
		// content) so tokens resolve to the looped entry, not the archive's
		// queried object; fall back to the queried object at template level.
		$post_id = ! empty( $block['context']['postId'] )
			? (int) $block['context']['postId']
			: get_queried_object_id();

		return preg_replace_callback(
			// Inner content is plain label text — match [^<]* (not .*?/s) to avoid
			// pathological backtracking and never span across nested markup.
			'/<span\b[^>]*\bdata-blocklane-field="([^"]+)"[^>]*>[^<]*<\/span>/i',
			static function ( $m ) use ( $post_id ) {
				$key = sanitize_key( $m[1] );
				if ( ! $post_id || '' === $key ) {
					return '';
				}
				$type = blocklane_pro_ct_field_type( $post_id, $key );
				// Only resolve real fields on this record's type — never arbitrary
				// or protected post meta.
				if ( '' === $type ) {
					return '';
				}
				$value = get_post_meta( $post_id, $key, true );
				return blocklane_pro_ct_format_value( $value, $type, true );
			},
			$block_content
		);
	}

	/**
	 * Glyph fallback for rating fields bound via core/post-meta. Core bindings can
	 * only emit a string, so a bound paragraph/heading would print the raw number;
	 * here we swap that for plain star glyphs (the rich, styled stars come from the
	 * surfaces we fully control — the shortcode + inline token).
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	function blocklane_pro_ct_resolve_binding_ratings( $block_content, $block ) {
		$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( 'core/paragraph' !== $name && 'core/heading' !== $name ) {
			return $block_content;
		}

		$binding = isset( $block['attrs']['metadata']['bindings']['content'] )
			? $block['attrs']['metadata']['bindings']['content']
			: null;
		if ( empty( $binding['source'] ) || 'core/post-meta' !== $binding['source'] ) {
			return $block_content;
		}

		$key = isset( $binding['args']['key'] ) ? sanitize_key( $binding['args']['key'] ) : '';
		if ( '' === $key ) {
			return $block_content;
		}

		$post_id = ! empty( $block['context']['postId'] )
			? (int) $block['context']['postId']
			: get_queried_object_id();
		if ( ! $post_id || 'rating' !== blocklane_pro_ct_field_type( $post_id, $key ) ) {
			return $block_content;
		}

		$glyphs = blocklane_pro_ct_stars( (int) get_post_meta( $post_id, $key, true ), false );

		// Core replaced the element's inner content with the raw number; swap the
		// inner content of the first paragraph/heading tag for the glyph string.
		return preg_replace(
			'/(<(?:p|h[1-6])\b[^>]*>).*?(<\/(?:p|h[1-6])>)/is',
			'${1}' . $glyphs . '${2}',
			$block_content,
			1
		);
	}

	/**
	 * The first rating field on a record's type that holds a value (1–5), or 0.
	 *
	 * @param int $post_id Record ID.
	 * @return int
	 */
	function blocklane_pro_ct_rating_value( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return 0;
		}
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( ! isset( $type['slug'] ) || $type['slug'] !== $post_type ) {
				continue;
			}
			$fields = ! empty( $type['fields'] ) && is_array( $type['fields'] ) ? $type['fields'] : array();
			foreach ( $fields as $field ) {
				if ( isset( $field['type'], $field['key'] ) && 'rating' === $field['type'] ) {
					$val = (int) get_post_meta( $post_id, $field['key'], true );
					if ( $val > 0 ) {
						return max( 1, min( 5, $val ) );
					}
				}
			}
		}
		return 0;
	}

	/**
	 * Emit schema.org Review JSON-LD on a single CPT entry that has a star rating,
	 * so the rating is eligible for search rich results. Core-block authoring can't
	 * add microdata itemscopes, so structured data lives in the document head.
	 *
	 * Defaults suit testimonials: the entry title is the reviewer (author), the
	 * site is the reviewed Organization, the excerpt is the review body. Filter
	 * `blocklane_pro_ct_review_schema` to remap fields or return an empty value to
	 * suppress the markup for a given record.
	 */
	function blocklane_pro_ct_review_jsonld() {
		if ( ! is_singular() ) {
			return;
		}
		// Never on the site-lock splash: it reuses wp_head() with the hidden
		// request's query state, and this node would carry the hidden entry's
		// review content past the lock (the gate fires the action pre-head).
		if ( did_action( 'blocklane_pro_site_lock_splash' ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$rating = blocklane_pro_ct_rating_value( $post_id );
		if ( ! $rating ) {
			return;
		}

		$data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Review',
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			'author'        => array(
				'@type' => 'Person',
				'name'  => wp_strip_all_tags( get_the_title( $post_id ) ),
			),
			'itemReviewed'  => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'datePublished' => get_post_time( 'c', true, $post_id ),
		);

		$body = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		if ( '' !== $body ) {
			$data['reviewBody'] = $body;
		}

		$data = apply_filters( 'blocklane_pro_ct_review_schema', $data, $post_id );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		// JSON_HEX_TAG escapes < and > so the payload can't break out of <script>.
		echo '<script type="application/ld+json">'
			. wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP )
			. '</script>' . "\n";
	}

	/* ---- Classic-editor fields meta box -----------------------------------
	 * Body-OFF types use the classic editor (no block-editor sidebar), so their
	 * fields are edited through a native meta box here. Body-ON types keep the
	 * block-editor React panel and never reach this code.
	 * --------------------------------------------------------------------- */

	/**
	 * The definition for a post type that edits its fields via the classic meta
	 * box: one of ours, with fields, NOT using the block editor. Else null.
	 *
	 * @param string $post_type Post type.
	 * @return array|null
	 */
	function blocklane_pro_ct_classic_type( $post_type ) {
		if ( ! $post_type || post_type_supports( $post_type, 'editor' ) ) {
			return null; // block-editor types are handled by the React panel
		}
		foreach ( blocklane_pro_ct_definitions() as $type ) {
			if ( isset( $type['slug'] ) && $type['slug'] === $post_type && ! empty( $type['fields'] ) ) {
				return $type;
			}
		}
		return null;
	}

	/**
	 * Register the fields meta box on classic (body-off) entry screens.
	 *
	 * @param string $post_type Current post type.
	 */
	function blocklane_pro_ct_add_field_meta_box( $post_type ) {
		$type = blocklane_pro_ct_classic_type( $post_type );
		if ( ! $type ) {
			return;
		}
		$singular = isset( $type['singular'] ) && '' !== $type['singular'] ? $type['singular'] : ucfirst( $post_type );
		add_meta_box(
			'blocklane-pro-ct-fields',
			/* translators: %s: singular type name. */
			sprintf( _x( '%s fields', 'meta box title', 'blocklane' ), $singular ),
			'blocklane_pro_ct_render_field_meta_box',
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Render one field's control inside the meta box.
	 *
	 * @param array  $field Field def ({ key, label, type }).
	 * @param string $value Current stored value.
	 */
	function blocklane_pro_ct_render_field_control( $field, $value ) {
		$key   = $field['key'];
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';
		$label = isset( $field['label'] ) && '' !== $field['label'] ? $field['label'] : $key;
		$id    = 'blocklane-ct-' . $key;

		// Rating/image are composite widgets with no single labelable control, so
		// they use a group label (aria-labelledby) instead of a <label for> that
		// would point at an element that does not exist.
		$is_group = in_array( $type, array( 'rating', 'image', 'gallery' ), true );

		echo '<p class="blocklane-pro-ct-mb__field">';
		if ( $is_group ) {
			printf(
				'<span class="blocklane-pro-ct-mb__label" id="%s-label">%s</span>',
				esc_attr( $id ),
				esc_html( $label )
			);
		} else {
			printf(
				'<label class="blocklane-pro-ct-mb__label" for="%s">%s</label>',
				esc_attr( $id ),
				esc_html( $label )
			);
		}

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea class="blocklane-pro-ct-mb__input" id="%1$s" name="blocklane_pro_ct[%2$s]" rows="4">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_textarea( $value )
				);
				break;

			case 'rating':
				$current = max( 0, min( 5, (int) $value ) );
				printf( '<span class="blocklane-pro-ct-mb__stars" role="group" aria-labelledby="%s-label">', esc_attr( $id ) );
				printf(
					'<input type="hidden" name="blocklane_pro_ct[%1$s]" value="%2$d" class="blocklane-pro-ct-mb__starval">',
					esc_attr( $key ),
					(int) $current
				);
				for ( $i = 1; $i <= 5; $i++ ) {
					printf(
						'<button type="button" class="blocklane-pro-ct-mb__star%2$s" data-v="%1$d" aria-pressed="%3$s" aria-label="%4$s">★</button>',
						(int) $i,
						$i <= $current ? ' is-on' : '',
						$i === $current ? 'true' : 'false',
						/* translators: %d: number of stars. */
						esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'blocklane' ), $i ) )
					);
				}
				printf(
					'<button type="button" class="button-link blocklane-pro-ct-mb__starclear">%s</button>',
					esc_html__( 'Clear', 'blocklane' )
				);
				echo '</span>';
				break;

			case 'image':
				printf( '<span class="blocklane-pro-ct-mb__image" role="group" aria-labelledby="%s-label">', esc_attr( $id ) );
				printf(
					'<input type="hidden" name="blocklane_pro_ct[%1$s]" value="%2$s" class="blocklane-pro-ct-mb__imageval">',
					esc_attr( $key ),
					esc_url( $value )
				);
				if ( '' !== $value ) {
					printf( '<img src="%s" alt="" class="blocklane-pro-ct-mb__imagepreview">', esc_url( $value ) );
				}
				printf(
					'<button type="button" class="button blocklane-pro-ct-mb__imageselect">%s</button>',
					esc_html( '' !== $value ? __( 'Replace image', 'blocklane' ) : __( 'Select image', 'blocklane' ) )
				);
				printf(
					'<button type="button" class="button-link blocklane-pro-ct-mb__imageclear"%1$s>%2$s</button>',
					'' !== $value ? '' : ' style="display:none"',
					esc_html__( 'Clear', 'blocklane' )
				);
				echo '</span>';
				break;

			case 'gallery':
				$gallery_ids = array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) );
				printf( '<span class="blocklane-pro-ct-mb__gallery" role="group" aria-labelledby="%s-label">', esc_attr( $id ) );
				printf(
					'<input type="hidden" name="blocklane_pro_ct[%1$s]" value="%2$s" class="blocklane-pro-ct-mb__galval">',
					esc_attr( $key ),
					esc_attr( implode( ',', $gallery_ids ) )
				);
				echo '<span class="blocklane-pro-ct-mb__galpreview">';
				foreach ( $gallery_ids as $gallery_id ) {
					$thumb = wp_get_attachment_image( $gallery_id, 'thumbnail' );
					if ( $thumb ) {
						echo '<span class="blocklane-pro-ct-mb__galthumb">' . wp_kses_post( $thumb ) . '</span>';
					}
				}
				echo '</span>';
				printf(
					'<button type="button" class="button blocklane-pro-ct-mb__galselect">%s</button>',
					esc_html( ! empty( $gallery_ids ) ? __( 'Edit gallery', 'blocklane' ) : __( 'Add images', 'blocklane' ) )
				);
				printf(
					'<button type="button" class="button-link blocklane-pro-ct-mb__galclear"%1$s>%2$s</button>',
					! empty( $gallery_ids ) ? '' : ' style="display:none"',
					esc_html__( 'Clear', 'blocklane' )
				);
				echo '</span>';
				break;

			case 'select':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				printf(
					'<select class="blocklane-pro-ct-mb__input" id="%1$s" name="blocklane_pro_ct[%2$s]">',
					esc_attr( $id ),
					esc_attr( $key )
				);
				printf( '<option value="">%s</option>', esc_html__( '— Select —', 'blocklane' ) );
				foreach ( $options as $opt ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $opt ),
						selected( $value, $opt, false ),
						esc_html( $opt )
					);
				}
				echo '</select>';
				break;

			default:
				$input_types = array(
					'email'  => 'email',
					'url'    => 'url',
					'phone'  => 'tel',
					'number' => 'number',
					'date'   => 'date',
					'time'   => 'time',
				);
				$itype = isset( $input_types[ $type ] ) ? $input_types[ $type ] : 'text';
				printf(
					'<input type="%1$s" class="blocklane-pro-ct-mb__input" id="%2$s" name="blocklane_pro_ct[%3$s]" value="%4$s">',
					esc_attr( $itype ),
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( $value )
				);
				break;
		}

		echo '</p>';
	}

	/**
	 * Render the fields meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	function blocklane_pro_ct_render_field_meta_box( $post ) {
		$type = blocklane_pro_ct_classic_type( $post->post_type );
		if ( ! $type ) {
			return;
		}
		wp_nonce_field( 'blocklane_pro_ct_fields_' . $post->ID, 'blocklane_pro_ct_fields_nonce' );
		echo '<div class="blocklane-pro-ct-mb">';
		foreach ( $type['fields'] as $field ) {
			if ( empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
				continue;
			}
			$value = get_post_meta( $post->ID, $field['key'], true );
			blocklane_pro_ct_render_field_control( $field, is_scalar( $value ) ? (string) $value : '' );
		}
		echo '</div>';
	}

	/**
	 * Save the classic meta box field values. Nonce + capability gated; each
	 * value is sanitized by its field type's callback (same as the REST path).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	function blocklane_pro_ct_save_fields( $post_id, $post ) {
		if ( ! isset( $_POST['blocklane_pro_ct_fields_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blocklane_pro_ct_fields_nonce'] ) ), 'blocklane_pro_ct_fields_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$type = blocklane_pro_ct_classic_type( $post->post_type );
		if ( ! $type ) {
			return;
		}

		// Field inputs are namespaced under blocklane_pro_ct[<key>] so a field keyed
		// e.g. "excerpt"/"status"/"action" cannot collide with WP's own form fields.
		$posted = isset( $_POST['blocklane_pro_ct'] ) && is_array( $_POST['blocklane_pro_ct'] )
			? wp_unslash( $_POST['blocklane_pro_ct'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitized by its per-type callback below.
			: array();

		foreach ( $type['fields'] as $field ) {
			if ( empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
				continue;
			}
			$key = $field['key'];
			if ( ! isset( $posted[ $key ] ) || ! is_scalar( $posted[ $key ] ) ) {
				continue;
			}
			$raw       = $posted[ $key ];
			$sanitizer = blocklane_pro_ct_field_sanitizer( $field );
			$value     = is_callable( $sanitizer ) ? call_user_func( $sanitizer, (string) $raw ) : sanitize_text_field( (string) $raw );
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Inline CSS + JS for the meta box (star widget + media picker), plus the
	 * media library, on our classic entry screens only.
	 *
	 * @param string $hook Current admin page.
	 */
	function blocklane_pro_ct_metabox_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! blocklane_pro_ct_classic_type( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_media();

		$css = '.blocklane-pro-ct-mb__field{margin:0 0 16px}'
			. '.blocklane-pro-ct-mb__label{display:block;font-weight:600;margin-bottom:4px}'
			. '.blocklane-pro-ct-mb__input{width:100%;max-width:480px}'
			. '.blocklane-pro-ct-mb__stars{display:inline-flex;align-items:center;gap:2px}'
			. '.blocklane-pro-ct-mb__star{background:none;border:0;padding:2px;font-size:22px;line-height:1;cursor:pointer;color:#c3c4c7}'
			. '.blocklane-pro-ct-mb__star.is-on{color:#f5b301}'
			. '.blocklane-pro-ct-mb__starclear,.blocklane-pro-ct-mb__imageclear,.blocklane-pro-ct-mb__galclear{margin-left:8px;color:#b32d2e;cursor:pointer}'
			. '.blocklane-pro-ct-mb__imagepreview{display:block;max-width:160px;height:auto;margin-bottom:8px;border-radius:4px}'
			. '.blocklane-pro-ct-mb__galpreview{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}'
			. '.blocklane-pro-ct-mb__galthumb img{display:block;width:60px;height:60px;object-fit:cover;border-radius:4px}';
		wp_add_inline_style( 'wp-admin', $css );

		// The meta box behavior lives in a file beside this one (the wordpress.org
		// review refuses heredoc/nowdoc PHP, checklist B5); it depends on the media
		// editor so it loads after the l10n object below.
		wp_enqueue_script(
			'blocklane-pro-ct-meta-box',
			plugins_url( 'meta-box.js', __FILE__ ),
			array( 'media-editor' ),
			(string) filemtime( __DIR__ . '/meta-box.js' ),
			true
		);
		$l10n = wp_json_encode(
			array(
				'selectImages' => __( 'Select images', 'blocklane' ),
				'editGallery'  => __( 'Edit gallery', 'blocklane' ),
				'addImages'    => __( 'Add images', 'blocklane' ),
			)
		);
		wp_add_inline_script( 'media-editor', 'window.blocklaneProCtL10n=' . $l10n . ';', 'before' );
	}

	add_action( 'init', 'blocklane_pro_ct_register' );
	add_filter( 'rest_post_dispatch', 'blocklane_pro_ct_hide_gallery_meta_schema', 10, 3 );
	add_filter( 'allowed_block_types_all', 'blocklane_pro_ct_restrict_field_gallery', 20, 2 );
	add_shortcode( 'blocklane-field', 'blocklane_pro_ct_shortcode' );
	add_filter( 'wp_theme_json_data_theme', 'blocklane_pro_ct_extend_custom_templates' );
	add_filter( 'render_block', 'blocklane_pro_ct_resolve_inline_fields', 10, 2 );
	add_filter( 'render_block', 'blocklane_pro_ct_resolve_binding_ratings', 10, 2 );
	add_action( 'wp_head', 'blocklane_pro_ct_review_jsonld' );
	add_action( 'add_meta_boxes', 'blocklane_pro_ct_add_field_meta_box' );
	add_action( 'save_post', 'blocklane_pro_ct_save_fields', 10, 2 );
	add_action( 'admin_enqueue_scripts', 'blocklane_pro_ct_metabox_assets' );
}
