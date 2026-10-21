<?php
/**
 * Icon collection — the bundled set's name => label table.
 *
 * Hand-kept data of the runtime:icon-collection unit: one row per file in
 * svg/, and nothing generates it. It moved here from the companion theme on
 * 2026-09-21 (theme review §5/§6: a theme may not register a plugin's
 * namespace), and with it the 75 Phosphor glyphs the theme had shipped since
 * 0.8.0 — which is why both editions carry it. Withdrawing a name blanks
 * every core/icon block that already names it.
 *
 * A sibling, not a source: inc/extensions/loader/button-icons/icons.json is
 * the Button Icons control's own glyph data (viewBox + paths, no labels).
 * The two sets overlap by coincidence of origin; neither is generated from
 * the other.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'arrow-circle-right' => 'Arrow Circle Right',
	'arrow-circle-right-bold' => 'Arrow Circle Right Bold',
	'arrow-circle-right-fill' => 'Arrow Circle Right Fill',
	'arrow-left' => 'Arrow Left',
	'arrow-left-bold' => 'Arrow Left Bold',
	'arrow-left-fill' => 'Arrow Left Fill',
	'arrow-right' => 'Arrow Right',
	'arrow-right-bold' => 'Arrow Right Bold',
	'arrow-right-fill' => 'Arrow Right Fill',
	'arrow-square-out' => 'External Link',
	'arrow-square-out-bold' => 'External Link Bold',
	'arrow-square-out-fill' => 'External Link Fill',
	'arrow-up-right' => 'Arrow Up-Right',
	'arrow-up-right-bold' => 'Arrow Up-Right Bold',
	'arrow-up-right-fill' => 'Arrow Up-Right Fill',
	'arrows-clockwise' => 'Refresh',
	'arrows-clockwise-bold' => 'Refresh Bold',
	'arrows-clockwise-fill' => 'Refresh Fill',
	'calendar-blank' => 'Calendar',
	'calendar-blank-bold' => 'Calendar Bold',
	'calendar-blank-fill' => 'Calendar Fill',
	'caret-right' => 'Chevron Right',
	'caret-right-bold' => 'Chevron Right Bold',
	'caret-right-fill' => 'Chevron Right Fill',
	'check' => 'Check',
	'check-bold' => 'Check Bold',
	'check-fill' => 'Check Fill',
	'credit-card' => 'Credit Card',
	'credit-card-bold' => 'Credit Card Bold',
	'credit-card-fill' => 'Credit Card Fill',
	'download-simple' => 'Download',
	'download-simple-bold' => 'Download Bold',
	'download-simple-fill' => 'Download Fill',
	'envelope' => 'Envelope',
	'envelope-bold' => 'Envelope Bold',
	'envelope-fill' => 'Envelope Fill',
	'heart' => 'Favorite',
	'heart-bold' => 'Favorite Bold',
	'heart-fill' => 'Favorite Fill',
	'info' => 'Info',
	'info-bold' => 'Info Bold',
	'info-fill' => 'Info Fill',
	'magnifying-glass' => 'Search',
	'magnifying-glass-bold' => 'Search Bold',
	'magnifying-glass-fill' => 'Search Fill',
	'paper-plane-tilt' => 'Paper Plane',
	'paper-plane-tilt-bold' => 'Paper Plane Bold',
	'paper-plane-tilt-fill' => 'Paper Plane Fill',
	'phone' => 'Phone',
	'phone-bold' => 'Phone Bold',
	'phone-fill' => 'Phone Fill',
	'play' => 'Play',
	'play-bold' => 'Play Bold',
	'play-fill' => 'Play Fill',
	'plus' => 'Plus',
	'plus-bold' => 'Plus Bold',
	'plus-fill' => 'Plus Fill',
	'share-network' => 'Share',
	'share-network-bold' => 'Share Bold',
	'share-network-fill' => 'Share Fill',
	'shopping-bag' => 'Shopping Bag',
	'shopping-bag-bold' => 'Shopping Bag Bold',
	'shopping-bag-fill' => 'Shopping Bag Fill',
	'shopping-cart-simple' => 'Shopping Cart',
	'shopping-cart-simple-bold' => 'Shopping Cart Bold',
	'shopping-cart-simple-fill' => 'Shopping Cart Fill',
	'sign-in' => 'Sign In',
	'sign-in-bold' => 'Sign In Bold',
	'sign-in-fill' => 'Sign In Fill',
	'upload-simple' => 'Upload',
	'upload-simple-bold' => 'Upload Bold',
	'upload-simple-fill' => 'Upload Fill',
	'user' => 'User',
	'user-bold' => 'User Bold',
	'user-fill' => 'User Fill',
);
