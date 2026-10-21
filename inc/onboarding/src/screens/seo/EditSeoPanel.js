/**
 * SEO screen — the Edit SEO drawer.
 *
 * Authored for the SEO package (the C-seo spec (2026-10-12)) from
 * `BL/SeoEditPanel` (`62:930`) in the frame `62:1094`
 * (`free/seo/seotab=content/editing@1440`), Amendments H and L: the details
 * card's anatomy. A header names the post being edited and its content type,
 * then previous / next / close; the body holds the search preview and the
 * four fields the editor's SEO sidebar edits (the same post meta); a footer
 * pinned to the card's foot holds Cancel · Save & next · Save.
 *
 * The help copy stays behind each label's tip (Amendment H, D-f), though the
 * frame does not draw the glyph.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { closeSmall, chevronLeft, chevronRight } from '@wordpress/icons';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { Badge } from '@wordpress/ui';

import { seo as seoApi } from '../../api/client';
import {
	errorMessage,
	labelWithTip,
	CharCount,
	SearchPreview,
	SCHEMA_OPTIONS,
	TITLE_LIMIT,
	DESCRIPTION_LIMIT,
} from './shared';
import { seoCopy } from '../../../../shared/seo-copy';

export const EditSeoPanel = ( { item, onClose, onSaved, stepper, site } ) => {
	// The strings shared/seo-copy.js owns (a clean-room rewrite).
	const copy = seoCopy();
	const [ seoTitle, setSeoTitle ] = useState( item.seo_title );
	const [ seoDescription, setSeoDescription ] = useState(
		item.seo_description
	);
	const [ schemaType, setSchemaType ] = useState( item.schema_type );
	const [ noindex, setNoindex ] = useState( item.noindex );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	// Move to a neighboring row (the screen remounts this drawer per row).
	// Unsaved edits are dropped, as when another row is clicked.
	const step = ( delta ) => !! stepper?.step( item.id, delta );

	// Save, then close (Save) or move to the next row (Save & next), closing
	// when there is no next row to land on.
	const save = ( thenNext = false ) => {
		setIsBusy( true );
		setError( '' );
		seoApi
			.saveContentRow( {
				id: item.id,
				seo_title: seoTitle,
				seo_description: seoDescription,
				schema_type: schemaType,
				noindex,
			} )
			.then( ( row ) => {
				onSaved( row );
				if ( ! thenNext || ! step( 1 ) ) {
					onClose();
				}
			} )
			.catch( ( err ) => {
				setError( errorMessage( err ) );
				setIsBusy( false );
			} );
	};

	const previewTitle = seoTitle.trim() || item.title;
	const previewDescription = ( seoDescription.trim() || item.excerpt ).slice(
		0,
		DESCRIPTION_LIMIT + 40
	);

	return (
		<div className="blocklane-seo-editor__panel">
			<div
				className="blocklane-seo-editor__header"
				data-edit-part="header"
			>
				<div className="blocklane-seo-editor__heading">
					<h2
						className="blocklane-seo-editor__title"
						data-edit-part="title"
					>
						{ item.title }
					</h2>
					{ item.type_label ? (
						<Badge>{ item.type_label }</Badge>
					) : null }
				</div>
				<div className="blocklane-seo-editor__nav">
					{ stepper ? (
						<>
							<Button
								size="compact"
								icon={ chevronLeft }
								label={ __( 'Previous item', 'blocklane' ) }
								data-edit-part="prev"
								disabled={ ! stepper.hasPrev }
								accessibleWhenDisabled
								onClick={ () => step( -1 ) }
							/>
							<Button
								size="compact"
								icon={ chevronRight }
								label={ __( 'Next item', 'blocklane' ) }
								data-edit-part="next"
								disabled={ ! stepper.hasNext }
								accessibleWhenDisabled
								onClick={ () => step( 1 ) }
							/>
						</>
					) : null }
					<Button
						size="compact"
						icon={ closeSmall }
						label={ __( 'Close', 'blocklane' ) }
						data-edit-part="close"
						onClick={ onClose }
					/>
				</div>
			</div>

			<div className="blocklane-seo-editor__body" data-edit-part="body">
				{ error ? (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) : null }

				<SearchPreview
					title={ previewTitle }
					description={ previewDescription }
					permalink={ item.url }
					noindex={ noindex }
					siteTitle={ site?.title }
					siteIcon={ site?.icon }
				/>

				<div>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ labelWithTip(
							copy.fieldTitleLabel,
							copy.fieldTitleHelp
						) }
						value={ seoTitle }
						onChange={ setSeoTitle }
					/>
					<CharCount value={ seoTitle } limit={ TITLE_LIMIT } />
				</div>

				<div>
					<TextareaControl
						__nextHasNoMarginBottom
						rows={ 5 }
						label={ labelWithTip(
							copy.fieldDescriptionLabel,
							copy.fieldDescriptionHelp
						) }
						value={ seoDescription }
						onChange={ setSeoDescription }
					/>
					<CharCount
						value={ seoDescription }
						limit={ DESCRIPTION_LIMIT }
					/>
				</div>

				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ labelWithTip(
						copy.fieldSchemaLabel,
						copy.fieldSchemaHelp
					) }
					value={ schemaType }
					options={ SCHEMA_OPTIONS }
					onChange={ setSchemaType }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					label={ labelWithTip(
						copy.fieldNoindexLabel,
						copy.fieldNoindexHelp
					) }
					checked={ noindex }
					onChange={ setNoindex }
				/>
			</div>

			<div
				className="blocklane-seo-editor__footer"
				data-edit-part="footer"
			>
				<Button size="compact" variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'blocklane' ) }
				</Button>
				{ stepper?.hasNext ? (
					<Button
						size="compact"
						variant="secondary"
						isBusy={ isBusy }
						disabled={ isBusy }
						onClick={ () => save( true ) }
					>
						{ __( 'Save & next', 'blocklane' ) }
					</Button>
				) : null }
				<Button
					size="compact"
					variant="primary"
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ () => save() }
				>
					{ __( 'Save', 'blocklane' ) }
				</Button>
			</div>
		</div>
	);
};
