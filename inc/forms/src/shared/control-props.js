/**
 * Canvas mirror of blocklane_pro_forms_control_style() in runtime.php: the
 * field blocks skip-serialize color + border so those styles land on the
 * actual control (not the wrapper) in the front render — which also means
 * the editor skips auto-applying them, so each edit view must merge these
 * props onto its canvas control itself (core Search block precedent).
 * Without this, per-field colors/borders render on the front but not in the
 * canvas. The consent checkbox is the exception on both sides: it has no
 * styleable text control, so the style props go on its row wrapper instead
 * (matching field_render_base's $style_on_wrapper mode).
 */
import {
	__experimentalUseBorderProps as useBorderProps,
	__experimentalUseColorProps as useColorProps,
} from '@wordpress/block-editor';

/**
 * The block's skip-serialized border and color classes/styles, without the
 * shared control class — spreadable onto a control or a wrapper.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} { className, style }.
 */
export function useControlStyleProps( attributes ) {
	const borderProps = useBorderProps( attributes );
	const colorProps = useColorProps( attributes );

	return {
		className: [ borderProps.className, colorProps.className ]
			.filter( Boolean )
			.join( ' ' ),
		style: { ...borderProps.style, ...colorProps.style },
	};
}

/**
 * Props for a canvas field control: the shared control class plus the style
 * props above.
 *
 * @param {Object} styleProps Result of useControlStyleProps().
 * @return {Object} { className, style } to spread onto the control.
 */
export function toControlProps( styleProps ) {
	return {
		className: [ 'blocklane-form__control', styleProps.className ]
			.filter( Boolean )
			.join( ' ' ),
		style: styleProps.style,
	};
}
