<?php
/**
 * Transparent Header — overlay a header group on the first section of the
 * page. Since 0.11.0 the background is a TRI-STATE, resolved by
 * blocklane_pro_transparent_header_background():
 *
 *   'solid-on-scroll' (the default) — stripped at the top of the page, pinned,
 *       and its own background returns once the page scrolls past it.
 *   'transparent'                   — stripped and never solidified; the header
 *       scrolls away with the page. Pre-0.11.0 headers that turned "Solid on
 *       scroll" OFF map here, because that setting meant overlaid-and-never-
 *       solidified. Mapping them to 'solid' would repaint every one of them.
 *   'solid'                         — the Floating Header: painted from the
 *       start and overlaid anyway, a bar floating over the page rather than a
 *       stripped one.
 *
 * This is the front-end half: it adds the state classes at render and
 * enqueues the behavior script only on pages that actually render a
 * transparent header. The overlay targets ONE group per header part — the
 * group carrying the attribute, or the part's first group when a page
 * override lights a header whose own setting is off (see the target resolver
 * below). The visual CSS rides the shared style-index.css bundle (it must be
 * in the head — a footer-printed stylesheet would flash the header solid —
 * and the same rules paint the editor canvas). The editor controls live in
 * src/controls/transparent-header/.
 *
 * @package blocklane_pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the per-page override meta.
 *
 * Tri-state, not a boolean opt-out: '' inherits the header's own setting, 'on'
 * forces the overlay for this page, 'off' forces a solid header. That lets the
 * two settings override each other in BOTH directions — transparent globally
 * with a few solid pages, or solid globally with a few transparent ones — from
 * one control instead of a one-way escape hatch.
 *
 * Page-scoped: templates with no singular page (blog, archives) have nothing to
 * hang a per-page choice on, and opt out by swapping in a solid header part.
 */
function blocklane_pro_transparent_header_register_meta() {
	register_post_meta(
		'page',
		'blocklane_pro_transparent_header',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => function ( $value ) {
				return in_array( $value, array( 'on', 'off' ), true ) ? $value : '';
			},
			'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);

	// The pre-tri-state boolean opt-out. Still registered so the editor can
	// read it (and so an un-migrated value stays visible to REST), but the
	// resolver below treats it as read-only history.
	register_post_meta(
		'page',
		'blocklane_pro_solid_header',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
add_action( 'init', 'blocklane_pro_transparent_header_register_meta' );

/**
 * This page's override: 'on', 'off', or '' to inherit the header's setting.
 *
 * @param int|null $page_id Page to read; defaults to the queried page.
 * @return string One of 'on', 'off', ''.
 */
function blocklane_pro_transparent_header_page_override( $page_id = null ) {
	if ( null === $page_id ) {
		// is_page() is false on the assigned Posts page even though that page
		// is a real page with real meta, and the editor happily shows the
		// control there and saves it — so gating on is_page() alone let a
		// saved override be silently ignored on exactly one page of the site.
		// is_home() covers it: on a static-front-page site it IS the posts
		// page, and page_for_posts names it.
		if ( is_page() ) {
			$page_id = get_queried_object_id();
		} elseif ( is_home() && ! is_front_page() ) {
			$page_id = (int) get_option( 'page_for_posts' );
		} else {
			return '';
		}
	}
	if ( ! $page_id ) {
		return '';
	}

	$value = (string) get_post_meta( $page_id, 'blocklane_pro_transparent_header', true );
	if ( 'on' === $value || 'off' === $value ) {
		return $value;
	}

	// Pages saved before the tri-state existed carry our old boolean opt-out.
	// Read it as 'off' rather than bulk-migrating: reading needs no writes,
	// and the editor rewrites the row only when this page makes an explicit
	// choice — so uninstall at any moment leaves nothing half-migrated.
	if ( get_post_meta( $page_id, 'blocklane_pro_solid_header', true ) ) {
		return 'off';
	}

	return '';
}

/**
 * Whether the overlay applies to this request, given the header's own setting.
 *
 * @param bool $header_default The header group's own transparent setting.
 * @return bool
 */
function blocklane_pro_transparent_header_is_active( $header_default ) {
	$override = blocklane_pro_transparent_header_page_override();
	if ( 'on' === $override ) {
		return true;
	}
	if ( 'off' === $override ) {
		return false;
	}
	return (bool) $header_default;
}

/**
 * The overlaid header's background mode, resolved from the target's attrs.
 *
 * Three values, and only the third is new:
 *
 *   'solid-on-scroll' — transparent at the top, the group's own background
 *                       restored once scrolled past. The shipped default.
 *   'transparent'     — overlaid and never solidified.
 *   'solid'           — overlaid and always painted with the group's own
 *                       background. The floating bar: it exists so a header
 *                       can leave the flow (pulling the hero up underneath
 *                       it) WITHOUT going transparent, which is the only
 *                       thing the overlay was previously able to offer.
 *
 * `'solid'` deliberately stays `position: absolute` — the stylesheet promotes
 * only `blocklane-pro-th-solidify` to `fixed`. That is what lets Advanced
 * Group keep pinning and sliding the <header> wrapper underneath it: a
 * transform on the wrapper hijacks `position: fixed` descendants (the
 * 2026-07-26 admin-bar double-offset), but an absolutely positioned group is
 * already anchored to that wrapper and rides it untouched.
 *
 * @param array<string, mixed>|null $target The captured target group's attributes.
 * @return string One of 'solid-on-scroll', 'transparent', 'solid'.
 */
function blocklane_pro_transparent_header_background( $target ) {
	$mode = is_array( $target ) ? ( $target['blocklaneProThBackground'] ?? '' ) : '';
	if ( 'transparent' === $mode || 'solid' === $mode ) {
		return $mode;
	}

	// Pre-0.11.0 headers carry the boolean this select replaced. Read it, never
	// write it — the `blocklane_pro_solid_header` precedent above: reading needs
	// no writes, the editor rewrites the attribute only when this header makes
	// an explicit choice, and uninstall at any moment leaves nothing half
	// migrated.
	//
	// The mapping is the load-bearing line. "Solid on scroll: off" meant
	// OVERLAID AND NEVER SOLIDIFIED — i.e. permanently transparent — so it maps
	// to 'transparent', NOT to the new 'solid'. Mapping it to 'solid' would
	// repaint every header that ever turned the toggle off, including the ones
	// faking a solid bar by setting the Transparent Background scrim to an
	// opaque color (that works today precisely because the transparent state
	// never reverts).
	if ( is_array( $target )
		&& isset( $target['blocklaneProTransparentSolidOnScroll'] )
		&& empty( $target['blocklaneProTransparentSolidOnScroll'] ) ) {
		return 'transparent';
	}

	// Default. The attribute is only serialized when set explicitly, so an
	// absent value and an absent legacy boolean both land here.
	return 'solid-on-scroll';
}

/**
 * Whether the overlaid group paints a background of its own.
 *
 * Used only by the always-solid mode, to decide whether the group is a visible
 * bar or just empty space around an inner one. A group that paints nothing is
 * invisible but still occupies its full width and height over the page below,
 * so it must not intercept the pointer there.
 *
 * Deliberately NOT applied to the transparent modes. Their painted-ness flips
 * at run time (the scrim, and the group's own background returning on scroll),
 * so a render-time class would be wrong half the time — and their behavior today
 * is not something this change should alter.
 *
 * @param array<string, mixed>|null $target The captured target group's attributes.
 * @return bool Whether the group paints its own background.
 */
function blocklane_pro_transparent_header_group_paints( $target ) {
	if ( ! is_array( $target ) ) {
		return false;
	}

	if ( ! empty( $target['backgroundColor'] ) || ! empty( $target['gradient'] ) ) {
		return true;
	}

	$color = $target['style']['color'] ?? array();
	if ( ! empty( $color['background'] ) || ! empty( $color['gradient'] ) ) {
		return true;
	}

	// Core's background-image support (theme.json `background`), which paints
	// just as opaquely as a color does.
	return ! empty( $target['style']['background']['backgroundImage'] );
}

/**
 * The cached header-part index: the registered header-area slugs, plus per
 * part the attrs of the group the overlay targets.
 *
 * Cached in a transient, not per request: get_block_templates() runs a
 * taxonomy-joined query plus a theme-directory scan, and the target scan
 * parses part content — too much to pay on every front-end view when the
 * answer only changes on an edit (the front-page budget is two env queries).
 * The key carries theme + version so a switch or update can never serve
 * another theme's parts; saves/deletes of parts and synced patterns flush it,
 * and the expiry is the backstop for file-level edits that fire no hook.
 *
 * @return array { 'key' => string, 'slugs' => string[]|null, 'targets' => array }
 */
function blocklane_pro_transparent_header_cache_read() {
	$key    = get_stylesheet() . ':' . (string) wp_get_theme()->get( 'Version' );
	$stored = get_transient( 'blocklane_pro_th_parts_v2' );

	if ( is_array( $stored )
		&& ( $stored['key'] ?? null ) === $key
		&& is_array( $stored['targets'] ?? null )
		&& ( null === ( $stored['slugs'] ?? null ) || is_array( $stored['slugs'] ) ) ) {
		return $stored;
	}

	return array(
		'key'     => $key,
		'slugs'   => null,
		'targets' => array(),
	);
}

/**
 * Store the header-part index. With an expiry, so it is never autoloaded.
 *
 * @param array $cache The index (see blocklane_pro_transparent_header_cache_read()).
 */
function blocklane_pro_transparent_header_cache_write( $cache ) {
	set_transient( 'blocklane_pro_th_parts_v2', $cache, DAY_IN_SECONDS );
}

/**
 * Drop the cached header-part index. Hooked to everything that changes what
 * the index describes: template-part saves/deletes (which parts are header
 * area, what they contain), synced-pattern saves/deletes (the header group
 * usually lives behind a reference), and theme switches.
 */
function blocklane_pro_transparent_header_flush_cache() {
	delete_transient( 'blocklane_pro_th_parts_v2' );
	delete_transient( 'blocklane_pro_th_parts' );
}
add_action( 'save_post_wp_template_part', 'blocklane_pro_transparent_header_flush_cache' );
add_action( 'save_post_wp_block', 'blocklane_pro_transparent_header_flush_cache' );
add_action( 'switch_theme', 'blocklane_pro_transparent_header_flush_cache' );

/**
 * Flush the index when a part or synced pattern is trashed or deleted —
 * deleting a customization reverts the part to its theme file, which the
 * index must re-read.
 *
 * @param int $post_id Post being removed.
 */
function blocklane_pro_transparent_header_flush_on_delete( $post_id ) {
	$post = get_post( $post_id );
	if ( $post && in_array( $post->post_type, array( 'wp_template_part', 'wp_block' ), true ) ) {
		blocklane_pro_transparent_header_flush_cache();
	}
}
add_action( 'deleted_post', 'blocklane_pro_transparent_header_flush_on_delete' );
add_action( 'trashed_post', 'blocklane_pro_transparent_header_flush_on_delete' );

/**
 * Slugs of the template parts registered in the header area.
 *
 * The header part is referenced by slug in the template
 * (`{"slug":"header","tagName":"header"}`) with no area attribute, so the
 * area has to come from the registered parts.
 *
 * @return string[]
 */
function blocklane_pro_transparent_header_header_slugs() {
	static $slugs = null;
	if ( null !== $slugs ) {
		return $slugs;
	}

	$cache = blocklane_pro_transparent_header_cache_read();
	if ( is_array( $cache['slugs'] ) ) {
		$slugs = $cache['slugs'];
		return $slugs;
	}

	$slugs = array();
	if ( function_exists( 'get_block_templates' ) ) {
		foreach ( get_block_templates( array( 'area' => 'header' ), 'wp_template_part' ) as $part ) {
			if ( ! empty( $part->slug ) ) {
				$slugs[] = (string) $part->slug;
			}
		}
	}

	$cache['slugs'] = $slugs;
	blocklane_pro_transparent_header_cache_write( $cache );

	return $slugs;
}

/**
 * Whether a template-part slug is a header-area part.
 *
 * @param string $slug Template part slug.
 * @return bool
 */
function blocklane_pro_transparent_header_is_header_slug( $slug ) {
	return '' !== (string) $slug
		&& in_array( (string) $slug, blocklane_pro_transparent_header_header_slugs(), true );
}

/**
 * The attrs of the group the overlay targets in a header part: the first
 * group (document order) carrying the transparent-header attribute, or the
 * part's first group when none does.
 *
 * The attribute-carrying group is preferred because that is the group the
 * user configured — a header with an announcement bar above the header row
 * has two top-level groups, and "the first one" would silently be the bar.
 * The positional fallback exists for the opposite reason: a page override of
 * 'on' must be able to light a header whose own setting is off, and with the
 * attribute absent nothing else identifies the group.
 *
 * Resolved from the part's PARSED content before it renders, not discovered
 * as rendering goes, because the answer is needed mid-render: advanced-group
 * asks "does the overlay govern this group?" from each group's own render
 * pass — before later siblings have rendered — so a discovered answer would
 * be wrong for any group preceding the real target.
 *
 * @param string $slug  Header part slug.
 * @param string $theme Theme the part reference names; defaults to the active theme.
 * @return array|null Target group attrs, or null when the part has no group.
 */
function blocklane_pro_transparent_header_target_attrs( $slug, $theme = '' ) {
	static $targets = array();

	$theme = '' !== (string) $theme ? (string) $theme : get_stylesheet();
	$id    = $theme . '//' . (string) $slug;

	if ( array_key_exists( $id, $targets ) ) {
		return $targets[ $id ];
	}

	$cache = blocklane_pro_transparent_header_cache_read();
	if ( array_key_exists( $id, $cache['targets'] ) ) {
		$targets[ $id ] = $cache['targets'][ $id ];
		return $targets[ $id ];
	}

	$content = '';
	if ( function_exists( 'get_block_template' ) ) {
		$template = get_block_template( $id, 'wp_template_part' );
		if ( $template && ! empty( $template->content ) ) {
			$content = (string) $template->content;
		}
	}

	$first      = null;
	$attributed = null;
	$n          = 0;
	$first_n    = null;
	$hit_n      = null;
	if ( '' !== $content ) {
		$attributed = blocklane_pro_transparent_header_scan_target(
			parse_blocks( $content ),
			$first,
			array( 'part:' . (string) $slug => true ),
			$n,
			$first_n,
			$hit_n
		);
	}

	$attrs_out = null !== $attributed ? $attributed : $first;
	$targets[ $id ] = null === $attrs_out
		? null
		: array(
			'attrs'   => $attrs_out,
			// Which group, counting in document order — see the scan.
			'ordinal' => null !== $attributed ? $hit_n : $first_n,
		);

	$cache['targets'][ $id ] = $targets[ $id ];
	blocklane_pro_transparent_header_cache_write( $cache );

	return $targets[ $id ];
}

/**
 * Depth-first scan of parsed blocks for the overlay's target group.
 *
 * Returns the first group carrying the attribute; records the first group of
 * any kind in $first as the positional fallback. Pattern, synced-pattern and
 * nested-part references are expanded in place — they render their content
 * inline, so their groups count exactly as if written into the part (this
 * theme's header part is a single wp:pattern reference). $seen guards
 * reference cycles per chain, like core's own render-time guard.
 *
 * @param array      $blocks Parsed blocks.
 * @param array|null $first  First group's attrs, collected by reference.
 * @param array      $seen   Reference ids already expanded on this chain.
 * @return array|null Attrs of the first attribute-carrying group, or null.
 */
function blocklane_pro_transparent_header_scan_target( $blocks, &$first, $seen = array(), &$n = null, &$first_n = null, &$hit_n = null ) {
	foreach ( (array) $blocks as $block ) {
		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( 'core/group' === $name ) {
			// Ordinal among groups in document order. This is the target's
			// IDENTITY — comparing attribute arrays by value cannot tell two
			// byte-identical groups apart, and in the positional-fallback case
			// the captured attrs are a plain layout array that a sibling can
			// easily match exactly.
			++$n;
			if ( null === $first ) {
				$first   = $attrs;
				$first_n = $n;
			}
			if ( ! empty( $attrs['blocklaneProTransparentHeader'] ) ) {
				$hit_n = $n;
				return $attrs;
			}
		}

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();

		if ( 'core/pattern' === $name ) {
			$inner = array();
			$ref   = 'pattern:' . (string) ( $attrs['slug'] ?? '' );
			if ( ! empty( $attrs['slug'] ) && ! isset( $seen[ $ref ] ) && class_exists( 'WP_Block_Patterns_Registry' ) ) {
				$seen[ $ref ] = true;
				$pattern      = WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $attrs['slug'] );
				if ( ! empty( $pattern['content'] ) ) {
					$inner = parse_blocks( (string) $pattern['content'] );
				}
			}
		} elseif ( 'core/block' === $name ) {
			$inner = array();
			$ref   = 'block:' . (int) ( $attrs['ref'] ?? 0 );
			if ( ! empty( $attrs['ref'] ) && ! isset( $seen[ $ref ] ) ) {
				$seen[ $ref ] = true;
				$reusable     = get_post( (int) $attrs['ref'] );
				if ( $reusable instanceof WP_Post && 'wp_block' === $reusable->post_type ) {
					$inner = parse_blocks( (string) $reusable->post_content );
				}
			}
		} elseif ( 'core/template-part' === $name ) {
			$inner = array();
			$ref   = 'part:' . (string) ( $attrs['slug'] ?? '' );
			if ( ! empty( $attrs['slug'] ) && ! isset( $seen[ $ref ] ) && function_exists( 'get_block_template' ) ) {
				$seen[ $ref ] = true;
				$nested_theme = ! empty( $attrs['theme'] ) ? (string) $attrs['theme'] : get_stylesheet();
				$nested       = get_block_template( $nested_theme . '//' . (string) $attrs['slug'], 'wp_template_part' );
				if ( $nested && ! empty( $nested->content ) ) {
					$inner = parse_blocks( (string) $nested->content );
				}
			}
		}

		if ( $inner ) {
			$found = blocklane_pro_transparent_header_scan_target( $inner, $first, $seen, $n, $first_n, $hit_n );
			if ( null !== $found ) {
				return $found;
			}
		}
	}

	return null;
}

/**
 * Per-request state for the header part currently rendering.
 *
 * The overlay used to be applied from the group's own render filter, keyed off
 * its attribute. That made the attribute do two jobs — marking WHICH group is
 * the header, and saying whether it is transparent — so a header switched off
 * could not be switched on for a single page: nothing identified the group any
 * more.
 *
 * Identification now comes from the target resolver: `pre_render_block` opens
 * the part with the scan's target attrs, attribute-carrying groups mark their
 * wrapper tag on the way through, and the part's own render filter applies
 * the result and closes. `depth` handles header-area parts nested inside each
 * other — only the outermost open resolves a target and only the outermost
 * close applies it, so a nested part can no longer wipe the outer capture.
 *
 * CROSS-FILE CONTRACT: advanced-group's
 * blocklane_pro_advanced_group_transparent_header_active() reads 'inside' and
 * compares 'captured' (the target attrs) against the attrs of the group it is
 * rendering, to skip the sticky pass on exactly the group the overlay will
 * take over. Keep those two keys, and keep 'captured' resolved at open — its
 * answer is consumed before later siblings have rendered.
 *
 * @param string     $op    'open', 'mark', 'retarget', 'read' or 'close'.
 * @param array|null $attrs Target attrs for 'open'/'retarget'.
 * @return array|bool Current state for 'read'; whether the caller may mark
 *                    for 'mark'; otherwise true.
 */
function blocklane_pro_transparent_header_part_state( $op, $attrs = null ) {
	static $state = array(
		'depth'    => 0,
		'inside'   => false,
		'captured' => null,
		'ordinal'  => null,
		'seen'     => 0,
		'marked'   => 0,
	);

	switch ( $op ) {
		case 'open':
			++$state['depth'];
			if ( 1 === $state['depth'] ) {
				$state['inside']   = true;
				$state['captured'] = isset( $attrs['attrs'] ) && is_array( $attrs['attrs'] ) ? $attrs['attrs'] : null;
				$state['ordinal']  = isset( $attrs['ordinal'] ) ? (int) $attrs['ordinal'] : null;
				$state['seen']     = 0;
				$state['marked']   = 0;
			}
			break;
		case 'visit':
			// Called once per group, in document order, from render_block_data.
			// Returns true for the one whose ordinal the scan resolved — the
			// target's IDENTITY. Comparing attribute arrays by value could not
			// tell two byte-identical groups apart.
			if ( ! $state['inside'] || null === $state['ordinal'] ) {
				return false;
			}
			++$state['seen'];
			return $state['seen'] === $state['ordinal'];
		case 'mark':
			if ( ! $state['inside'] ) {
				return false;
			}
			++$state['marked'];
			break;
		case 'retarget':
			if ( $state['inside'] && is_array( $attrs ) ) {
				$state['captured'] = $attrs;
			}
			break;
		case 'close':
			if ( $state['depth'] > 0 ) {
				--$state['depth'];
			}
			if ( 0 === $state['depth'] ) {
				$state = array(
					'depth'    => 0,
					'inside'   => false,
					'captured' => null,
					'ordinal'  => null,
					'seen'     => 0,
					'marked'   => 0,
				);
			}
			break;
		case 'read':
			return $state;
	}

	return true;
}

/**
 * Open the header-part scope before its content renders.
 *
 * Runs LAST on pre_render_block: any earlier filter may short-circuit the
 * part, in which case its render filter never fires and a scope opened here
 * would leak into the rest of the request. Opening only once the chain's
 * final word is in keeps the depth counter honest.
 *
 * @param string|null $pre_render Short-circuit value (passed through untouched).
 * @param array       $block      The parsed block.
 * @return string|null
 */
function blocklane_pro_transparent_header_open_part( $pre_render, $block ) {
	if ( null !== $pre_render ) {
		return $pre_render;
	}

	if ( 'core/template-part' === ( $block['blockName'] ?? '' )
		&& blocklane_pro_transparent_header_is_header_slug( $block['attrs']['slug'] ?? '' ) ) {
		// A nested header part renders inside the outermost scope, which
		// already owns the target — only the outermost open resolves one.
		$state  = blocklane_pro_transparent_header_part_state( 'read' );
		$target = null;
		if ( 0 === $state['depth'] ) {
			$target = blocklane_pro_transparent_header_target_attrs(
				(string) $block['attrs']['slug'],
				(string) ( $block['attrs']['theme'] ?? '' )
			);
		}
		blocklane_pro_transparent_header_part_state( 'open', $target );
	}

	return $pre_render;
}
add_filter( 'pre_render_block', 'blocklane_pro_transparent_header_open_part', PHP_INT_MAX, 2 );

/**
 * Follow the target through render-time attribute rewrites.
 *
 * The scan captured the target's attrs as PARSED, but render_block_data
 * filters may rewrite a group's attrs before it renders — and advanced-group
 * compares the state's captured attrs against the attrs IT is handed at
 * render, so the two must drift together or the comparison (and the overlay's
 * own settings) go stale.
 *
 * It receives ONE argument. add_filter() below registers no accepted-args
 * count, so render_block_data passes only the (possibly rewritten) parsed
 * block; an earlier version of this note described a second $source_block
 * parameter that the function has never declared or been handed. The target is
 * identified from the parsed block itself. Runs late so it sees the other
 * filters' final word.
 *
 * @param array $parsed_block The (possibly rewritten) parsed block.
 * @return array
 */
function blocklane_pro_transparent_header_track_target( $parsed_block ) {
	if ( 'core/group' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}

	if ( true === blocklane_pro_transparent_header_part_state( 'visit' ) ) {
		// Private and render-only: render_block_data shapes this pass, never
		// saved content, and core/group does not emit unknown attributes. Both
		// the marker filter and advanced-group's suppression read THIS rather
		// than comparing attribute arrays, so two byte-identical groups are
		// still two different elements.
		$parsed_block['attrs']['blocklaneProThIsTarget'] = true;

		// Keep the captured attrs in step with any rewrite a later filter made
		// (the core-7.1 idiom branch materializes attributes on this hook).
		blocklane_pro_transparent_header_part_state( 'retarget', $parsed_block['attrs'] );
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'blocklane_pro_transparent_header_track_target', PHP_INT_MAX );

/**
 * Mark the attribute-carrying group's wrapper while the header part renders.
 *
 * The target resolver decides WHICH group the overlay lands on; this pins
 * down WHERE its tag sits in the part's rendered output. Render order is
 * children-first, so by the time the part's own filter runs, the group's
 * markup is just bytes inside a bigger string — the marker class, added while
 * the group is still its own string, is what survives the flattening. The
 * part filter overlays the first marked tag (document order, the same order
 * the scan resolved its target in) and strips every marker before output, so
 * the class never reaches the page.
 *
 * @param string $block_content The rendered group.
 * @param array  $block         The parsed block.
 * @return string
 */
function blocklane_pro_transparent_header_mark_group( $block_content, $block ) {
	if ( empty( $block['attrs']['blocklaneProThIsTarget'] )
		|| empty( $block['attrs']['blocklaneProTransparentHeader'] ) ) {
		return $block_content;
	}
	if ( true !== blocklane_pro_transparent_header_part_state( 'mark' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	while ( $processor->next_tag() ) {
		if ( true === $processor->has_class( 'wp-block-group' ) ) {
			$processor->add_class( 'blocklane-pro-th-target' );
			return $processor->get_updated_html();
		}
	}

	return $block_content;
}
add_filter( 'render_block_core/group', 'blocklane_pro_transparent_header_mark_group', 10, 2 );

/**
 * Apply the overlay to the header template part at render.
 *
 * Runs on the PART, not the group, because the part is what identifies the
 * header. The target group's tag is the first marked tag when the target
 * carries the attribute (the marker filter above), and the first
 * `wp-block-group` tag on the positional fallback; its settings — solid on
 * scroll, top offset, z-index — come from the attrs the target resolver
 * captured at open.
 *
 * The server renders the at-top state (transparent) so the first paint is
 * right; the behavior script swaps the state classes on scroll when "Solid on
 * scroll" is enabled.
 *
 * @param string $block_content The rendered part.
 * @param array  $block         The parsed block.
 * @return string Modified content.
 */
function blocklane_pro_transparent_header_render_part( $block_content, $block ) {
	if ( ! blocklane_pro_transparent_header_is_header_slug( $block['attrs']['slug'] ?? '' ) ) {
		return $block_content;
	}

	$state = blocklane_pro_transparent_header_part_state( 'read' );
	if ( 0 === $state['depth'] ) {
		return $block_content;
	}

	blocklane_pro_transparent_header_part_state( 'close' );

	// A nested header part is bytes inside the outermost part's output; the
	// outermost pass owns the overlay, and any markers ride along to it.
	if ( $state['depth'] > 1 ) {
		return $block_content;
	}

	if ( '' === trim( (string) $block_content ) ) {
		return $block_content;
	}

	$target = is_array( $state['captured'] ) ? $state['captured'] : null;
	$marked = (int) $state['marked'];

	// The header's own setting is the site default; the page may override it
	// either way.
	$active = null !== $target
		&& blocklane_pro_transparent_header_is_active( ! empty( $target['blocklaneProTransparentHeader'] ) );

	// Inactive with no markers to strip: nothing to do. (Markers can exist on
	// an inactive render — a page override of 'off' — and must not ship.)
	if ( ! $active && 0 === $marked ) {
		return $block_content;
	}

	// When the target carries the attribute, the marker is the ONLY acceptable
	// landing site. If nothing was marked, the target group did not render at
	// all (a pre_render_block short-circuit on it skips the render filters),
	// and falling back to the first tag would overlay a different element
	// wearing the target's settings — #10's wrong-element failure through a
	// side door. Overlay nothing instead; the header the author configured is
	// not on this page.
	$attributed = ! empty( $target['blocklaneProTransparentHeader'] );
	if ( $attributed && 0 === $marked ) {
		return $block_content;
	}

	$use_marker = $marked > 0 && $attributed;

	// Three modes, one resolver (shared with advanced-group's suppression check
	// so the two cannot disagree about what this header is).
	$background = blocklane_pro_transparent_header_background( $target );

	// Only 'solid-on-scroll' has a state the behavior script must swap, and
	// `blocklane-pro-th-solidify` is what the stylesheet promotes to
	// `position: fixed`. Withholding it in 'solid' is what keeps the floating
	// bar `absolute` and therefore compatible with advanced-group's slide.
	$solidify = $active && 'solid-on-scroll' === $background;

	// 'solid' paints with the group's OWN background, so the at-top state is
	// never entered. Every transparent-state rule — the background strip here
	// and every per-block color in blocks.php — is scoped under
	// `.is-th-transparent`, so withholding this one class turns all of them off
	// together, which is exactly right for a solid header.
	$transparent = $active && 'solid' !== $background;

	$processor = new WP_HTML_Tag_Processor( $block_content );
	$stripped  = 0;
	$applied   = false;

	while ( $processor->next_tag() ) {
		$is_marked = false;
		if ( $stripped < $marked && true === $processor->has_class( 'blocklane-pro-th-target' ) ) {
			$processor->remove_class( 'blocklane-pro-th-target' );
			++$stripped;
			$is_marked = true;
		}

		if ( ! $applied && $active
			&& ( $use_marker ? $is_marked : true === $processor->has_class( 'wp-block-group' ) ) ) {
			$processor->add_class( 'blocklane-pro-transparent-header' );
			if ( $transparent ) {
				$processor->add_class( 'is-th-transparent' );
			} else {
				// A POSITIVE marker for the always-solid mode, because absence
				// is not usable here: `is-th-transparent` is toggled at runtime
				// by the behavior script, so "no transparent class" describes a
				// scrolled solid-on-scroll header just as well as this mode, and
				// a guard reading it would be timing-dependent. Advanced Group's
				// behavior script keys on this to decide whether the group is
				// pinnable (see its initStickyScrollBehavior).
				//
				// Named `opaque` and not `solid` purely so it cannot be misread
				// as `blocklane-pro-th-solidify`, which means something else and
				// is set two lines from here.
				$processor->add_class( 'blocklane-pro-th-opaque' );

				// An overlaid group spans the full width at its full height, so
				// it sits over the top of whatever it overlays and swallows the
				// pointer there — measured: a floating-dock header intercepted
				// every click across a 1440x104 band, including well outside the
				// visible bar, because the bar is an INNER group and the overlaid
				// group around it is empty space.
				//
				// Only when the group paints nothing itself: if it has its own
				// background the bar really is that wide, and clicks landing on
				// it should stop there rather than falling through to the hero.
				if ( ! blocklane_pro_transparent_header_group_paints( $target ) ) {
					$processor->add_class( 'blocklane-pro-th-nobg' );
				}
			}
			if ( $solidify ) {
				$processor->add_class( 'blocklane-pro-th-solidify' );
			}

			$vars = array();

			// The transparent-state background/scrim rides a custom property
			// consumed only by .is-th-transparent-chained rules, so the solid
			// state — the group's own core colors — is untouched by
			// construction. The group-level text color and logo treatment are
			// RETIRED (read-never-write, the blocklane_pro_solid_header
			// pattern): text and logo styling is per-block now — see
			// blocks.php and spec 2026-08-20-transparent-header-block-
			// extensions. Stored values stay inert in the comment JSON.
			// Withheld in 'solid': the var is consumed only by the
			// `.is-th-transparent` rule, which that mode never carries, so
			// emitting it would be dead markup — and dead markup that reads as
			// if the scrim were live is how the next person concludes the
			// scrim should work there.
			$th_bg = $transparent
				? blocklane_pro_ext_resolve_preset_color( $target['blocklaneProTransparentBackground'] ?? '' )
				: '';
			if ( '' !== $th_bg ) {
				$vars[] = '--blocklane-pro-th-bg: ' . $th_bg . ';';
			}

			$offset = trim( (string) ( $target['blocklaneProTransparentTopOffset'] ?? '' ) );
			if ( '' !== $offset
				&& '0px' !== $offset
				&& preg_match( '/^\d*\.?\d+(px|em|rem|vh|vw|%)$/', $offset ) ) {
				$vars[] = '--blocklane-pro-th-offset: ' . $offset . ';';
			}

			// Optional stacking order. The overlay defaults to 100 — above page
			// content, below the popups overlay (99999) and the video modal
			// (999999) — which a header sharing the viewport with another fixed
			// element (a third-party bar, a chat widget) may need to beat. Rides
			// a custom property because the stylesheet rule is !important and so
			// cannot be overridden from the style attribute.
			$z_index = $target['blocklaneProTransparentZIndex'] ?? null;
			if ( null !== $z_index && is_numeric( $z_index ) ) {
				$vars[] = '--blocklane-pro-th-z: ' . (int) $z_index . ';';
			}

			if ( $vars ) {
				$existing = (string) $processor->get_attribute( 'style' );
				$existing = '' !== $existing ? rtrim( $existing, '; ' ) . '; ' : '';
				$processor->set_attribute( 'style', $existing . implode( ' ', $vars ) );
			}

			$applied = true;
		}

		if ( $stripped >= $marked && ( $applied || ! $active ) ) {
			break;
		}
	}

	if ( $applied && $solidify ) {
		blocklane_pro_transparent_header_enqueue_script();
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block_core/template-part', 'blocklane_pro_transparent_header_render_part', 10, 2 );

/**
 * Whether the site's default header is the overlay — some header-area part's
 * target group carries the attribute. Feeds the page control's "Default – …"
 * label. Rides the target resolver, so it sees through pattern and
 * synced-pattern references — a raw scan of part content cannot, because the
 * part is usually just a reference (this theme's header is one wp:pattern).
 *
 * @return bool
 */
function blocklane_pro_transparent_header_default_on() {
	foreach ( blocklane_pro_transparent_header_header_slugs() as $slug ) {
		$target = blocklane_pro_transparent_header_target_attrs( $slug );
		if ( ! empty( $target['attrs']['blocklaneProTransparentHeader'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue the scroll behavior script. Called from the render filter — the
 * header renders in the template body, well before wp_footer, so the script
 * still prints — which keeps pages without a transparent header at zero cost.
 */
function blocklane_pro_transparent_header_enqueue_script() {
	if ( is_admin() || wp_script_is( 'blocklane-pro-transparent-header', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script(
		'blocklane-pro-transparent-header',
		blocklane_pro_ext_base_url() . '/loader/transparent-header/transparent-header-frontend.js',
		array(),
		blocklane_pro_ext_version(),
		true
	);
}

// Per-block transparent-state render filters (navigation, site title, site
// logo). Required here so they ride this file's extension-enabled gate and
// the part-state machinery above is always defined first.
require_once __DIR__ . '/blocks.php';
