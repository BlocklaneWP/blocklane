/**
 * SEO screen — the Edit SEO sidebar.
 *
 * Built from the same core primitives as the page editor's settings sidebar
 * — Panel / PanelHeader / PanelBody / VStack — so the header row, borders,
 * padding, and typography all come from `@wordpress/components`' own styles,
 * not custom CSS. Edits the same four meta fields as the editor's SEO
 * sidebar, with the same live search preview.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { closeSmall, chevronLeft, chevronRight } from '@wordpress/icons';
import {
	Button,
	Flex,
	Notice,
	Panel,
	PanelBody,
	PanelHeader,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

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

export const EditSeoPanel = ( { item, onClose, onSaved, stepper, site } ) => {
	const [ seoTitle, setSeoTitle ] = useState( item.seo_title );
	const [ seoDescription, setSeoDescription ] = useState(
		item.seo_description
	);
	const [ schemaType, setSchemaType ] = useState( item.schema_type );
	const [ noindex, setNoindex ] = useState( item.noindex );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	// Move to a neighboring row (the table remounts this panel per row).
	// Same as clicking another row — unsaved edits are dropped.
	const step = ( delta ) => !! stepper?.step( item.id, delta );

	// Save, then either close (Save) or advance to the next row (Save &
	// next) — closing when there is no next row to land on.
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
		<Panel>
			<PanelHeader label={ __( 'Edit SEO', 'blocklane' ) }>
				{ /* The media modal's Attachment-details nav: prev/next/close
				     as full-height cells separated by hairlines, flush to the
				     header's right edge. */ }
				<span className="blocklane-pro-seo-screen__panel-nav">
					{ stepper && (
						<>
							<Button
								icon={ chevronLeft }
								label={ __( 'Previous item', 'blocklane' ) }
								disabled={ ! stepper.hasPrev }
								accessibleWhenDisabled
								onClick={ () => step( -1 ) }
							/>
							<Button
								icon={ chevronRight }
								label={ __( 'Next item', 'blocklane' ) }
								disabled={ ! stepper.hasNext }
								accessibleWhenDisabled
								onClick={ () => step( 1 ) }
							/>
						</>
					) }
					<Button
						icon={ closeSmall }
						label={ __( 'Close', 'blocklane' ) }
						onClick={ onClose }
					/>
				</span>
			</PanelHeader>
			<PanelBody>
				<VStack spacing={ 4 }>
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
								__( 'Search title', 'blocklane' ),
								__(
									'Replaces the title in search results and the browser tab. Leave blank to use the page title.',
									'blocklane'
								)
							) }
							value={ seoTitle }
							onChange={ setSeoTitle }
						/>
						<CharCount value={ seoTitle } limit={ TITLE_LIMIT } />
					</div>

					<div>
						<TextareaControl
							__nextHasNoMarginBottom
							rows={ 3 }
							label={ labelWithTip(
								__( 'Search description', 'blocklane' ),
								__(
									'The summary search engines may show under the title. Leave blank to use the excerpt or page content.',
									'blocklane'
								)
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
							__( 'Schema type', 'blocklane' ),
							__(
								'Structured data that helps search engines understand the page. For FAQ, questions and answers are read from the Accordion and Details blocks on the page.',
								'blocklane'
							)
						) }
						value={ schemaType }
						options={ SCHEMA_OPTIONS }
						onChange={ setSchemaType }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ labelWithTip(
							__( 'Hide from search engines', 'blocklane' ),
							__(
								'Adds a noindex directive and leaves this content out of the sitemap.',
								'blocklane'
							)
						) }
						checked={ noindex }
						onChange={ setNoindex }
					/>
				</VStack>
			</PanelBody>

			{ /* Pinned action bar — mirrors the DataViews footer beside it
			     (12px vertical padding, #f0f0f0 border-top, 32px compact
			     controls) so the two footers run flush across the seam. */ }
			<div className="blocklane-pro-seo-screen__editor-footer">
				<Flex justify="flex-end" gap={ 2 }>
					<Button
						size="compact"
						variant="tertiary"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'blocklane' ) }
					</Button>
					{ stepper?.hasNext && (
						<Button
							size="compact"
							variant="secondary"
							isBusy={ isBusy }
							disabled={ isBusy }
							onClick={ () => save( true ) }
						>
							{ __( 'Save & next', 'blocklane' ) }
						</Button>
					) }
					<Button
						size="compact"
						variant="primary"
						isBusy={ isBusy }
						disabled={ isBusy }
						onClick={ () => save() }
					>
						{ __( 'Save', 'blocklane' ) }
					</Button>
				</Flex>
			</div>
		</Panel>
	);
};
