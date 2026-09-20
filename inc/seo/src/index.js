/**
 * Blocklane SEO — the editor's SEO sidebar.
 *
 * A dedicated full-height sidebar (pinned icon in the editor header, like the
 * settings cog) editing the four SEO meta fields — search title, description,
 * schema type, noindex — with a live preview of the search result. A
 * PluginSidebar rather than a document panel: the Page|Block tab bar is not
 * extensible, and this is the supported way to give SEO its own top-level
 * surface. Values are plain post meta (registered REST-exposed by
 * inc/seo/runtime.php) read and written as entity props, so Undo, autosave,
 * and revisions all behave like any other document field.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import { decodeEntities } from '@wordpress/html-entities';
import {
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

// The shared SEO primitives (inc/shared — build-input only), bound to this
// bundle's class namespace. The dashboard renders the same components under
// its own prefix, so the two surfaces can't drift apart.
import {
	makeSeoUi,
	seoIcon,
	SCHEMA_OPTIONS,
	TITLE_LIMIT,
	DESCRIPTION_LIMIT,
} from '../../shared/seo-ui';

import './editor.css';

const { labelWithTip, CharCount, SearchPreview } =
	makeSeoUi( 'blocklane-pro-seo' );

const META_TITLE = 'blocklane_seo_title';
const META_DESCRIPTION = 'blocklane_seo_description';
const META_NOINDEX = 'blocklane_seo_noindex';
const META_SCHEMA_TYPE = 'blocklane_seo_schema_type';

const SeoPanel = () => {
	const {
		postId,
		postType,
		postTitle,
		excerpt,
		permalink,
		siteTitle,
		siteIcon,
		viewable,
	} = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const core = select( 'core' );
		const type = editor?.getCurrentPostType?.();
		const typeObject = type ? core?.getPostType?.( type ) : null;

		return {
			postId: editor?.getCurrentPostId?.() || 0,
			postType: type,
			postTitle: editor?.getEditedPostAttribute?.( 'title' ) || '',
			excerpt: editor?.getEditedPostAttribute?.( 'excerpt' ) || '',
			permalink: editor?.getPermalink?.() || '',
			siteTitle: core?.getEntityRecord?.( 'root', 'site' )?.title || '',
			// The unstable base (REST index) carries the site icon URL —
			// the same source core's own site-icon components read.
			siteIcon:
				core?.getEntityRecord?.( 'root', '__unstableBase' )
					?.site_icon_url || '',
			viewable: !! typeObject?.viewable,
		};
	}, [] );

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// The designated HTML-sitemap page renders content the editor can't
	// show — surface it the way core flags the posts page.
	const { createWarningNotice } = useDispatch( noticesStore );
	useEffect( () => {
		const sitemapPage = window.blocklaneProSeo?.htmlSitemapPage || 0;
		if ( sitemapPage && postId === sitemapPage ) {
			createWarningNotice(
				__(
					'You are currently editing the page that shows your site map.',
					'blocklane'
				),
				{ id: 'blocklane-pro-seo-sitemap-page', isDismissible: false }
			);
		}
	}, [ postId, createWarningNotice ] );

	// Only public, front-rendered types have a search result to optimize.
	if ( ! postType || ! viewable || ! meta ) {
		return null;
	}

	const seoTitle = meta[ META_TITLE ] ?? '';
	const seoDescription = meta[ META_DESCRIPTION ] ?? '';
	const noindex = !! meta[ META_NOINDEX ];
	const schemaType = meta[ META_SCHEMA_TYPE ] ?? '';

	const update = ( key ) => ( value ) =>
		setMeta( { ...meta, [ key ]: value } );

	// Mirrors the front end: a custom title replaces the whole title tag;
	// otherwise core renders "Title – Site name".
	const previewTitle = seoTitle.trim()
		? seoTitle.trim()
		: decodeEntities(
				[ postTitle, siteTitle ].filter( Boolean ).join( ' – ' )
			);
	const previewDescription = ( seoDescription.trim() || excerpt ).slice(
		0,
		DESCRIPTION_LIMIT + 40
	);

	return (
		<>
			<PluginSidebarMoreMenuItem target="blocklane-pro-seo">
				{ __( 'SEO', 'blocklane' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="blocklane-pro-seo"
				title={ __( 'SEO', 'blocklane' ) }
				className="blocklane-pro-seo"
			>
				{ /* Core primitives own the section box and the rhythm:
				     PanelBody supplies the sidebar padding, VStack the 16px
				     vertical beat between sections — same arrangement as the
				     dashboard's Edit SEO drawer. */ }
				<PanelBody className="blocklane-pro-seo__sidebar">
					<VStack spacing={ 4 }>
						<SearchPreview
							title={ previewTitle }
							description={ previewDescription }
							permalink={ permalink }
							noindex={ noindex }
							siteTitle={ siteTitle }
							siteIcon={ siteIcon }
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
								onChange={ update( META_TITLE ) }
							/>
							<CharCount
								value={ seoTitle }
								limit={ TITLE_LIMIT }
							/>
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
								onChange={ update( META_DESCRIPTION ) }
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
							onChange={ update( META_SCHEMA_TYPE ) }
						/>

						<div>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ labelWithTip(
									__(
										'Hide from search engines',
										'blocklane'
									),
									__(
										'Adds a noindex directive and leaves this content out of the sitemap.',
										'blocklane'
									)
								) }
								checked={ noindex }
								onChange={ update( META_NOINDEX ) }
							/>
							{ noindex && (
								<Notice
									status="warning"
									isDismissible={ false }
									className="blocklane-pro-seo__noindex-notice"
								>
									{ __(
										'This page asks search engines not to index it and is left out of the sitemap.',
										'blocklane'
									) }
								</Notice>
							) }
						</div>
					</VStack>
				</PanelBody>
			</PluginSidebar>
		</>
	);
};

registerPlugin( 'blocklane-pro-seo', { render: SeoPanel, icon: seoIcon } );
