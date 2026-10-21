/**
 * Submit button — edit. The canvas renders the button's text in a <div> with
 * the theme's button classes (wp-element-button), so it looks like
 * render.php's button and takes every keystroke a button would swallow.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import ContentTextPanel, { ContentTextItem } from '../shared/content-panel';

export default function FormSubmitButtonEdit( { attributes, setAttributes } ) {
	const { text, busyText } = attributes;

	// The Turnstile placeholder moved to the form wrapper (form/edit.js),
	// mirroring the front-end move: the widget is the form's last child,
	// never a sibling of the button.
	const blockProps = useBlockProps( {
		className:
			'blocklane-form__submit wp-block-button__link wp-element-button',
	} );

	return (
		<>
			<ContentTextPanel
				resetAll={ () => setAttributes( { busyText: '' } ) }
			>
				<ContentTextItem
					label={ __( 'Sending label', 'blocklane' ) }
					help={ __(
						'Shown while the form submits. Empty uses “Sending…”.',
						'blocklane'
					) }
					value={ busyText }
					onChange={ ( value ) =>
						setAttributes( { busyText: value } )
					}
				/>
			</ContentTextPanel>
			{ /* A <div>, not a <button>: inside a button the space key
			     activates the button instead of typing, so "Count me in"
			     saved as "Countmein". The theme's button classes style it
			     the same; the front end (render.php) renders the button. */ }
			<RichText
				{ ...blockProps }
				tagName="div"
				value={ text }
				onChange={ ( value ) => setAttributes( { text: value } ) }
				placeholder={ __( 'Submit', 'blocklane' ) }
				allowedFormats={ [] }
			/>
		</>
	);
}
