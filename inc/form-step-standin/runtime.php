<?php
/**
 * Form Step — the block editor's stand-in (data preservation, both editions).
 *
 * WHAT IT IS. While the real blocklane/form-step block is not registered on
 * the server (the free edition, whose registrar's view,
 * blocklane_pro_forms_known_blocks(), leaves it out because block:form-step
 * is a Pro contributing unit, edition-manifest.json rule 6), the editor
 * bundle built from src/ registers the block type CLIENT-SIDE with
 * `inserter: false`: an editor opening a form authored with Pro sees each
 * step as a transparent container whose fields stay editable, instead of
 * core's missing-block placeholder. That placeholder is the data-loss shape:
 * core wraps an unregistered block AND ITS INNER BLOCKS into one core/missing
 * whose fields are raw HTML, with "Convert to HTML" one click away. The
 * bundle's `save` is byte-identical to Pro's (`<InnerBlocks.Content />`,
 * inc/forms/src/form-step/index.js), so a free save re-serializes a
 * Pro-authored stepped form unchanged and Pro finds its steps whole when it
 * returns.
 *
 * WHAT IT IS NOT. A capability. It hands a never-paid user no feature: the
 * front end renders the fields flat (form/shell.php asks the same view
 * before it treats a child as a step), the inserter never offers the block,
 * and the canvas shows no step chrome — no label, no Steps panel, no lock
 * pass (the form's edit.js reads STEPS_KNOWN from the same view). It is the
 * sixth entry of the edition doctrine's data-preservation allowlist (spec
 * 2026-09-24 D4), which is why `runtime:form-step-standin` is carried by
 * BOTH editions with no edition filter of its own.
 *
 * THE DOORS are the helper's, named in inc/class-blocklane-pro-standin.php:
 * the registry, the extra gate, safe mode, the enqueue and its alarm, the
 * translations, the localized inspector copy. This file names what is the
 * form step's alone: the block, its script handle, the unit whose presence
 * registers the real block, what the inspector says here per reason (a
 * closure, so the __() calls run at enqueue, never at plugins_loaded), and
 * the extra gate, the forms toggle (the suite registers nothing while it is
 * off, inc/forms/runtime.php, the same Content_Toggle::on( 'forms' ); with it
 * off, Pro- and free-authored forms behave alike in both editions: core's
 * placeholder for every suite block). It DECLARES NOTHING and defines no
 * constant; a second require would reach Standin::register(), which refuses
 * the block by name. bin/standin-shape-check.php (wiring check 8) refuses
 * anything but this guard and one Standin::register() call — and a
 * `static fn` closure here, whose body it reads as file scope.
 *
 * @package blocklane_pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\blocklane_pro\Standin::register(
	array(
		'block'   => 'blocklane/form-step',
		'handle'  => 'blocklane-pro-form-step-standin',
		'file'    => __FILE__,
		'owner'   => 'block:form-step',
		// A content row owns the real block, not a module behind the
		// block-theme gate, so `classic_theme` is unreachable here and the
		// strings table may not carry it (Standin::text() refuses a dead key).
		'module'  => null,
		'wanted'  => static function (): bool {
			return \blocklane_pro\Content_Toggle::on( 'forms' );
		},
		'strings' => static function (): array {
			return array(
				/* translators: Inspector note on a saved step of a multi-step form in the block editor, when the running plugin does not include Blocklane Pro. */
				\blocklane_pro\Standin::REASON_EDITION     => __( 'This step belongs to a multi-step form built with Blocklane Pro, which is not active on this site. The fields inside stay editable; the step itself is kept exactly as saved, and the form shows on one page on the site.', 'blocklane' ),
				/* translators: Inspector note on a saved step of a multi-step form in the block editor, when Blocklane Pro is active but its Form Steps block did not register on this site. */
				\blocklane_pro\Standin::REASON_NOT_RUNNING => __( 'The Form Steps block is not registered on this site right now. The fields inside stay editable; the step itself is kept exactly as saved, and the form shows on one page on the site.', 'blocklane' ),
			);
		},
	)
);
