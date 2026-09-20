/**
 * Password Form block — editor side only.
 *
 * A self-contained, server-rendered unlock form modelled on wp-login.php's
 * structure (label, password field with a show/hide eye, submit button) but
 * styled by the theme so it drops into any design. This file is the editor
 * preview + block registration; the front end is rendered in PHP (the Site Lock
 * gate's render_password_form()).
 *
 * The submit reuses the theme button (wp-element-button) by default; the Button
 * panel layers optional per-instance background / text color / border radius on
 * top, and the label is edited inline (RichText). All are honoured on the front
 * end via the buttonText / buttonBackground / buttonTextColor / buttonBorderRadius
 * attributes. Container styling comes from the block supports in block.json.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	RichText,
} from '@wordpress/block-editor';
import {
	Icon,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { seen as seenIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './editor.css';

const RADIUS_UNITS = [
	{ value: 'px', label: 'px' },
	{ value: 'em', label: 'em' },
	{ value: 'rem', label: 'rem' },
	{ value: '%', label: '%' },
];

const Edit = ( { attributes, setAttributes } ) => {
	const {
		buttonText,
		buttonBackground,
		buttonTextColor,
		buttonBorderRadius,
	} = attributes;
	const blockProps = useBlockProps( {
		className: 'blocklane-pro-site-lock__form',
	} );

	const buttonStyle = {};
	if ( buttonBackground ) {
		buttonStyle.backgroundColor = buttonBackground;
	}
	if ( buttonTextColor ) {
		buttonStyle.color = buttonTextColor;
	}
	if ( buttonBorderRadius ) {
		buttonStyle.borderRadius = buttonBorderRadius;
	}

	return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title={ __( 'Button', 'blocklane' ) }
					enableAlpha
					colorSettings={ [
						{
							value: buttonBackground,
							onChange: ( value ) =>
								setAttributes( {
									buttonBackground: value,
								} ),
							label: __( 'Background', 'blocklane' ),
						},
						{
							value: buttonTextColor,
							onChange: ( value ) =>
								setAttributes( { buttonTextColor: value } ),
							label: __( 'Text', 'blocklane' ),
						},
					] }
				>
					<UnitControl
						__next40pxDefaultSize
						label={ __( 'Border radius', 'blocklane' ) }
						value={ buttonBorderRadius || '' }
						units={ RADIUS_UNITS }
						onChange={ ( value ) =>
							setAttributes( {
								buttonBorderRadius: value || '',
							} )
						}
					/>
				</PanelColorSettings>
			</InspectorControls>

			<div { ...blockProps }>
				<span className="blocklane-pro-site-lock__label">
					{ __( 'Password', 'blocklane' ) }
				</span>
				<span className="blocklane-pro-site-lock__inputwrap">
					<input
						className="blocklane-pro-site-lock__input"
						type="password"
						placeholder={ __( 'Enter password', 'blocklane' ) }
						disabled
					/>
					<span
						className="blocklane-pro-site-lock__reveal"
						aria-hidden="true"
					>
						<Icon icon={ seenIcon } />
					</span>
				</span>
				<RichText
					tagName="span"
					className="blocklane-pro-site-lock__submit wp-block-button__link wp-element-button"
					style={ buttonStyle }
					value={ buttonText }
					onChange={ ( value ) =>
						setAttributes( { buttonText: value } )
					}
					aria-label={ __( 'Button label', 'blocklane' ) }
					placeholder={ __( 'Log in', 'blocklane' ) }
					allowedFormats={ [] }
					disableLineBreaks
					withoutInteractiveFormatting
				/>
			</div>
		</>
	);
};

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
