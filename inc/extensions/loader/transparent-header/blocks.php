<?php
/**
 * Transparent Header — per-block render filters.
 *
 * While a header-area template part renders with the overlay active, the
 * participating blocks — core/navigation, core/site-title, core/site-logo —
 * get render-added marker classes and instance CSS custom properties on their
 * own wrapper tags. The stylesheet's rules chain those markers under
 * `.is-th-transparent`, naming exactly the leaf elements they style, so
 * nothing here can repaint a container that was never named (the failure
 * mode of the retired `*:not(...)` blanket, 0.9.1's mega-panel leak).
 *
 * Everything added here is render-added, never serialized: with Pro off the
 * markup is pure core. A block rendering outside the header part (or on a
 * page where the overlay resolves inactive) is left byte-untouched.
 *
 * Required from transparent-header.php, so it rides the same
 * extension-enabled gate and the part-state machinery is always defined.
 *
 * @package blocklane-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The captured target attrs when the overlay is active for the header part
 * currently rendering, or null.
 *
 * Mirrors the part filter's own activation logic exactly (captured must be an
 * array — the positional-fallback capture included — and the page override
 * runs through the same resolver). The captured attrs are resolved at part
 * OPEN, before any inner block renders, which is the documented cross-file
 * contract that makes reading them from an inner block's filter safe.
 *
 * @return array<string, mixed>|null Target attrs, or null when inactive.
 */
function blocklane_pro_th_blocks_active_target() {
	$state = blocklane_pro_transparent_header_part_state( 'read' );
	if ( 0 === $state['depth'] || ! is_array( $state['captured'] ) ) {
		return null;
	}
	if ( ! blocklane_pro_transparent_header_is_active( ! empty( $state['captured']['blocklaneProTransparentHeader'] ) ) ) {
		return null;
	}

	return $state['captured'];
}

/**
 * Depth counter for Pro mega-menu panels currently rendering.
 *
 * A mega panel is a template part with arbitrary content — it can contain a
 * navigation block — and it paints its own background, so the transparent
 * chrome styling must not reach inside it. The panel declares its own
 * boundary here (the container owns the knowledge, per the popups-provenance
 * lesson — never a central denylist): pre_render_block increments before the
 * panel's inner content renders, the block's render filter decrements after,
 * and the per-block filters below skip while the counter is up.
 *
 * @param int $delta +1 entering a mega block's render, -1 leaving, 0 to read.
 * @return int Current depth.
 */
function blocklane_pro_th_blocks_mega_depth( $delta = 0 ) {
	static $depth = 0;
	$depth = max( 0, $depth + $delta );

	return $depth;
}

/**
 * Open the mega-block scope before its content (the panel part) renders.
 *
 * Runs LAST on pre_render_block and passes a short-circuit through untouched
 * — the same guard the header-part opener uses: a short-circuited block's
 * render filter never fires, and an unbalanced open would poison the counter
 * for the rest of the request.
 *
 * @param string|null $pre_render Short-circuit value (passed through).
 * @param array<string, mixed> $block The parsed block.
 * @return string|null
 */
function blocklane_pro_th_blocks_open_mega( $pre_render, $block ) {
	if ( null !== $pre_render ) {
		return $pre_render;
	}
	if ( 'blocklane-pro/mega-menu' === ( $block['blockName'] ?? '' ) ) {
		blocklane_pro_th_blocks_mega_depth( 1 );
	}

	return $pre_render;
}
add_filter( 'pre_render_block', 'blocklane_pro_th_blocks_open_mega', PHP_INT_MAX, 2 );

/**
 * Close the mega-block scope once its content has rendered.
 *
 * @param string $block_content The rendered block.
 * @return string
 */
function blocklane_pro_th_blocks_close_mega( $block_content ) {
	blocklane_pro_th_blocks_mega_depth( -1 );

	return $block_content;
}
add_filter( 'render_block_blocklane-pro/mega-menu', 'blocklane_pro_th_blocks_close_mega', PHP_INT_MAX );

/**
 * Whether the per-block filters should touch the block rendering right now.
 *
 * @return bool
 */
function blocklane_pro_th_blocks_applies() {
	return 0 === blocklane_pro_th_blocks_mega_depth()
		&& null !== blocklane_pro_th_blocks_active_target();
}

/**
 * Add marker classes and instance vars to the block's wrapper tag.
 *
 * The wrapper is the first tag carrying the block's own class — NOT the
 * first tag in the content: rendered block output can lead with an inline
 * <style> tag (layout/support styles on this site do), and decorating that
 * silently styles nothing. Keying on the wrapper class is the same choice
 * the part filter makes with `wp-block-group`.
 *
 * @param string                $block_content Rendered block HTML.
 * @param string                $wrapper_class The block's wrapper class.
 * @param list<string>          $classes       Classes to add.
 * @param array<string, string> $vars          name => resolved CSS value.
 * @return string
 */
function blocklane_pro_th_blocks_decorate( $block_content, $wrapper_class, $classes, $vars ) {
	$processor = new WP_HTML_Tag_Processor( $block_content );
	$found     = false;
	while ( $processor->next_tag() ) {
		if ( true === $processor->has_class( $wrapper_class ) ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		return $block_content;
	}
	foreach ( $classes as $class ) {
		$processor->add_class( $class );
	}
	if ( $vars ) {
		$declarations = array();
		foreach ( $vars as $name => $value ) {
			$declarations[] = $name . ': ' . $value . ';';
		}
		$existing = (string) $processor->get_attribute( 'style' );
		$existing = '' !== $existing ? rtrim( $existing, '; ' ) . '; ' : '';
		$processor->set_attribute( 'style', $existing . implode( ' ', $declarations ) );
	}

	return $processor->get_updated_html();
}

/**
 * The color-slot roster: per block, the wrapper class the decorate keys on,
 * an always-on chrome marker (present = the block participates; the blocks
 * whose text sits bare on the stripped background carry one so their
 * default-white static rule fires), and the slots — attribute => optional
 * per-slot marker + the instance var the stylesheet consumes.
 *
 * Chrome-text blocks (marker, white default): navigation, site title, site
 * tagline, the search label. Self-branded blocks (no marker, opt-in only —
 * ruling D1, buttons stay branded): button, social icons. Every block
 * rendering in the part is decorated; one outside the target group has no
 * `.is-th-transparent` ancestor, so its markers are inert by scoping — the
 * same reason a value saved on a footer block does nothing (ruling D4).
 *
 * @return array<string, array{wrapper: string, marker: string|null, slots: array<string, array{class: string|null, var: string}>}>
 */
function blocklane_pro_th_blocks_roster() {
	return array(
		'core/navigation'   => array(
			'wrapper' => 'wp-block-navigation',
			'marker'  => 'blocklane-pro-th-nav',
			'slots'   => array(
				'blocklaneProTransparentTextColor'    => array(
					'class' => null,
					'var'   => '--blocklane-pro-th-nav-text',
				),
				'blocklaneProTransparentHoverColor'   => array(
					'class' => 'blocklane-pro-th-nav-has-hover',
					'var'   => '--blocklane-pro-th-nav-hover',
				),
				'blocklaneProTransparentCurrentColor' => array(
					'class' => 'blocklane-pro-th-nav-has-current',
					'var'   => '--blocklane-pro-th-nav-current',
				),
			),
		),
		'core/site-title'   => array(
			'wrapper' => 'wp-block-site-title',
			'marker'  => 'blocklane-pro-th-title',
			'slots'   => array(
				'blocklaneProTransparentTextColor' => array(
					'class' => null,
					'var'   => '--blocklane-pro-th-title-text',
				),
			),
		),
		'core/site-tagline' => array(
			'wrapper' => 'wp-block-site-tagline',
			'marker'  => 'blocklane-pro-th-tagline',
			'slots'   => array(
				'blocklaneProTransparentTextColor' => array(
					'class' => null,
					'var'   => '--blocklane-pro-th-tagline-text',
				),
			),
		),
		'core/search'       => array(
			'wrapper' => 'wp-block-search',
			'marker'  => 'blocklane-pro-th-search',
			'slots'   => array(
				'blocklaneProTransparentTextColor' => array(
					'class' => null,
					'var'   => '--blocklane-pro-th-search-text',
				),
			),
		),
		'core/loginout'     => array(
			'wrapper' => 'wp-block-loginout',
			'marker'  => 'blocklane-pro-th-loginout',
			'slots'   => array(
				'blocklaneProTransparentTextColor' => array(
					'class' => null,
					'var'   => '--blocklane-pro-th-loginout-text',
				),
			),
		),
		'core/button'       => array(
			'wrapper' => 'wp-block-button',
			'marker'  => null,
			'slots'   => array(
				'blocklaneProTransparentTextColor'  => array(
					'class' => 'blocklane-pro-th-btn-has-text',
					'var'   => '--blocklane-pro-th-btn-text',
				),
				'blocklaneProTransparentBackground' => array(
					'class' => 'blocklane-pro-th-btn-has-bg',
					'var'   => '--blocklane-pro-th-btn-bg',
				),
			),
		),
		// The only slot in the roster keyed to the SOLID state rather than the
		// transparent one. A row inside the header — a floating bar, a pill —
		// that carries no background of its own is already transparent over the
		// hero; what it lacks is a color for once the header has solidified.
		// Nothing is stripped, so nothing needs restoring: the color simply
		// switches on. Opt-in (marker null), like the other self-branded slots.
		'core/group'        => array(
			'wrapper' => 'wp-block-group',
			'marker'  => null,
			'slots'   => array(
				'blocklaneProThSolidBackground' => array(
					'class' => 'blocklane-pro-th-row-has-solidbg',
					'var'   => '--blocklane-pro-th-row-solid-bg',
				),
			),
		),
		'core/social-links' => array(
			'wrapper' => 'wp-block-social-links',
			'marker'  => null,
			'slots'   => array(
				'blocklaneProTransparentIconColor'      => array(
					'class' => 'blocklane-pro-th-social-has-icon',
					'var'   => '--blocklane-pro-th-social-icon',
				),
				'blocklaneProTransparentIconBackground' => array(
					'class' => 'blocklane-pro-th-social-has-iconbg',
					'var'   => '--blocklane-pro-th-social-iconbg',
				),
			),
		),
	);
}

/**
 * One render filter for every color-slot block in the roster.
 *
 * @param string               $block_content The rendered block.
 * @param array<string, mixed> $block         The parsed block.
 * @return string
 */
function blocklane_pro_th_blocks_render_slots( $block_content, $block ) {
	if ( '' === trim( $block_content ) || ! blocklane_pro_th_blocks_applies() ) {
		return $block_content;
	}

	$roster = blocklane_pro_th_blocks_roster();
	$config = $roster[ (string) ( $block['blockName'] ?? '' ) ] ?? null;
	if ( null === $config ) {
		return $block_content;
	}

	$attrs   = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$classes = null !== $config['marker'] ? array( $config['marker'] ) : array();
	$vars    = array();

	foreach ( $config['slots'] as $attribute => $slot ) {
		$value = blocklane_pro_ext_resolve_preset_color( $attrs[ $attribute ] ?? '' );
		if ( '' === $value ) {
			continue;
		}
		if ( null !== $slot['class'] ) {
			$classes[] = $slot['class'];
		}
		$vars[ $slot['var'] ] = $value;
	}

	if ( ! $classes && ! $vars ) {
		return $block_content;
	}

	return blocklane_pro_th_blocks_decorate( $block_content, $config['wrapper'], $classes, $vars );
}
foreach ( array_keys( blocklane_pro_th_blocks_roster() ) as $blocklane_pro_th_block_name ) {
	add_filter( 'render_block_' . $blocklane_pro_th_block_name, 'blocklane_pro_th_blocks_render_slots', 10, 2 );
}
unset( $blocklane_pro_th_block_name );

/**
 * Site logo: the alternate transparent image (#135) and the filter treatment.
 *
 * Both images render — a `src` swap would flash on the first scroll while
 * the other image loads — and the state class picks which is visible. The
 * alternate is decorative markup: permanently `aria-hidden` with an empty
 * alt, so assistive tech announces one logo in both states. It mirrors the
 * original's width so the header does not reflow at the swap.
 *
 * A stored id whose attachment is gone renders NO alternate (the original
 * stays visible in both states) rather than an empty slot — the
 * distinguishable-miss rule from the silent-icons incident.
 *
 * @param string               $block_content The rendered block.
 * @param array<string, mixed> $block         The parsed block.
 * @return string
 */
function blocklane_pro_th_blocks_render_site_logo( $block_content, $block ) {
	if ( '' === trim( $block_content ) || ! blocklane_pro_th_blocks_applies() ) {
		return $block_content;
	}

	$attrs     = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$treatment = (string) ( $attrs['blocklaneProTransparentLogo'] ?? '' );
	$alt_id    = (int) ( $attrs['blocklaneProTransparentLogoId'] ?? 0 );

	$alt_html = '';
	if ( $alt_id > 0 ) {
		// Mirror the original image's rendered width so the swap cannot
		// reflow the header; height stays auto for a differing aspect ratio.
		$probe = new WP_HTML_Tag_Processor( $block_content );
		$width = '';
		if ( $probe->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			$width = (string) $probe->get_attribute( 'width' );
		}
		$img_attrs = array(
			'class'       => 'blocklane-pro-th-alt-logo',
			'alt'         => '',
			'aria-hidden' => 'true',
			'style'       => 'height:auto;',
		);
		if ( '' !== $width && is_numeric( $width ) ) {
			$img_attrs['style'] = 'width:' . (int) $width . 'px;height:auto;';
		}
		$alt_html = wp_get_attachment_image( $alt_id, 'full', false, $img_attrs );
	}

	if ( '' !== $alt_html ) {
		// Inside the logo link when there is one, so the alternate shares the
		// home link; before the closing wrapper otherwise. String surgery on
		// core-controlled markup — the tag processor cannot insert.
		$anchor_close = strpos( $block_content, '</a>' );
		if ( false !== $anchor_close ) {
			$block_content = substr_replace( $block_content, $alt_html, $anchor_close, 0 );
		} else {
			$wrapper_close = strrpos( $block_content, '</div>' );
			if ( false !== $wrapper_close ) {
				$block_content = substr_replace( $block_content, $alt_html, $wrapper_close, 0 );
			}
		}

		return blocklane_pro_th_blocks_decorate( $block_content, 'wp-block-site-logo', array( 'blocklane-pro-th-has-alt' ), array() );
	}

	// Treatment only without an alternate: the image IS the transparent look
	// when one is set, and filtering it to a silhouette would defeat picking
	// it. With the attachment missing this degrades to the treatment.
	if ( 'white' === $treatment || 'black' === $treatment ) {
		return blocklane_pro_th_blocks_decorate( $block_content, 'wp-block-site-logo', array( 'blocklane-pro-th-logo-' . $treatment ), array() );
	}

	return $block_content;
}
add_filter( 'render_block_core/site-logo', 'blocklane_pro_th_blocks_render_site_logo', 10, 2 );
