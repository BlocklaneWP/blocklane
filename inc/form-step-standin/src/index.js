/**
 * Form Step — the block editor's stand-in.
 *
 * Enqueued by blocklane_pro\Standin (inc/class-blocklane-pro-standin.php;
 * inc/form-step-standin/runtime.php is one Standin::register() call) only
 * while the real blocklane/form-step block is NOT registered on the server
 * (the free edition, where block:form-step is a Pro contributing unit) and
 * the forms suite is on; the getBlockType guard below is the belt to that
 * PHP guard. It registers the step as a TRANSPARENT CONTAINER: the fields
 * inside stay ordinary, editable blocks instead of the raw HTML core's
 * missing-block placeholder would make of them, and nothing here offers a
 * step's behavior — no label, no Steps panel, no lock pass, no inserter
 * entry (`inserter: false`). The front end renders the same form flat.
 *
 * The inspector carries ONE read-only note (a note is not an editing
 * surface; without it, selecting a step showed an empty inspector on a block
 * named "Form Step"): the helper's localized sentence,
 * window.blocklaneProStandin[ 'blocklane/form-step' ].text, read through
 * inc/shared/standin-note.js and written once in PHP. When the global is
 * absent the paragraph is too.
 *
 * `save` is byte-identical to Pro's (inc/forms/src/form-step/index.js:16),
 * so a save from this editor re-serializes a Pro-authored stepped form
 * unchanged and the two registrations can never invalidate each other.
 * Metadata comes from ONE definition: inc/shared/forms/form-step-metadata.json
 * is the generator's committed copy of Pro's block.json (edition-manifest
 * `copies`), rendered so the free stage, where Pro's src is gone, still
 * builds this bundle from Pro's own attributes and supports. Built by
 * `npm run build:form-step-standin` into build/ (untracked; both editions'
 * zips carry it). Data preservation, never a capability (spec 2026-09-24 D4).
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';

import metadata from '../../shared/forms/form-step-metadata.json';
import { standinNote } from '../../shared/standin-note';

/**
 * The transparent container: the step's block props on a plain div, the
 * fields as its inner blocks. No step class — the front end under this
 * edition has no step styles either, so the canvas stays 1:1 with it. The
 * inspector holds the read-only note and nothing else.
 *
 * @return {JSX.Element} The container with its inner blocks.
 */
function EditStandin() {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps );
	const note = standinNote( window.blocklaneProStandin, metadata.name );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form Step (read-only)', 'blocklane' ) }>
					{ note && <p>{ note.text }</p> }
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

if ( ! getBlockType( metadata.name ) ) {
	registerBlockType(
		{
			...metadata,
			// Existing steps stay whole; new ones need Pro.
			supports: { ...metadata.supports, inserter: false },
		},
		{
			edit: EditStandin,
			save: () => <InnerBlocks.Content />,
		}
	);
}
