/**
 * Mega Menu — the block editor's stand-in.
 *
 * Enqueued by blocklane_pro\Standin (inc/class-blocklane-pro-standin.php;
 * inc/mega-menu-standin/runtime.php is one Standin::register() call) only
 * while the real blocklane-pro/mega-menu block is NOT registered on the
 * server (the free edition; Pro with its menu-designer module vetoed or not
 * running, or on a classic theme); the getBlockType guard below is the belt
 * to that PHP guard, and it also keeps one registration during the upgrade
 * window in which a theme older than 0.11.0 still enqueues its own copy of
 * this bundle. It gives the editor a clean, intentional block instead of
 * core's "unsupported block" placeholder — while preserving every Pro
 * attribute, so Blocklane Pro, running again, restores the full mega menu
 * untouched.
 *
 * The inspector says WHY the block is read-only here, to the audience that
 * reads it: the first paragraph is the helper's localized note,
 * window.blocklaneProStandin[ 'blocklane-pro/mega-menu' ].text, read through
 * inc/shared/standin-note.js — one sentence per reason (Pro not active, a
 * classic theme, the module not running), written once in PHP, in the
 * runtime's strings closure. When the global is absent (an older loader, a
 * theme's copy of this bundle, a cached page) the paragraph is too, and the
 * rest of the panel renders.
 *
 * It also offers the one deliberate exit: a core-style convert action
 * (the "Convert to blocks" idiom) that replaces the item with a plain
 * core Submenu or Link built from the same data the fallback renders.
 * Explicit and user-initiated only, undoable until saved — never
 * automatic, because conversion discards the mega menu's panel settings
 * and makes reactivating Blocklane Pro a no-op for that item.
 *
 * Lines this design never crosses: it registers nothing on the server;
 * it never offers the block in the inserter (`inserter: false`); it never
 * converts on its own. Data preservation, never a capability (Amendment 14).
 *
 * metadata, save and deprecations come from ONE definition: save and icons
 * from inc/shared/mega-menu/ (Pro's registration imports the same files),
 * metadata from inc/shared/mega-menu/block-metadata.json — the generator's
 * committed copy of Pro's block.json, so this bundle serializes
 * byte-identical content to Pro's own save and the two registrations can
 * never invalidate each other, in the free stage too, where Pro's src is
 * gone. Built by `npm run build:mega-menu-standin` into build/ (untracked;
 * both editions' zips carry it).
 */
import {
	registerBlockType,
	getBlockType,
	createBlock,
} from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

import metadata from '../../shared/mega-menu/block-metadata.json';
import saveFallbackNav, {
	saveFallbackDeprecations,
} from '../../shared/mega-menu/save-fallback';
import { BlockIcon, ToggleIcon } from '../../shared/mega-menu/icons';
import { standinNote } from '../../shared/standin-note';

/**
 * Build the core block this item converts to — the same tiers the saved
 * fallback renders: a Submenu of Links when links were derived, a plain
 * Link when only a URL exists, nothing otherwise.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object|null} A core/navigation-submenu or core/navigation-link
 *                       block, or null when there is nothing to convert to.
 */
function toCoreNavigation( attributes ) {
	const label = ( attributes.label || '' ).trim();
	const url = attributes.url || attributes.collapsedUrl || '';
	const links = (
		Array.isArray( attributes.fallbackLinks )
			? attributes.fallbackLinks
			: []
	).filter( ( link ) => link && link.url && link.label );

	if ( ! label || ( ! url && ! links.length ) ) {
		return null;
	}

	const entity = attributes.urlEntity || {};
	const parentAttributes = {
		label,
		...( url ? { url } : {} ),
		...( attributes.description
			? { description: attributes.description }
			: {} ),
		...( attributes.title ? { title: attributes.title } : {} ),
		...( entity.id && entity.kind && entity.type
			? { id: entity.id, kind: entity.kind, type: entity.type }
			: {} ),
	};

	if ( ! links.length ) {
		return createBlock( 'core/navigation-link', parentAttributes );
	}

	return createBlock(
		'core/navigation-submenu',
		parentAttributes,
		links.map( ( link ) =>
			createBlock( 'core/navigation-link', {
				label: link.label,
				url: link.url,
				kind: 'custom',
			} )
		)
	);
}

/**
 * Read-only canvas preview: the item as its fallback renders it — label,
 * chevron — with the state explained in the inspector, plus the explicit
 * convert-to-core action. No other editing surfaces: the panel, behavior
 * and layout all belong to Blocklane Pro.
 *
 * @param {Object} props            Edit props.
 * @param {Object} props.attributes Block attributes.
 * @param {string} props.clientId   Block client id.
 * @return {JSX.Element} Canvas preview.
 */
function EditFallback( { attributes, clientId } ) {
	const blockProps = useBlockProps( {
		className: 'wp-block-navigation-item',
	} );
	const { replaceBlocks } = useDispatch( blockEditorStore );
	const label =
		( attributes.label || '' ).trim() || __( 'Mega Menu', 'blocklane' );
	const linkCount = Array.isArray( attributes.fallbackLinks )
		? attributes.fallbackLinks.filter(
				( link ) => link && link.url && link.label
			).length
		: 0;
	const converted = toCoreNavigation( attributes );
	const note = standinNote( window.blocklaneProStandin, metadata.name );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Mega Menu (read-only)', 'blocklane' ) }>
					{ note && <p>{ note.text }</p> }
					<p>
						{ linkCount
							? sprintf(
									/* translators: %d: number of links. */
									__(
										'Its dropdown carries %d links derived from the menu template part.',
										'blocklane'
									),
									linkCount
								)
							: __(
									'It renders as a single link.',
									'blocklane'
								) }
					</p>
					{ converted && (
						<>
							<Button
								variant="secondary"
								onClick={ () =>
									replaceBlocks( clientId, [ converted ] )
								}
							>
								{ linkCount
									? __( 'Convert to Submenu', 'blocklane' )
									: __( 'Convert to Link', 'blocklane' ) }
							</Button>
							<p>
								{ __(
									'Converting makes this a regular WordPress navigation item with the same label and links. The mega menu panel and its settings are discarded — undo restores them until you save.',
									'blocklane'
								) }
							</p>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			<li { ...blockProps }>
				<span className="wp-block-navigation-item__content">
					<span className="wp-block-navigation-item__label">
						{ label }
					</span>
					<span
						style={ {
							display: 'inline-block',
							height: '0.6em',
							lineHeight: 0,
							marginLeft: '0.25em',
							width: '0.8em',
							verticalAlign: 'middle',
						} }
						aria-hidden="true"
					>
						<ToggleIcon />
					</span>
				</span>
			</li>
		</>
	);
}

if ( ! getBlockType( metadata.name ) ) {
	registerBlockType(
		{
			...metadata,
			// Existing mega menus render gracefully; new ones need Pro.
			supports: { ...metadata.supports, inserter: false },
		},
		{
			icon: BlockIcon,
			edit: EditFallback,
			save: saveFallbackNav,
			deprecated: saveFallbackDeprecations( metadata ),
		}
	);
}
