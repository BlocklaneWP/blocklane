/**
 * Form notification — edit. The one deliberate canvas-vs-front deviation in
 * the suite: the front hides notifications until the runtime reveals them,
 * but the canvas must show them for editing — so they render with a dashed
 * outline and a type chip (editor.scss).
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const TEMPLATE_OF = {
	success: [
		[
			'core/paragraph',
			{
				content: __(
					'Thanks! Your message has been sent.',
					'blocklane'
				),
			},
		],
	],
	error: [
		[
			'core/paragraph',
			{
				content: __(
					'Something went wrong and your message was not sent. Please check the highlighted fields and try again.',
					'blocklane'
				),
			},
		],
	],
};

export default function FormNotificationEdit( { attributes } ) {
	const { type } = attributes;

	const blockProps = useBlockProps( {
		className:
			'blocklane-form__notification blocklane-form__notification-editor is-' +
			type,
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{},
		{ template: TEMPLATE_OF[ type ] || TEMPLATE_OF.success }
	);

	return (
		<div { ...blockProps }>
			<span className="blocklane-form__notification-chip">
				{ 'error' === type
					? __( 'Error message', 'blocklane' )
					: __( 'Success message', 'blocklane' ) }
			</span>
			<div { ...innerBlocksProps } />
		</div>
	);
}
