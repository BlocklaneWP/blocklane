<?php
/**
 * Popups — runtime (canonical source).
 *
 * SELF-CONTAINED by construction: it assumes no module has booted, and the
 * one plugin class it references — the side-effect-free toggle reader,
 * Content_Toggle — is resolved by the classmap autoloader registered at
 * plugin-file scope before any runtime loads, so no load order can trip it.
 * (The old "core WordPress only" letter of the rule outlived its reason —
 * this file used to be copied into wp-content/mu-plugins/ and run outside
 * the plugin; the spirit, no dependence on a booted module, still holds.)
 * (The gate chain below is safe mode → Advanced toggle, nothing else: nothing
 * in this plugin gates on the license at runtime — enforcement is server-side
 * at the update gateway; see inc/license/class-blocklane-pro-license.php.)
 *
 * One home now: the plugin requires this file in-process (once, from
 * Modules), so it runs only while Blocklane Pro is active. The single-load
 * guard below is kept anyway — it costs nothing, and on a site upgrading from
 * a pre-2026-08 version a leftover baked copy in mu-plugins would still load
 * first and define these symbols. Assets resolve via plugins_url().
 *
 * It provides: the blocklane_popup CPT + settings meta, the display-rules
 * evaluation, the wp_footer render (markup + per-popup animation CSS + the
 * block-support style delta + the front assets), the capability-gated
 * force-open preview parameter, the Site Lock splash suppression (popups stay
 * off the Coming Soon / Maintenance screen unless a popup's showOnLockScreen
 * setting opts it in — the gate announces the splash via the
 * `blocklane_pro_site_lock_splash` action, read here with did_action()), and
 * the Popup Bindings render pass (popup-bindings.md): source-provenance
 * opener detection (stored markup + comment-JSON attrs, never rendered
 * output) + the bound-popup render guarantee, image/cover opener markup,
 * button closers.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single-load guard. The plugin requires this file once, from the Modules
// manifest; the guard matters on a site upgrading from a pre-2026-08 version,
// where a leftover baked copy in mu-plugins loads first and wins.
if ( defined( 'BLOCKLANE_PRO_POPUPS_RUNTIME_LOADED' ) ) {
	return;
}
define( 'BLOCKLANE_PRO_POPUPS_RUNTIME_LOADED', true );

// Safe mode silences every Blocklane Pro surface, the generated runtimes included.
if ( defined( 'BLOCKLANE_PRO_SAFE_MODE' ) && BLOCKLANE_PRO_SAFE_MODE ) {
	return;
}

// The Advanced toggle (ships OFF). Read through the one toggle reader, which
// resolves the stored row without loading the Advanced class: an absent row
// or key means the shipped default, and there is no second source to consult.
if ( ! \blocklane_pro\Content_Toggle::on( 'popups' ) ) {
	return;
}

if ( ! function_exists( 'blocklane_pro_popups_register' ) ) {

	/** The post type key. */
	define( 'BLOCKLANE_PRO_POPUPS_CPT', 'blocklane_popup' );

	/** Allowed enum values for the settings meta (server-side allowlists). */
	function blocklane_pro_popups_enums() {
		return array(
			'position'   => array( 'center', 'top-left', 'top-center', 'top-right', 'center-left', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right' ),
			'trigger'    => array( 'time', 'scroll', 'exit', 'manual' ),
			'qualifier'  => array( 'is', 'is-not', 'contains', 'not-contains' ),
			'condition'  => array( 'everywhere', 'front-page', 'page', 'post', 'post-type', 'url', 'logged-in' ),
			// The Animation extension's shared keyframes (they ride the
			// extensions front-end bundle; absent bundle = graceful no-op).
			'animation'  => array(
				'fadeIn'      => 'blocklaneProAnimateFadeIn',
				'fadeInUp'    => 'blocklaneProAnimateFadeInUp',
				'fadeInDown'  => 'blocklaneProAnimateFadeInDown',
				'fadeInLeft'  => 'blocklaneProAnimateFadeInLeft',
				'fadeInRight' => 'blocklaneProAnimateFadeInRight',
				'zoomIn'      => 'blocklaneProAnimateZoomIn',
			),
		);
	}

	/** Default settings — the single source of truth for the meta shape. */
	function blocklane_pro_popups_default_settings() {
		return array(
			'position'         => 'center',
			'trigger'          => array(
				'type'  => 'time',
				'value' => 0,
			),
			'frequency'        => array(
				'seen'      => 0,
				'dismissed' => 7,
			),
			'rules'            => array(
				'match' => 'any',
				'items' => array(),
			),
			// A CSS length (px/em/rem) evaluated client-side via matchMedia,
			// so the browser does the unit math. '' = show at every width.
			'minWidth'         => '',
			// The popup card's max-width (CSS length; '' = default sizing:
			// center = theme content size, corners = fit content). Applied
			// as an inline style on the dialog AND mirrored onto the popup
			// editor canvas, so what you design is what opens.
			'maxWidth'         => '',
			'closable'         => true,
			'showCloseButton'  => true,
			// Whether this popup may appear on the Site Lock splash (Coming
			// Soon / Maintenance). Default OFF: the splash suppresses popups
			// unless a popup opts in. NOTE the polarity — unlike closable/
			// showCloseButton, an absent key means FALSE.
			'showOnLockScreen' => false,
			// The Animation builder's vocabulary: shared keyframes + its
			// duration/delay ranges (0.1-5s / 0-5s).
			'animation'        => array(
				'type'     => '',
				'duration' => 0.4,
				'delay'    => 0,
			),
		);
	}

	/**
	 * Sanitize the settings meta against the defaults + enums. Unknown keys
	 * drop; enum misses fall back to the default; ints clamp to >= 0.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array Clean settings.
	 */
	function blocklane_pro_popups_sanitize_settings( $value ) {
		$defaults = blocklane_pro_popups_default_settings();
		$enums    = blocklane_pro_popups_enums();
		$value    = is_array( $value ) ? $value : array();

		$clean             = $defaults;
		$clean['position'] = in_array( $value['position'] ?? '', $enums['position'], true ) ? $value['position'] : $defaults['position'];

		// CSS lengths in px/em/rem (viewport units are circular for a
		// viewport threshold, and pointless for the card). Legacy integer
		// saves meant px.
		foreach ( array( 'minWidth', 'maxWidth' ) as $length_key ) {
			$length = $value[ $length_key ] ?? '';
			if ( is_int( $length ) || ( is_string( $length ) && ctype_digit( $length ) ) ) {
				$length = absint( $length ) > 0 ? absint( $length ) . 'px' : '';
			}
			$clean[ $length_key ] = ( is_string( $length ) && preg_match( '/^\d*\.?\d+(px|em|rem)$/', trim( $length ) ) ) ? trim( $length ) : '';
		}

		$trigger                   = is_array( $value['trigger'] ?? null ) ? $value['trigger'] : array();
		$clean['trigger']['type']  = in_array( $trigger['type'] ?? '', $enums['trigger'], true ) ? $trigger['type'] : $defaults['trigger']['type'];
		$clean['trigger']['value'] = max( 0, absint( $trigger['value'] ?? 0 ) );
		if ( 'scroll' === $clean['trigger']['type'] ) {
			$clean['trigger']['value'] = min( 100, $clean['trigger']['value'] );
		}

		$frequency                      = is_array( $value['frequency'] ?? null ) ? $value['frequency'] : array();
		$clean['frequency']['seen']      = max( 0, absint( $frequency['seen'] ?? 0 ) );
		$clean['frequency']['dismissed'] = max( 0, absint( $frequency['dismissed'] ?? 0 ) );

		$rules                  = is_array( $value['rules'] ?? null ) ? $value['rules'] : array();
		$clean['rules']['match'] = ( 'all' === ( $rules['match'] ?? '' ) ) ? 'all' : 'any';
		$clean['rules']['items'] = array();
		foreach ( (array) ( $rules['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$condition = in_array( $item['condition'] ?? '', $enums['condition'], true ) ? $item['condition'] : 'everywhere';
			$qualifier = in_array( $item['qualifier'] ?? '', $enums['qualifier'], true ) ? $item['qualifier'] : 'is';
			$clean['rules']['items'][] = array(
				'condition' => $condition,
				'qualifier' => $qualifier,
				'value'     => sanitize_text_field( (string) ( $item['value'] ?? '' ) ),
			);
		}

		$clean['closable']         = ! isset( $value['closable'] ) || (bool) $value['closable'];
		$clean['showCloseButton']  = ! isset( $value['showCloseButton'] ) || (bool) $value['showCloseButton'];
		// Opposite polarity to the two above on purpose: absent means FALSE
		// (legacy rows and stale clients surface with the splash opt-in off).
		$clean['showOnLockScreen'] = ! empty( $value['showOnLockScreen'] );

		// Animation: legacy saves were a bare type string; now an object
		// carrying the builder's duration/delay.
		$animation = $value['animation'] ?? array();
		if ( is_string( $animation ) ) {
			$animation = array( 'type' => $animation );
		}
		$animation = is_array( $animation ) ? $animation : array();
		$clean['animation']['type']     = isset( $enums['animation'][ $animation['type'] ?? '' ] ) ? $animation['type'] : '';
		$clean['animation']['duration'] = min( 5, max( 0.1, round( (float) ( $animation['duration'] ?? $defaults['animation']['duration'] ), 2 ) ) );
		$clean['animation']['delay']    = min( 5, max( 0, round( (float) ( $animation['delay'] ?? 0 ), 2 ) ) );

		return $clean;
	}

	/** Register the CPT + the settings meta. */
	function blocklane_pro_popups_register() {
		register_post_type(
			BLOCKLANE_PRO_POPUPS_CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Popups', 'blocklane' ),
					'singular_name' => __( 'Popup', 'blocklane' ),
					'add_new_item'  => __( 'Add New Popup', 'blocklane' ),
					'edit_item'     => __( 'Edit Popup', 'blocklane' ),
					'not_found'     => __( 'No popups yet.', 'blocklane' ),
				),
				'public'              => false,
				'show_ui'             => true,
				// Site-wide chrome, so admin-level only: default post caps
				// would let Authors/Editors create and publish popups through
				// edit.php or core REST (the hidden submenu is not a gate).
				// Every primitive maps to manage_options, matching the other
				// site-chrome surfaces (Scripts, Site Visibility). With
				// map_meta_cap off, edit_post/read_post/delete_post resolve
				// straight to these primitives, so the render-time
				// current_user_can( 'edit_post', … ) checks below stay correct.
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => 'manage_options',
					'read_post'              => 'manage_options',
					'delete_post'            => 'manage_options',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'read'                   => 'read',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'create_posts'           => 'manage_options',
				),
				// Under the Blocklane Pro menu while the plugin is around; a
				// plain top-level menu from the bake when it isn't, so the
				// content stays manageable. defined() is a soft probe — this
				// file must never hard-require the plugin.
				'show_in_menu'        => defined( 'BLOCKLANE_PRO_VERSION' ) ? 'blocklane-pro' : true,
				'menu_icon'           => 'dashicons-megaphone',
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_in_rest'        => true,
				// custom-fields is required for registered meta to surface in
				// REST (and therefore in the editor's meta entity prop) — the
				// settings panels are dead without it.
				'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'rewrite'             => false,
			)
		);

		$item_schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'condition' => array( 'type' => 'string' ),
				'qualifier' => array( 'type' => 'string' ),
				'value'     => array( 'type' => 'string' ),
			),
		);

		register_post_meta(
			BLOCKLANE_PRO_POPUPS_CPT,
			'blocklane_popup_settings',
			array(
				'single'            => true,
				'type'              => 'object',
				'default'           => blocklane_pro_popups_default_settings(),
				'sanitize_callback' => 'blocklane_pro_popups_sanitize_settings',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
				'show_in_rest'      => array(
					// Core's default prepare_callback schema-validates the
					// STORED value and nulls the whole object on any mismatch,
					// so legacy rows (bare-string animation, integer minWidth)
					// reached the editor as null — and the next save patched
					// over defaults, silently wiping rules/trigger/frequency.
					// Run the same sanitizer the write path uses so legacy
					// shapes surface migrated; it is idempotent on
					// current-shape values.
					'prepare_callback' => static function ( $value ) {
						return blocklane_pro_popups_sanitize_settings( $value );
					},
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'position'         => array( 'type' => 'string' ),
							'trigger'          => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'type'  => array( 'type' => 'string' ),
									'value' => array( 'type' => 'integer' ),
								),
							),
							'frequency'        => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'seen'      => array( 'type' => 'integer' ),
									'dismissed' => array( 'type' => 'integer' ),
								),
							),
							'rules'            => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'match' => array( 'type' => 'string' ),
									'items' => array(
										'type'  => 'array',
										'items' => $item_schema,
									),
								),
							),
							'minWidth'         => array( 'type' => 'string' ),
							'maxWidth'         => array( 'type' => 'string' ),
							'closable'         => array( 'type' => 'boolean' ),
							'showCloseButton'  => array( 'type' => 'boolean' ),
							'showOnLockScreen' => array( 'type' => 'boolean' ),
							'animation'        => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array(
									'type'     => array( 'type' => 'string' ),
									'duration' => array( 'type' => 'number' ),
									'delay'    => array( 'type' => 'number' ),
								),
							),
						),
					),
				),
			)
		);
	}
	add_action( 'init', 'blocklane_pro_popups_register' );

	/**
	 * A popup's settings, sanitized with defaults filled.
	 *
	 * @param int $post_id Popup ID.
	 * @return array
	 */
	function blocklane_pro_popups_settings( $post_id ) {
		return blocklane_pro_popups_sanitize_settings( get_post_meta( $post_id, 'blocklane_popup_settings', true ) );
	}

	/**
	 * Evaluate one display rule against the current request.
	 *
	 * @param array $rule { condition, qualifier, value }.
	 * @return bool
	 */
	function blocklane_pro_popups_rule_matches( array $rule ) {
		$values = array_filter( array_map( 'trim', explode( ',', (string) $rule['value'] ) ) );
		$negate = in_array( $rule['qualifier'], array( 'is-not', 'not-contains' ), true );

		switch ( $rule['condition'] ) {
			case 'everywhere':
				$result = true;
				break;
			case 'front-page':
				$result = is_front_page();
				break;
			case 'page':
				// Empty value = any page; else IDs or slugs.
				$result = empty( $values ) ? is_page() : is_page( $values );
				break;
			case 'post':
				$result = empty( $values ) ? is_singular( 'post' ) : is_single( $values );
				break;
			case 'post-type':
				$queried = get_post_type();
				$result  = empty( $values ) ? is_singular() : ( $queried && in_array( $queried, $values, true ) );
				break;
			case 'url':
				$path   = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
				$result = false;
				foreach ( $values as $needle ) {
					if ( false !== stripos( $path, $needle ) ) {
						$result = true;
						break;
					}
				}
				// URL with no value never matches (nothing to compare).
				break;
			case 'logged-in':
				$result = is_user_logged_in();
				break;
			default:
				$result = false;
		}

		return $negate ? ! $result : $result;
	}

	/**
	 * Whether a popup's rules allow it on the current request. No rules =
	 * everywhere (the editor default).
	 *
	 * @param array $rules { match, items }.
	 * @return bool
	 */
	function blocklane_pro_popups_rules_match( array $rules ) {
		if ( empty( $rules['items'] ) ) {
			return true;
		}

		foreach ( $rules['items'] as $rule ) {
			$matched = blocklane_pro_popups_rule_matches( $rule );
			if ( $matched && 'any' === $rules['match'] ) {
				return true;
			}
			if ( ! $matched && 'all' === $rules['match'] ) {
				return false;
			}
		}

		return 'all' === $rules['match'];
	}

	/**
	 * The force-open preview id, when this request is a valid preview: the
	 * param names a popup the current user can edit. Called from the
	 * wp_footer render only — the current_user_can( 'edit_post' ) check
	 * here is the real gate (with the admin-mapped CPT caps that means
	 * manage_options).
	 *
	 * @return int 0 when not previewing.
	 */
	function blocklane_pro_popups_preview_id() {
		$id = absint( $_GET['blocklane_popup_preview'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate, capability-checked.
		if ( ! $id || BLOCKLANE_PRO_POPUPS_CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return 0;
		}
		return $id;
	}

	/** Point the editor's Preview at the front page with the force-open param. */
	function blocklane_pro_popups_preview_link( $link, $post ) {
		if ( BLOCKLANE_PRO_POPUPS_CPT === $post->post_type ) {
			return add_query_arg( 'blocklane_popup_preview', $post->ID, home_url( '/' ) );
		}
		return $link;
	}
	add_filter( 'preview_post_link', 'blocklane_pro_popups_preview_link', 10, 2 );

	/**
	 * The page's bound-popup registry: popup IDs referenced by a manual
	 * opener rendered somewhere on this request (popup-bindings.md). The
	 * footer render unions these into its output so a click binding always
	 * has its popup in the page.
	 *
	 * @param array $add Popup IDs to register.
	 * @return int[] All registered IDs.
	 */
	function blocklane_pro_popups_bound_ids( $add = array() ) {
		static $ids = array();
		foreach ( (array) $add as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[ $id ] = true;
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Popup IDs already rendered into this request — shared between the main
	 * render (wp_footer:9) and the late drain (wp_footer:19) so a popup can
	 * never print twice. Same static-registry shape as bound_ids above.
	 *
	 * @param array<int|string> $mark IDs to mark rendered.
	 * @return array<int> The rendered IDs.
	 */
	function blocklane_pro_popups_rendered_ids( $mark = array() ) {
		static $ids = array();
		foreach ( (array) $mark as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[ $id ] = true;
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Popup Bindings render pass (popup-bindings.md), two jobs:
	 *
	 * 1. Apply attribute-carried binding markup: Image/Cover openers
	 *    (blocklaneProPopupId → opener class + activatable wrapper) and
	 *    Button closers (blocklaneProPopupClose → data-popup-close). Anchor
	 *    blocks need no mutation — their binding is their own href.
	 * 2. Register every manual-opener reference found in the block's SOURCE —
	 *    its stored markup and comment-JSON attributes — for the footer
	 *    render's union (the render guarantee). Never the rendered output:
	 *    that is the channel visitor content arrives on (#34).
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	function blocklane_pro_popups_bind_render_block( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		// The opener mutations and the registry are consumed only by the
		// wp_footer render, which never runs in admin, REST, or feed
		// contexts. Skip there: the work would be discarded anyway (and this
		// filter fires on every block of every REST content.rendered).
		// wp_is_rest_endpoint() (WP 6.5) covers internal rest_do_request()
		// dispatch too, where the REST_REQUEST constant is never set — the
		// same predicate the Video Modal twin uses (its
		// blocklane_pro_video_modal_page_prints_runtime()). Embeds render the
		// excerpt, never blocks, so there is nothing to add for them.
		if ( is_admin() || wp_is_rest_endpoint() || is_feed() ) {
			return $block_content;
		}

		$name  = isset( $block['blockName'] ) ? $block['blockName'] : '';
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		// Manual-class openers (issue #429): a hand-typed
		// blocklane-popup-open-{id} class on an image binds the delegated
		// click on the whole element — including the <img> core's lightbox
		// also binds — so the same suppression the attribute branch performs
		// below must fire here too, or the one documented opener path the
		// attribute door doesn't cover double-fires popup + zoom. Keyed on
		// the STORED className attribute (the Additional CSS classes field),
		// never rendered output — the #34 provenance rule.
		if ( 'core/image' === $name
			&& is_string( $attrs['className'] ?? null )
			&& false !== strpos( $attrs['className'], 'blocklane-popup-open-' ) ) {
			remove_filter( 'render_block_core/image', 'block_core_image_render_lightbox', 15 );
		}

		// Image/Cover openers, for a published popup only (a deleted or
		// unpublished target renders the block untouched). Video Modal wins a
		// copy-pasted conflict (one click, one meaning) — but only when it
		// will ACTUALLY render (issue #410): the resolver is the shared
		// predicate, and its absence means the extension is off for this
		// request, so the popup opener proceeds instead of leaving a block
		// with neither behavior.
		$video_wins = function_exists( 'blocklane_pro_video_modal_resolve' )
			&& null !== blocklane_pro_video_modal_resolve( $name, $attrs );
		if ( in_array( $name, array( 'core/image', 'core/cover' ), true ) && ! $video_wins ) {
			$popup_id = absint( $attrs['blocklaneProPopupId'] ?? 0 );
			$popup    = $popup_id > 0 ? get_post( $popup_id ) : null;

			// A linked image keeps its link (issue #420's popups door,
			// decision D2): stamping role="button" around a real <a> is the
			// same nested-interactive violation one feature over. No opener
			// — the binding stays saved, and the picker's own linked-image
			// notice explains. Structural check covers hand-written wraps.
			$image_linked = false;
			if ( 'core/image' === $name && $popup ) {
				$image_linked = 'none' !== ( $attrs['linkDestination'] ?? 'none' );
				if ( ! $image_linked && preg_match( '/<a[\s>]/i', $block_content, $m_a, PREG_OFFSET_CAPTURE ) ) {
					$img_at       = stripos( $block_content, '<img' );
					$image_linked = ( false === $img_at || $m_a[0][1] < $img_at );
				}
			}

			if ( $popup && ! $image_linked && BLOCKLANE_PRO_POPUPS_CPT === $popup->post_type && 'publish' === $popup->post_status ) {
				// The generated opener class exists only in rendered output,
				// which the scan below no longer reads (#34) — register the
				// binding directly. One call covers the image path, the cover
				// overlay path, and the cover fallback path.
				blocklane_pro_popups_bound_ids( array( $popup_id ) );

				// MIRROR — core lightbox suppression, same mechanism and
				// rationale as blocklane_pro_video_modal_render_block()
				// (wp-includes/blocks/image.php:104-126; issues #403/D2):
				// the explicit popup opener owns this image's click, so
				// core's ambient zoom must not double-fire beneath it. The
				// next lightboxed image re-adds the filter in its own
				// render callback.
				if ( 'core/image' === $name ) {
					remove_filter( 'render_block_core/image', 'block_core_image_render_lightbox', 15 );
				}

				$title = get_the_title( $popup );
				$label = '' !== $title
					/* translators: %s: popup title. */
					? sprintf( __( 'Open popup: %s', 'blocklane' ), $title )
					: __( 'Open popup', 'blocklane' );

				if ( 'core/cover' === $name ) {
					// A Cover is a container: making its wrapper role="button"
					// would wrap the cover's own interactive blocks (links,
					// buttons) in a button role and add a competing tab stop.
					// Instead inject ONE real <button> overlay as the FIRST
					// child of the inner-container, filling it. Inside the
					// inner-container the button sits in the same stacking
					// context as the content (no fight with core's cover
					// z-index rules): CSS lifts the cover's own controls above
					// it, so background/text clicks open the popup while inner
					// controls keep working.
					$needle = '<div class="wp-block-cover__inner-container';
					$at     = strpos( $block_content, $needle );
					$tag_end = false !== $at ? strpos( $block_content, '>', $at ) : false;
					if ( false !== $tag_end ) {
						$processor = new WP_HTML_Tag_Processor( $block_content );
						if ( $processor->next_tag() ) {
							$processor->add_class( 'blocklane-popup-cover-opener' );
							$block_content = $processor->get_updated_html();
							$at            = strpos( $block_content, $needle );
							$tag_end       = strpos( $block_content, '>', $at );
						}
						$button    = '<button type="button" class="blocklane-popup-open-' . $popup_id
							. ' blocklane-popup-cover-opener__trigger" aria-label="' . esc_attr( $label ) . '"></button>';
						$insert_at     = $tag_end + 1;
						$block_content = substr( $block_content, 0, $insert_at ) . $button . substr( $block_content, $insert_at );
					} else {
						// No inner-container found (unexpected markup): fall back
						// to the wrapper-as-opener so the binding still works.
						$processor = new WP_HTML_Tag_Processor( $block_content );
						if ( $processor->next_tag() ) {
							$processor->add_class( 'blocklane-popup-open-' . $popup_id );
							$processor->set_attribute( 'role', 'button' );
							$processor->set_attribute( 'tabindex', '0' );
							$processor->set_attribute( 'aria-label', $label );
							$block_content = $processor->get_updated_html();
						}
					}
				} else {
					// Image: a <figure> wrapping a non-interactive <img> — the
					// button role is well-formed here.
					$processor = new WP_HTML_Tag_Processor( $block_content );
					if ( $processor->next_tag() ) {
						$processor->add_class( 'blocklane-popup-open-' . $popup_id );
						$processor->set_attribute( 'role', 'button' );
						$processor->set_attribute( 'tabindex', '0' );
						$processor->set_attribute( 'aria-label', $label );
						$block_content = $processor->get_updated_html();
					}
				}
			}
		}

		// Button closers: data-popup-close is the attribute view.js already
		// honors; href="#" keeps a URL-less anchor focusable (view.js
		// prevents the default so it never scroll-jumps). Close wins a
		// copy-pasted video-modal conflict — the video filter already ran
		// (extensions load before popups), so its trigger
		// class is strippable here.
		if ( 'core/button' === $name && ! empty( $attrs['blocklaneProPopupClose'] ) ) {
			$processor = new WP_HTML_Tag_Processor( $block_content );
			$found     = $processor->next_tag( 'a' );
			if ( ! $found ) {
				$processor = new WP_HTML_Tag_Processor( $block_content );
				$found     = $processor->next_tag( 'button' );
			}
			if ( $found ) {
				$processor->set_attribute( 'data-popup-close', 'true' );
				$processor->remove_class( 'blocklane-pro-video-modal-trigger' );
				if ( 'A' === $processor->get_tag() && null === $processor->get_attribute( 'href' ) ) {
					$processor->set_attribute( 'href', '#' );
				}
				$block_content = $processor->get_updated_html();
			}
		}

		// Register opener references from the block's SOURCE — its stored
		// markup and comment-JSON attributes — never from rendered output
		// (#34). Stored block source exists only in author-controlled
		// containers (post content, templates, template parts, patterns,
		// navigation/popup posts); visitor- and external-supplied text
		// (comments, embeds, feeds) enters a page only through a dynamic
		// block's render callback and can never appear here, and a rendered
		// opener also bubbles into every ANCESTOR block's output, so no
		// per-block denylist could hold. Attribute view only, as before, so
		// escaped text that merely names the contract stays inert. Only a
		// bare same-page fragment counts as an opener; a cross-page deep
		// link navigates and opens on arrival via the hash. PHP-generated
		// openers (dynamic third-party blocks, other render_block filters)
		// are the one deliberate loss — they register by calling
		// blocklane_pro_popups_bound_ids() themselves (popup-bindings.md).
		$ids = array();

		$source = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
		if ( '' !== $source && false !== strpos( $source, 'blocklane-popup-' ) ) {
			$tags = new WP_HTML_Tag_Processor( $source );
			while ( $tags->next_tag() ) {
				$href = $tags->get_attribute( 'href' );
				if ( is_string( $href ) && preg_match( '/^#blocklane-popup-(\d+)$/', $href, $m ) ) {
					$ids[] = $m[1];
				}
				$class = $tags->get_attribute( 'class' );
				if ( is_string( $class ) && preg_match_all( '/(?:^|\s)blocklane-popup-open-(\d+)(?:\s|$)/', $class, $cm ) ) {
					$ids = array_merge( $ids, $cm[1] );
				}
			}
		}

		// Dynamic anchor blocks (navigation-link/submenu) and dynamic blocks
		// carrying a hand-typed opener class have no saved markup — their
		// only author-provenance signal is the parsed attributes. Top-level
		// string values only; the anchored patterns fail on the first
		// character for everything else.
		foreach ( $attrs as $attr_value ) {
			if ( is_string( $attr_value ) && preg_match( '/^#blocklane-popup-(\d+)$/', $attr_value, $m ) ) {
				$ids[] = $m[1];
			}
		}
		$class_attr = isset( $attrs['className'] ) ? $attrs['className'] : null;
		if ( is_string( $class_attr ) && preg_match_all( '/(?:^|\s)blocklane-popup-open-(\d+)(?:\s|$)/', $class_attr, $cm ) ) {
			$ids = array_merge( $ids, $cm[1] );
		}

		if ( $ids ) {
			blocklane_pro_popups_bound_ids( $ids );
		}

		return $block_content;
	}
	add_filter( 'render_block', 'blocklane_pro_popups_bind_render_block', 10, 2 );

	/**
	 * Render one popup's footer markup (and append its animation CSS).
	 *
	 * @param WP_Post $post         Popup post.
	 * @param array   $settings     Sanitized settings.
	 * @param bool    $force_manual Replace the ambient trigger with manual —
	 *                              bound-only popups on rules-excluded pages
	 *                              must open on click but never auto-fire.
	 * @param bool    $is_preview   Capability-gated force-open preview.
	 * @param array   $enums        blocklane_pro_popups_enums().
	 * @param string  $css          Accumulated animation CSS (by reference).
	 * @return string Markup, '' when the popup has no content.
	 */
	function blocklane_pro_popups_render_single( $post, $settings, $force_manual, $is_preview, $enums, &$css ) {
		$content = trim( (string) $post->post_content );
		if ( '' === $content ) {
			return '';
		}
		$content = wp_filter_content_tags( do_shortcode( do_blocks( $content ) ) );

		if ( $force_manual ) {
			$settings['trigger'] = array(
				'type'  => 'manual',
				'value' => 0,
			);
		}

		$id        = 'blocklane-popup-' . $post->ID;
		$is_center = 'center' === $settings['position'];
		$closable  = ! empty( $settings['closable'] );

		$client = array(
			'trigger'   => $settings['trigger'],
			'frequency' => $settings['frequency'],
			'minWidth'  => $settings['minWidth'],
			'closable'  => $closable,
			'position'  => $settings['position'],
			'center'    => $is_center,
			'force'     => $is_preview,
			// Editors bypass frequency suppression (the dismissal cookie
			// otherwise silences the popup for days mid-design — the footgun
			// the reference solved with a global "test mode"). Triggers still
			// run naturally so timing can be felt.
			'bypass'    => current_user_can( 'edit_post', $post->ID ),
		);

		$close_button = '';
		if ( $closable && ! empty( $settings['showCloseButton'] ) ) {
			$close_button = '<button type="button" class="blocklane-popup__close" data-popup-close aria-label="' . esc_attr__( 'Close', 'blocklane' ) . '">'
				. '<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false" fill="none"><path d="M4.5 4.5l11 11M15.5 4.5l-11 11" stroke="currentColor" stroke-width="1.7"/></svg>'
				. '</button>';
		}

		// Center = a real modal; corners = non-modal complementary.
		$dialog_attrs = $is_center
			? 'role="dialog" aria-modal="true"'
			: 'role="complementary"';
		$dialog_attrs .= ' aria-label="' . esc_attr( get_the_title( $post ) ) . '" tabindex="-1"';

		// Max width caps both sizing models (center = content-size width,
		// corners = fit-content). The value is sanitizer-guaranteed to be a
		// bare CSS length. Placement differs on purpose: a corner wrapper is
		// a shrink-to-fit fixed box, and a percentage inside its child's
		// max-width is CYCLIC there — browsers drop the cap from the
		// intrinsic-width pass, so the wrapper sizes to the uncapped content
		// and the dialog caps inside it, leaving lopsided dead space at the
		// pinned edge. Capping the WRAPPER with viewport units (non-cyclic,
		// mirroring its own 100vw - 48px stylesheet cap) keeps corners
		// hugging their offsets; center wrappers span the viewport, so the
		// cap stays on the dialog, where % resolves against a definite flex
		// parent.
		$wrapper_attrs = '';
		if ( '' !== $settings['maxWidth'] ) {
			if ( $is_center ) {
				$dialog_attrs .= ' style="max-width:min(' . esc_attr( $settings['maxWidth'] ) . ', 100%)"';
			} else {
				$wrapper_attrs = ' style="max-width:min(' . esc_attr( $settings['maxWidth'] ) . ', 100vw - 48px)"';
			}
		}

		if ( '' !== $settings['animation']['type'] ) {
			$css .= sprintf(
				'@media (prefers-reduced-motion: no-preference) { #%1$s.is-open .blocklane-popup__dialog { animation: %2$s %3$ss ease-out %4$ss both; } }',
				esc_attr( $id ),
				$enums['animation'][ $settings['animation']['type'] ],
				(float) $settings['animation']['duration'],
				(float) $settings['animation']['delay']
			);
		}

		return '<div id="' . esc_attr( $id ) . '" class="blocklane-popup blocklane-popup--' . esc_attr( $settings['position'] ) . '"'
			. $wrapper_attrs
			. ' data-blocklane-popup="' . esc_attr( wp_json_encode( $client ) ) . '" hidden>'
			. ( $is_center ? '<div class="blocklane-popup__overlay" data-popup-overlay></div>' : '' )
			. '<div class="blocklane-popup__dialog" ' . $dialog_attrs . '>'
			. $close_button
			. '<div class="blocklane-popup__content is-layout-flow">' . $content . '</div>'
			. '</div></div>';
	}

	/**
	 * Shape version for the cached popup rows. BUMP THIS whenever the settings
	 * structure blocklane_pro_popups_sanitize_settings() produces changes —
	 * a key added, renamed or removed, or an enum's allowed values narrowed —
	 * or when the payload layout itself changes. Cached payloads built under
	 * a different version are discarded on read, so an upgrade can never feed
	 * old-shape settings to new render code.
	 *
	 * 2: rows keyed by post ID + the flush-generation token ('gen'); the
	 *    published SET moved out of the cache into a per-request ids-only
	 *    WP_Query (see blocklane_pro_popups_published_rows).
	 * 3: settings gained showOnLockScreen (the Site Lock splash opt-in).
	 */
	define( 'BLOCKLANE_PRO_POPUPS_CACHE_SCHEMA', 3 );

	/**
	 * Autoload budget for a cache row, mirroring
	 * blocklane_pro\Helper::autoload_ok(). Duplicated by contract: this file
	 * is baked into mu-plugins and must never reach into the plugin
	 * (docs/archive/bake-contract.md). Keep the number in sync with the Helper.
	 *
	 * @param mixed $value The value about to be stored.
	 * @return bool Whether the option should autoload.
	 */
	function blocklane_pro_popups_autoload_ok( $value ) {
		return strlen( maybe_serialize( $value ) ) < 32768;
	}

	/**
	 * The published-popup set for the footer render.
	 *
	 * WHICH popups are in the set is decided per request, by an ids-only
	 * WP_Query that runs everything a normal query runs — pre_get_posts, the
	 * posts_* SQL filters, whatever a membership or translation plugin hooks
	 * to scope this CPT. An earlier revision cached the set itself, filled by
	 * a deliberately filter-free $wpdb SELECT so no single request could skew
	 * the shared row; that made the cache deterministic but also bypassed any
	 * plugin legitimately filtering the CPT, for every visitor — restricted
	 * popups could reach people meant not to see them. UNRESOLVED PRODUCT
	 * DECISION (no ruling yet), safer option taken: correct-by-default at the
	 * price of one light ids query per page — the zero-query warm path is
	 * gone. Reversing this means re-introducing a context-free id fetch; the
	 * row cache below is unaffected either way.
	 *
	 * What IS cached is the heavy, request-independent part: per-popup rows —
	 * raw post fields + sanitized settings — keyed by post ID, so the meta
	 * lookup + sanitizer pass and the full post rows are not refetched per
	 * request. Rendering stays per-request (do_blocks and per-user bits like
	 * the editor bypass run fresh), so dynamic content inside a popup is
	 * never frozen.
	 *
	 * Autoload is size-gated, NOT unconditional. Only this render path reads
	 * the cache and it bails on is_admin(), so an autoloaded row would tax
	 * every admin, REST and cron request on the site for something none of
	 * them use. Small sets still ride alloptions for free; a set carrying
	 * enough post content to exceed the budget drops to on-demand, costing
	 * that one front-end read a query and every other request nothing. A
	 * plain option rather than a transient because set_transient() with no
	 * expiry is precisely the unconditional-autoload trap, and one WITH an
	 * expiry would throw the warm-read win away for every site.
	 *
	 * The stored payload carries the schema version it was built under. The
	 * rows hold sanitizer OUTPUT, and the render path indexes that structure
	 * without isset guards, so a release that adds, renames or drops a
	 * settings key would otherwise serve old-shape arrays to new render code
	 * until every popup happened to be re-saved — this repo's render-vs-schema
	 * divergence class. Reading through the version makes an upgrade
	 * self-healing instead of depending on a migration hook having fired.
	 *
	 * It also carries the flush-generation token current when this rebuild
	 * STARTED. blocklane_pro_popups_flush_rows() rotates the token, so a
	 * rebuild already in flight when an editor's save flushed writes a
	 * payload stamped with the old token — discarded on the next read,
	 * instead of resurrecting the pre-save snapshot until the next mutation
	 * (which a bare delete_option flush allowed).
	 *
	 * @return array{posts:WP_Post[],settings:array<int,array>}
	 */
	function blocklane_pro_popups_published_rows() {
		/**
		 * Whether to serve per-popup row data from the shared cache.
		 *
		 * The published SET is always resolved per request (query filters
		 * honored), so most sites never need this. Return false only when the
		 * row DATA itself — post fields, settings — is request-dependent:
		 * every row is then rebuilt through WP_Query on each request.
		 *
		 * @param bool $use_cache Whether to use the cached row data.
		 */
		$use_cache = (bool) apply_filters( 'blocklane_pro_popups_use_cache', true );

		if ( ! $use_cache ) {
			return blocklane_pro_popups_assemble( blocklane_pro_popups_fetch_rows_queried() );
		}

		$ids = blocklane_pro_popups_published_ids();

		// The generation token is read BEFORE the rows are, so a flush that
		// lands while this rebuild is in flight leaves the payload written
		// below stamped stale — the next read discards it. The token row is
		// seeded on first use so later reads ride alloptions instead of
		// paying a missing-option query on every front-end request.
		$gen = (string) get_option( 'blocklane_pro_popup_rows_gen', '' );
		if ( '' === $gen ) {
			add_option( 'blocklane_pro_popup_rows_gen', wp_generate_uuid4(), '', true );
			// Re-read: a concurrent seeder may have won the add_option race,
			// and both requests must agree on the one stored token.
			$gen = (string) get_option( 'blocklane_pro_popup_rows_gen', '' );
		}

		$cached = get_option( 'blocklane_pro_popup_rows', null );
		$rows   = ( is_array( $cached )
			&& isset( $cached['schema'], $cached['gen'], $cached['rows'] )
			&& BLOCKLANE_PRO_POPUPS_CACHE_SCHEMA === $cached['schema']
			&& $gen === $cached['gen']
			&& is_array( $cached['rows'] ) )
				? $cached['rows']
				: array();

		$missing = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $rows[ $id ] ) ) {
				$missing[] = $id;
			}
		}
		if ( $missing ) {
			$rows    = $rows + blocklane_pro_popups_rows_from_posts( array_filter( array_map( 'get_post', $missing ) ) );
			$payload = array(
				'schema' => BLOCKLANE_PRO_POPUPS_CACHE_SCHEMA,
				'gen'    => $gen,
				'rows'   => $rows,
			);
			update_option( 'blocklane_pro_popup_rows', $payload, blocklane_pro_popups_autoload_ok( $payload ) );
		}

		// Serve ONLY the ids this request's query allowed, in query order —
		// the cache may hold rows other requests were allowed to see.
		$allowed = array();
		foreach ( $ids as $id ) {
			if ( isset( $rows[ $id ] ) ) {
				$allowed[ $id ] = $rows[ $id ];
			}
		}

		return blocklane_pro_popups_assemble( $allowed );
	}

	/**
	 * Inflate cache rows into the render contract. The reconstructed posts
	 * are primed into the post cache so the render path's get_post()/
	 * get_the_title()/current_user_can( 'edit_post' ) lookups stay
	 * query-free.
	 *
	 * @param array<int,array{post:array,settings:array}> $rows Cache rows.
	 * @return array{posts:WP_Post[],settings:array<int,array>}
	 */
	function blocklane_pro_popups_assemble( $rows ) {
		$posts    = array();
		$settings = array();
		foreach ( $rows as $row ) {
			$post                  = new WP_Post( (object) $row['post'] );
			$posts[]               = $post;
			$settings[ $post->ID ] = $row['settings'];
		}

		if ( $posts ) {
			update_post_cache( $posts );
		}

		return array(
			'posts'    => $posts,
			'settings' => $settings,
		);
	}

	/**
	 * The published popup IDs for the current request — a real WP_Query so
	 * pre_get_posts and the posts_* filters all run, restricted to ids so
	 * the heavy row data can come from the cache instead of a full row
	 * fetch. Mirrors the cached set's original shape: newest first, capped
	 * at 20.
	 *
	 * @return int[]
	 */
	function blocklane_pro_popups_published_ids() {
		$query = new WP_Query(
			array(
				'post_type'              => BLOCKLANE_PRO_POPUPS_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Build the row set through WP_Query, honouring every per-request filter
	 * for the row data too. Used only when the row cache is switched off.
	 *
	 * @return array<int,array{post:array,settings:array}>
	 */
	function blocklane_pro_popups_fetch_rows_queried() {
		$query = new WP_Query(
			array(
				'post_type'              => BLOCKLANE_PRO_POPUPS_CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		return blocklane_pro_popups_rows_from_posts( $query->posts );
	}

	/**
	 * Shape post objects into cache rows, keyed by post ID: raw fields plus
	 * sanitized settings.
	 *
	 * @param array $posts Post objects.
	 * @return array<int,array{post:array,settings:array}>
	 */
	function blocklane_pro_popups_rows_from_posts( $posts ) {
		$rows = array();
		foreach ( (array) $posts as $post ) {
			$rows[ (int) $post->ID ] = array(
				'post'     => get_object_vars( $post ),
				'settings' => blocklane_pro_popups_settings( $post->ID ),
			);
		}
		return $rows;
	}

	/**
	 * Drop the cached popup rows; the next front-end request rebuilds them.
	 *
	 * The generation token rotates FIRST: a rebuild that read the old token
	 * before this flush — and may still be mid-fetch on another request —
	 * writes a payload stamped with it, which the token mismatch then
	 * discards on read. Deleting the option alone let that in-flight write
	 * resurrect the pre-save snapshot until the next mutation. A fresh
	 * random token rather than an increment, so two concurrent flushes
	 * cannot lose a bump to a read-modify-write race.
	 */
	function blocklane_pro_popups_flush_rows() {
		update_option( 'blocklane_pro_popup_rows_gen', wp_generate_uuid4(), true );
		delete_option( 'blocklane_pro_popup_rows' );
		// Releases fitted before the option move cached under a no-expiry
		// transient, which autoloads and is exempt from the expired-transient
		// purge. Clear that row too so upgrading sites stop paying for it.
		delete_transient( 'blocklane_pro_popup_rows' );
	}

	// Any popup mutation invalidates. These hooks live in the runtime so the
	// baked copy keeps the cache honest after the plugin is deactivated or
	// removed.
	//
	// save_post_{type} is the single hook that covers every core write path,
	// scheduled publishes included: wp_publish_post() fires
	// wp_transition_post_status() and then, unconditionally,
	// do_action( "save_post_{$post->post_type}", ... ) — so it needs no
	// transition_post_status companion. (An earlier revision carried one,
	// justified by the belief that wp_publish_post only fires the transition.
	// It does not; the hook was pure duplicate work and its comment would have
	// misled the next editor into treating it as load-bearing.)
	add_action( 'save_post_' . BLOCKLANE_PRO_POPUPS_CPT, 'blocklane_pro_popups_flush_rows' );
	add_action(
		'deleted_post',
		function ( $post_id, $post = null ) {
			if ( $post && BLOCKLANE_PRO_POPUPS_CPT === $post->post_type ) {
				blocklane_pro_popups_flush_rows();
			}
		},
		10,
		2
	);
	// The settings meta written outside a post save — wp post meta update, or
	// any code calling update_post_meta() directly. Before the cache existed
	// such an edit took effect on the next request; without this it would sit
	// invisible until the popup happened to be re-saved.
	foreach ( array( 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ) as $blocklane_pro_popups_meta_hook ) {
		add_action(
			$blocklane_pro_popups_meta_hook,
			function ( $meta_id, $post_id, $meta_key ) {
				if ( 'blocklane_popup_settings' === $meta_key
					&& BLOCKLANE_PRO_POPUPS_CPT === get_post_type( $post_id ) ) {
					blocklane_pro_popups_flush_rows();
				}
			},
			10,
			3
		);
	}
	unset( $blocklane_pro_popups_meta_hook );

	/**
	 * Print block-support styles the popup render queued onto a hook slot
	 * that can no longer run (#42). wp_enqueue_block_support_styles targets
	 * wp_head in block themes (long fired by footer time) and wp_footer:10
	 * in classic themes (already passed for the late drain) — the callbacks
	 * it queues during a popup's footer-time render are dead code, and the
	 * block-level preset variables they print simply never appear. Snapshot
	 * the hook table before the render, then invoke, capture, and REMOVE
	 * whatever appeared in a dead slot. A dead-slot callback would never
	 * have run anyway, so invoking it here can only surface output that was
	 * otherwise lost; slots that will still run naturally are left alone so
	 * nothing prints twice.
	 *
	 * @param string $hook_name         'wp_head' or 'wp_footer'.
	 * @param array<int|string,array<string,array{function:callable,accepted_args:int}>> $before Pre-render WP_Hook->callbacks snapshot.
	 * @param int    $max_dead_priority Highest priority that can no longer
	 *                                  run (PHP_INT_MAX when the whole hook
	 *                                  already fired).
	 * @return string Captured markup.
	 */
	function blocklane_pro_popups_flush_dead_support_styles( $hook_name, $before, $max_dead_priority ) {
		if ( ! isset( $GLOBALS['wp_filter'][ $hook_name ] ) ) {
			return '';
		}
		$hook = $GLOBALS['wp_filter'][ $hook_name ];
		$out  = '';
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			if ( $priority > $max_dead_priority ) {
				continue; // Will still run naturally — never double-print.
			}
			foreach ( $callbacks as $id => $callback ) {
				if ( isset( $before[ $priority ][ $id ] ) ) {
					continue; // Pre-existing (already ran when its slot fired).
				}
				ob_start();
				try {
					call_user_func( $callback['function'] );
				} catch ( \Throwable $e ) {
					// A foreign late-added callback must not break the footer;
					// its output (if any) is discarded with the buffer intact.
				}
				$out .= ob_get_clean();
				unset( $hook->callbacks[ $priority ][ $id ] );
				if ( empty( $hook->callbacks[ $priority ] ) ) {
					unset( $hook->callbacks[ $priority ] );
				}
			}
		}
		return $out;
	}

	/**
	 * The bound-popup drain (popup-bindings.md): manual openers seen during
	 * page render pull their popups in even where display rules exclude them
	 * (rules govern ambient behavior; a binding is author intent), and past
	 * the ambient query cap. Rules-excluded popups get their trigger forced
	 * to manual so they can't auto-fire where rules say no. Rendering popup
	 * content registers any openers inside it (chained popups), so drain
	 * until quiet; the rendered-ids registry makes cycles terminate and lets
	 * the late pass at wp_footer:19 resume where this one stopped.
	 *
	 * @param array<int,array<string,mixed>> $settings_map Cached settings by popup ID.
	 * @param bool                           $is_splash    Rendering on the Site Lock splash.
	 * @param array<string,mixed>            $enums        blocklane_pro_popups_enums().
	 * @param string                         $css          Accumulated animation CSS (by reference).
	 * @return string Rendered markup.
	 */
	function blocklane_pro_popups_drain_bound( $settings_map, $is_splash, $enums, &$css ) {
		$markup = '';
		do {
			$pending = array_diff( blocklane_pro_popups_bound_ids(), blocklane_pro_popups_rendered_ids() );
			foreach ( $pending as $bound_id ) {
				blocklane_pro_popups_rendered_ids( array( $bound_id ) );

				$post = get_post( $bound_id );
				if ( ! $post || BLOCKLANE_PRO_POPUPS_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
					continue;
				}

				$settings     = isset( $settings_map[ $bound_id ] )
					? $settings_map[ $bound_id ]
					: blocklane_pro_popups_settings( $bound_id );
				// A binding is author intent, so a bound popup renders even on
				// the splash — but the splash term is load-bearing: the bound
				// pass must never grant ambient behavior the ambient pass just
				// denied. Without it, a rules-matching bound popup would
				// auto-fire on the splash with its opt-in off.
				$force_manual = ! blocklane_pro_popups_rules_match( $settings['rules'] )
					|| ( $is_splash && empty( $settings['showOnLockScreen'] ) );
				$markup      .= blocklane_pro_popups_render_single( $post, $settings, $force_manual, false, $enums, $css );
			}
		} while ( $pending );

		return $markup;
	}

	/**
	 * Render eligible popups into the footer: markup hidden until the view
	 * script opens it, per-popup animation CSS, the block-support styles the
	 * footer-time render generates, and the front assets — enqueued only when
	 * something rendered.
	 *
	 * On the Site Lock splash (Coming Soon / Maintenance), popups are
	 * suppressed by default: ambient popups skip unless their
	 * showOnLockScreen setting opts them in, and bound popups render
	 * click-only. The splash is detected via did_action() on the gate's
	 * `blocklane_pro_site_lock_splash` action — a string-level contract, so
	 * this file stays free of plugin classes and degrades to normal behavior
	 * when the gate never fires.
	 */
	function blocklane_pro_popups_render() {
		if ( is_admin() ) {
			return;
		}

		// The gate fires this before any splash output, and only when the
		// splash actually renders (editors, unlocked visitors, and the inert
		// passwordless Coming Soon never reach it) — so did_action() is a
		// precise "we are the splash" predicate.
		$is_splash = did_action( 'blocklane_pro_site_lock_splash' ) > 0;

		$preview_id = blocklane_pro_popups_preview_id();

		$cached       = blocklane_pro_popups_published_rows();
		$settings_map = $cached['settings'];

		// A previewed draft isn't in the published set — pull it in explicitly.
		$posts = $cached['posts'];
		if ( $preview_id && ! wp_list_filter( $posts, array( 'ID' => $preview_id ) ) ) {
			$preview_post = get_post( $preview_id );
			if ( $preview_post ) {
				$posts[] = $preview_post;
			}
		}

		$markup = '';
		$css    = '';
		$enums  = blocklane_pro_popups_enums();

		// Snapshot the style engine's block-supports store BEFORE any popup
		// content renders. Core prints that store in the head (block themes) or
		// at wp_footer priority 1 (classic) — both already behind us — so any
		// rule this render generates (layout wp-container-*, elements, block
		// gap) would otherwise never be printed. The after-render delta against
		// this snapshot is compiled and printed below. Keys are the selector
		// (or "rules-group selector"). Layout selectors are content-hashed, so
		// one already present means an identical rule was already printed for a
		// page block — the popup borrows it, correctly. Element selectors
		// (wp-elements-*) are per-request unique ids and never pre-exist, so
		// they always land in the delta.
		$blocklane_pro_popups_store        = WP_Style_Engine_CSS_Rules_Store::get_store( 'block-supports' );
		$blocklane_pro_popups_rules_before = $blocklane_pro_popups_store->get_all_rules();

		// Dead-slot snapshot for the support-style flush below (#42): in a
		// block theme the enqueue target is wp_head, which already fired; in
		// a classic theme it is wp_footer:10, which still runs after this
		// render at 9 — nothing is dead here, so no flush.
		$support_hook   = wp_is_block_theme() ? 'wp_head' : '';
		$support_before = ( '' !== $support_hook && isset( $GLOBALS['wp_filter'][ $support_hook ] ) )
			? $GLOBALS['wp_filter'][ $support_hook ]->callbacks
			: array();

		// Pass 1 — ambient popups: display rules decide, triggers arm
		// naturally. Rules-excluded posts are NOT marked done: an opener
		// elsewhere on the page can still pull them in below (as manual).
		foreach ( $posts as $post ) {
			$settings   = isset( $settings_map[ $post->ID ] )
				? $settings_map[ $post->ID ]
				: blocklane_pro_popups_settings( $post->ID );
			$is_preview = $preview_id === $post->ID;

			// The splash shows no ambient popup that hasn't opted in. Checked
			// before the rules so a rules-excluded-but-bound popup still takes
			// the pass-2 path (forced manual there, splash or not). The
			// capability-gated force-open preview outranks the splash skip:
			// preview_id() demands edit_post (manage_options under the mapped
			// CPT caps), and gate bypass keys on edit_posts — for every stock
			// role those coincide and the splash is unreachable, but a custom
			// role holding one without the other must not lose its preview
			// (nor gain any splash exposure: the popup renders force-open for
			// that capable user only). Both pass-1 skips carry the
			// ! $is_preview guard — keep any future skip guarded too, or the
			// preview falls through to pass 2, whose render hardcodes
			// $is_preview false and loses the force-open.
			if ( ! $is_preview && $is_splash && empty( $settings['showOnLockScreen'] ) ) {
				continue;
			}

			if ( ! $is_preview && ! blocklane_pro_popups_rules_match( $settings['rules'] ) ) {
				continue;
			}

			$markup .= blocklane_pro_popups_render_single( $post, $settings, false, $is_preview, $enums, $css );
			blocklane_pro_popups_rendered_ids( array( $post->ID ) );
		}

		// Pass 2 — bound popups: the drain is shared with the late pass at
		// wp_footer:19, both marking the rendered-ids registry, so a bound
		// popup renders exactly once whichever pass first sees its opener.
		$markup .= blocklane_pro_popups_drain_bound( $settings_map, $is_splash, $enums, $css );

		if ( '' === $markup ) {
			return;
		}

		$base    = plugin_dir_url( __FILE__ );
		$version = (string) filemtime( __DIR__ . '/view.js' );
		wp_enqueue_style( 'blocklane-pro-popups', $base . 'style.css', array(), (string) filemtime( __DIR__ . '/style.css' ) );
		wp_enqueue_script( 'blocklane-pro-popups', $base . 'view.js', array(), $version, true );

		// The block-support rules the popup renders just generated (see the
		// snapshot above). Only the delta — everything in the snapshot was
		// already printed for the page, and re-printing it would double every
		// rule and churn specificity order.
		$new_rules = array_diff_key( $blocklane_pro_popups_store->get_all_rules(), $blocklane_pro_popups_rules_before );
		if ( $new_rules ) {
			$processor   = new WP_Style_Engine_Processor();
			$support_css = $processor->add_rules( $new_rules )->get_css( array( 'prettify' => false ) );
			if ( '' !== $support_css ) {
				echo '<style id="blocklane-pro-popups-block-supports">' . $support_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core style-engine output, same trust as core's own stored-styles print.
			}
		}

		if ( '' !== $css ) {
			echo '<style id="blocklane-pro-popups-animations">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from allowlisted keyframe names and absint ids above.
		}
		if ( '' !== $support_hook ) {
			echo blocklane_pro_popups_flush_dead_support_styles( $support_hook, $support_before, PHP_INT_MAX ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core block-support style output captured verbatim (#42).
		}
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piecewise above; content is rendered block markup.
	}
	// Priority 9, deliberately ahead of core's wp_footer:10 tenants: the
	// popup content must have RENDERED before WP_Duotone::output_footer_assets
	// emits SVG defs and before the video-modal footer prints its shared
	// overlay — both are registered earlier than this file loads, so at the
	// same priority they would run first and miss anything popup content
	// enqueued. Core's stored-styles print (priority 1, classic themes) has
	// already run either way — the delta print above covers that. Known gap:
	// in block themes duotone's CSS half IS flushed into the block-supports
	// store, but only once, head-side (WP_Duotone::output_block_styles on
	// wp_enqueue_scripts); footer-render duotone accumulates in WP_Duotone's
	// private declarations array and is never re-flushed, so popup-only
	// duotone stays unstyled there — a timing gap, not a store bypass. The
	// classic-theme footer path (priority 10) is what the 9-move rescues.
	add_action( 'wp_footer', 'blocklane_pro_popups_render', 9 );

	/**
	 * Late drain. The render at 9 must stay ahead of core's footer tenants
	 * (see above) — but that closes the bound-popup drain before any tenant
	 * at priority 9+ that renders block content: its openers registered
	 * after the drain finished, so the opener printed and its popup never
	 * did. One more drain right before core prints footer scripts
	 * (wp_print_footer_scripts, :20 — assets enqueued here still print)
	 * catches them. The contract line (popup-bindings.md): openers must
	 * exist before wp_footer:19. Content rendered this late sits past
	 * duotone defs and the head-side block-support print — the same accepted
	 * residuals as the main render's known gaps, one bucket later.
	 *
	 * ACCEPTED LIMIT (issue #436): this drain echoes at wp_footer:19, AFTER
	 * core prints the interactivity module payload at wp_footer:10 — so an
	 * interactive block rendered here misses the payload. For core/tabs
	 * inside a late-drained popup that means first paint is correct (the SSR
	 * mirror stamps inline during render, and the serialization door keeps
	 * the payload safe either way) but the per-instance tab list never
	 * reaches the client store, so tab SWITCHING is dead inside such a
	 * popup. Pre-existing, low-likelihood (a footer-registered opener whose
	 * popup contains tabs), accepted rather than re-printing the payload.
	 *
	 * @return void
	 */
	function blocklane_pro_popups_late_drain() {
		if ( is_admin() ) {
			return;
		}
		if ( ! array_diff( blocklane_pro_popups_bound_ids(), blocklane_pro_popups_rendered_ids() ) ) {
			return;
		}

		$is_splash    = did_action( 'blocklane_pro_site_lock_splash' ) > 0;
		$cached       = blocklane_pro_popups_published_rows();
		$settings_map = $cached['settings'];
		$enums        = blocklane_pro_popups_enums();
		$css          = '';

		$store  = WP_Style_Engine_CSS_Rules_Store::get_store( 'block-supports' );
		$before = $store->get_all_rules();

		// Dead-slot snapshot (#42): block themes queue onto the long-fired
		// wp_head (everything dead); classic themes queue onto wp_footer:10,
		// which this drain at 19 has already passed — dead up to 18, while
		// later slots still run naturally.
		$support_hook   = wp_is_block_theme() ? 'wp_head' : 'wp_footer';
		$support_dead   = wp_is_block_theme() ? PHP_INT_MAX : 18;
		$support_before = isset( $GLOBALS['wp_filter'][ $support_hook ] )
			? $GLOBALS['wp_filter'][ $support_hook ]->callbacks
			: array();

		$markup = blocklane_pro_popups_drain_bound( $settings_map, $is_splash, $enums, $css );
		if ( '' === $markup ) {
			return;
		}

		$base = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'blocklane-pro-popups', $base . 'style.css', array(), (string) filemtime( __DIR__ . '/style.css' ) );
		wp_enqueue_script( 'blocklane-pro-popups', $base . 'view.js', array(), (string) filemtime( __DIR__ . '/view.js' ), true );

		$new_rules = array_diff_key( $store->get_all_rules(), $before );
		if ( $new_rules ) {
			$processor   = new WP_Style_Engine_Processor();
			$support_css = $processor->add_rules( $new_rules )->get_css( array( 'prettify' => false ) );
			if ( '' !== $support_css ) {
				echo '<style id="blocklane-pro-popups-block-supports-late">' . $support_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core style-engine output, same trust as core's own stored-styles print.
			}
		}
		if ( '' !== $css ) {
			echo '<style id="blocklane-pro-popups-animations-late">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from allowlisted keyframe names and absint ids above.
		}
		echo blocklane_pro_popups_flush_dead_support_styles( $support_hook, $support_before, $support_dead ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core block-support style output captured verbatim (#42).
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piecewise in render_single; content is rendered block markup.
	}
	add_action( 'wp_footer', 'blocklane_pro_popups_late_drain', 19 );

	/**
	 * Popup content renders at wp_footer, after the script-module import map
	 * printed (head in block themes; wp_footer:10 in classic — both ahead of
	 * the drains). A popup-only interactive block's view module then prints
	 * as a tag, but its "@wordpress/interactivity" import can't resolve —
	 * the block renders inert with only a console error. Pre-enqueue the
	 * runtime whenever any published popup's content carries a known
	 * interactivity consumer, so the map includes it before it prints. A
	 * cheap block-comment sniff, deliberately over-inclusive (an unused
	 * preload costs a few KB); third-party interactive blocks inside popups
	 * stay out of contract, and popups past the ambient query cap are not
	 * scanned.
	 *
	 * @return void
	 */
	function blocklane_pro_popups_preload_interactivity() {
		if ( is_admin() || ! function_exists( 'wp_enqueue_script_module' ) ) {
			return;
		}
		$cached      = blocklane_pro_popups_published_rows();
		$need_runtime = false;
		$need_router  = false;
		foreach ( $cached['posts'] as $post ) {
			$content = (string) $post->post_content;
			if ( false === strpos( $content, '<!-- wp:' ) ) {
				continue;
			}
			if ( preg_match( '/"lightbox"|<!-- wp:(navigation|details|accordion|file|search)[ \{]/', $content ) ) {
				$need_runtime = true;
			}
			if ( false !== strpos( $content, '"enhancedPagination":true' ) ) {
				$need_runtime = true;
				$need_router  = true;
			}
		}
		if ( $need_runtime ) {
			wp_enqueue_script_module( '@wordpress/interactivity' );
		}
		if ( $need_router ) {
			wp_enqueue_script_module( '@wordpress/interactivity-router' );
		}
	}
	add_action( 'wp_enqueue_scripts', 'blocklane_pro_popups_preload_interactivity' );

	/**
	 * The settings-panel bundle on the popup CPT's editor. Lives in the
	 * runtime (not the plugin integration) so popups stay fully DESIGNABLE
	 * from the bake alone — the same survival story as the Mega Menu block,
	 * whose editor UI ships in its bake via block.json. build/index.js is
	 * sits beside this file and resolves relative to it.
	 */
	function blocklane_pro_popups_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || BLOCKLANE_PRO_POPUPS_CPT !== $screen->post_type ) {
			return;
		}

		$asset_path = __DIR__ . '/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}
		$asset = require $asset_path;

		wp_enqueue_script(
			'blocklane-pro-popups-editor',
			plugin_dir_url( __FILE__ ) . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( __DIR__ . '/build/index.css' ) ) {
			wp_enqueue_style(
				'blocklane-pro-popups-editor',
				plugin_dir_url( __FILE__ ) . 'build/index.css',
				array(),
				$asset['version']
			);
		}
	}
	add_action( 'enqueue_block_editor_assets', 'blocklane_pro_popups_editor_assets' );

	/**
	 * Slot the Popups submenu right after SEO — or after Content Types when
	 * the SEO screen is hidden — under the Blocklane Pro menu (WP appends CPT
	 * submenus at the end). The bare 'blocklane-pro' slug is the same soft
	 * dependency as show_in_menu above; without the plugin the CPT has its
	 * own top-level menu and this no-ops.
	 */
	function blocklane_pro_popups_menu_order() {
		global $submenu;
		if ( empty( $submenu['blocklane-pro'] ) || ! is_array( $submenu['blocklane-pro'] ) ) {
			return;
		}

		$items       = array_values( $submenu['blocklane-pro'] );
		$popup_index = null;
		foreach ( $items as $i => $item ) {
			if ( false !== strpos( (string) ( $item[2] ?? '' ), 'post_type=' . BLOCKLANE_PRO_POPUPS_CPT ) ) {
				$popup_index = $i;
				break;
			}
		}
		if ( null === $popup_index ) {
			return;
		}

		$popup = $items[ $popup_index ];
		unset( $items[ $popup_index ] );
		$items = array_values( $items );

		// Exact slug matches — a substring test would also capture any
		// future screen whose slug merely starts with 'seo'.
		$target = count( $items );
		foreach ( array( 'blocklane-pro&screen=seo', 'blocklane-pro&screen=content-types' ) as $anchor ) {
			foreach ( $items as $i => $item ) {
				if ( ( $item[2] ?? '' ) === $anchor ) {
					$target = $i + 1;
					break 2;
				}
			}
		}
		array_splice( $items, $target, 0, array( $popup ) );
		$submenu['blocklane-pro'] = $items;
	}
	add_action( 'admin_menu', 'blocklane_pro_popups_menu_order', 99 );
}
