<?php
/**
 * Menu Button: a label, an icon and a button look for the navigation's
 * overlay toggle (spec 2026-10-15-navigation-toggle).
 *
 * Core draws the button that opens a collapsed navigation with its own menu
 * icon, named "Open menu", or the word "Menu". Three attributes on the
 * navigation change that button at render: a visible label (which then
 * names the button and the overlay's dialog), one of five core icons, and
 * the theme's button look. A navigation without them renders as core does,
 * byte for byte. Core's open, close and focus-return behavior is untouched:
 * only the button's contents, its aria-label and its classes change.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The icons a toggle may use: @wordpress/icons 17.0.0, library/<name>.svg.
 * `layout` is the glyph the Site Editor shows for Templates.
 *
 * @return array<string, string> Icon name => its paths.
 */
function blocklane_pro_navigation_toggle_icons(): array {
	return array(
		'menu'     => '<path d="M5 12H19M5 18.25H19M5 5.75H19" vector-effect="non-scaling-stroke"></path>',
		'layout'   => '<path d="M9.75 19.25H18C18.6904 19.25 19.25 18.6904 19.25 18V9.75M9.75 19.25H6C5.30964 19.25 4.75 18.6904 4.75 18V9.75M9.75 19.25V9.75M19.25 9.75V6C19.25 5.30964 18.6904 4.75 18 4.75H6C5.30964 4.75 4.75 5.30964 4.75 6V9.75M19.25 9.75H9.75M9.75 9.75H4.75" vector-effect="non-scaling-stroke"></path>',
		'category' => '<path d="M4.75 6C4.75 5.30964 5.30964 4.75 6 4.75H9C9.69036 4.75 10.25 5.30964 10.25 6V9C10.25 9.69036 9.69036 10.25 9 10.25H6C5.30964 10.25 4.75 9.69036 4.75 9V6Z" vector-effect="non-scaling-stroke"></path><path d="M4.75 15C4.75 14.3096 5.30964 13.75 6 13.75H9C9.69036 13.75 10.25 14.3096 10.25 15V18C10.25 18.6904 9.69036 19.25 9 19.25H6C5.30964 19.25 4.75 18.6904 4.75 18V15Z" vector-effect="non-scaling-stroke"></path><path d="M13.75 6C13.75 5.30964 14.3096 4.75 15 4.75H18C18.6904 4.75 19.25 5.30964 19.25 6V9C19.25 9.69036 18.6904 10.25 18 10.25H15C14.3096 10.25 13.75 9.69036 13.75 9V6Z" vector-effect="non-scaling-stroke"></path><path d="M13.75 15C13.75 14.3096 14.3096 13.75 15 13.75H18C18.6904 13.75 19.25 14.3096 19.25 15V18C19.25 18.6904 18.6904 19.25 18 19.25H15C14.3096 19.25 13.75 18.6904 13.75 18V15Z" vector-effect="non-scaling-stroke"></path>',
		'grid'     => '<path d="M12 4.75H6C5.30964 4.75 4.75 5.30964 4.75 6V12M12 4.75H18C18.6904 4.75 19.25 5.30964 19.25 6V12M12 4.75V19.25M12 19.25H18C18.6904 19.25 19.25 18.6904 19.25 18V12M12 19.25H6C5.30964 19.25 4.75 18.6904 4.75 18V12M19.25 12H4.75" vector-effect="non-scaling-stroke"></path>',
		'list'     => '<path d="M4 17.5H20M12 12H20M4 6.5H20" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path><path d="M6 13.25C6.69036 13.25 7.25 12.6904 7.25 12C7.25 11.3096 6.69036 10.75 6 10.75C5.30964 10.75 4.75 11.3096 4.75 12C4.75 12.6904 5.30964 13.25 6 13.25Z" fill="currentColor" vector-effect="non-scaling-stroke"></path>',
	);
}

/** The longest label a toggle prints. */
const BLOCKLANE_PRO_NAVIGATION_TOGGLE_LABEL_MAX = 60;

/**
 * A navigation's toggle settings, whatever its block comment holds: a
 * label is a non-empty trimmed string (cut to the maximum), an icon is a
 * key of the icon table, and the look is the button look or core's.
 *
 * @param array<mixed> $attrs Block attributes.
 * @return array{label: string, icon: string, button: bool}
 */
function blocklane_pro_navigation_toggle_settings( array $attrs ): array {
	$label = $attrs['blocklaneToggleLabel'] ?? '';
	$label = is_string( $label ) ? trim( wp_check_invalid_utf8( $label ) ) : '';
	// A label of only spaces, invisible or combining characters would name
	// the button with nothing: it is no label.
	if ( '' === (string) preg_replace( '/[\p{Z}\p{C}\p{M}]+/u', '', $label ) ) {
		$label = '';
	}
	$icon = $attrs['blocklaneToggleIcon'] ?? '';
	return array(
		'label'  => mb_substr( $label, 0, BLOCKLANE_PRO_NAVIGATION_TOGGLE_LABEL_MAX ),
		'icon'   => is_string( $icon ) && array_key_exists( $icon, blocklane_pro_navigation_toggle_icons() ) ? $icon : '',
		'button' => 'button' === ( $attrs['blocklaneToggleStyle'] ?? '' ),
	);
}

/**
 * The toggle's icon as printed: the paths in core's own SVG frame, hidden
 * from assistive technology.
 *
 * @param string $name A key of the icon table.
 * @return string
 */
function blocklane_pro_navigation_toggle_svg( string $name ): string {
	return '<svg class="blocklane-navigation-toggle__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">' . blocklane_pro_navigation_toggle_icons()[ $name ] . '</svg>';
}

/**
 * Change a collapsed navigation's open button as its attributes ask, and
 * name its overlay with the label. A navigation without them, or without
 * an open button, is returned untouched.
 *
 * @param mixed        $content Rendered navigation (another filter may have
 *                              suppressed it with a non-string, which passes through).
 * @param array<mixed> $block   Parsed block.
 * @return mixed
 */
function blocklane_pro_navigation_toggle_render( mixed $content, array $block ): mixed {
	if ( ! is_string( $content ) ) {
		return $content;
	}
	$settings = blocklane_pro_navigation_toggle_settings( is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array() );
	if ( '' === $settings['label'] && '' === $settings['icon'] && ! $settings['button'] ) {
		return $content;
	}
	$tags = new \blocklane_pro\Navigation_Toggle_Markup( $content );

	// The overlay's dialog takes its name from the nav's context, which core
	// sets to "Menu": the label names it instead.
	if ( '' !== $settings['label'] && $tags->next_tag( array( 'tag_name' => 'NAV' ) ) ) {
		$context = $tags->get_attribute( 'data-wp-context' );
		// The context may carry a store namespace before its JSON
		// ("core/navigation::{…}"); it is kept as it was.
		$prefix = '';
		if ( is_string( $context ) && 1 === preg_match( '/^([^:{]+::)/', $context, $m ) ) {
			$prefix  = $m[1];
			$context = substr( $context, strlen( $prefix ) );
		}
		$decoded = is_string( $context ) ? json_decode( $context, true ) : null;
		if ( is_array( $decoded ) && array_key_exists( 'ariaLabel', $decoded ) ) {
			$decoded['ariaLabel'] = $settings['label'];
			$encoded              = wp_json_encode( $decoded, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
			if ( is_string( $encoded ) ) {
				$tags->set_attribute( 'data-wp-context', $prefix . $encoded );
			}
		}
	}

	$found = false;
	while ( $tags->next_tag( array( 'tag_name' => 'BUTTON' ) ) ) {
		if ( $tags->has_class( 'wp-block-navigation__responsive-container-open' ) ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		return $content;
	}
	$tags->add_class( 'blocklane-navigation-toggle' );
	if ( $settings['button'] ) {
		$tags->add_class( 'wp-element-button' );
		$tags->add_class( 'blocklane-navigation-toggle--button' );
	}
	if ( '' !== $settings['label'] ) {
		// The visible text names the button (WCAG 2.5.3, Label in Name).
		$tags->remove_attribute( 'aria-label' );
	}
	$tags->set_bookmark( 'blocklane-toggle-button' );
	$current = $tags->inner();
	$tags->seek( 'blocklane-toggle-button' );
	$tags->release_bookmark( 'blocklane-toggle-button' );
	if ( null === $current ) {
		return $content;
	}
	// The icon: the chosen one, else core's own SVG as core wrote it (a
	// toggle core draws as its word has none). The label follows it; with
	// no label, core's word stays.
	$core_icon = blocklane_pro_navigation_toggle_has_icon( $current );
	if ( '' !== $settings['icon'] ) {
		$icon = blocklane_pro_navigation_toggle_svg( $settings['icon'] );
	} else {
		$icon = $core_icon ? $current : '';
	}
	if ( '' !== $settings['label'] ) {
		$text = '<span class="blocklane-navigation-toggle__label">' . esc_html( $settings['label'] ) . '</span>';
	} else {
		$text = $core_icon ? '' : $current;
	}
	if ( ! $tags->replace_inner( $icon . $text ) ) {
		return $content;
	}
	blocklane_pro_navigation_toggle_mark_used();
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/navigation', 'blocklane_pro_navigation_toggle_render', 10, 2 );

/**
 * Whether core drew the toggle as an icon (an SVG) rather than its word.
 *
 * @param string $inner The toggle's contents as core wrote them.
 * @return bool
 */
function blocklane_pro_navigation_toggle_has_icon( string $inner ): bool {
	return ( new WP_HTML_Tag_Processor( $inner ) )->next_tag( array( 'tag_name' => 'SVG' ) );
}

/**
 * Enqueue the toggle's stylesheet once per document, on the front end.
 */
function blocklane_pro_navigation_toggle_mark_used(): void {
	if ( is_admin() || wp_style_is( 'blocklane-pro-navigation-toggle', 'enqueued' ) ) {
		return;
	}
	wp_enqueue_style( 'blocklane-pro-navigation-toggle', blocklane_pro_ext_base_url() . '/loader/navigation-toggle/navigation-toggle.css', array(), blocklane_pro_ext_version() );
}
