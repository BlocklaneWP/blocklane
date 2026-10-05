/**
 * The mega menu's saved markup — shared between Blocklane Pro's block
 * registration (inc/menu-designer/src/mega-menu/index.js) and the block
 * editor's fallback-mode stand-in, which ships in the plugin tree in both
 * editions (inc/shared/README.md rule 1: two consumers, one file here, both
 * re-pointed — never a copy). ONE definition on
 * purpose: both sides must serialize byte-identical content or each
 * would invalidate the other's saves.
 *
 * Saved markup is what WordPress prints when the block type is not
 * registered, so it doubles as the deactivation fallback. Best case: a
 * core-navigation-shaped submenu carrying every link derived from the
 * menu part (fallbackLinks, maintained by Pro's edit.js) — core nav's
 * own CSS opens it on hover/focus with no JS. Without links it degrades
 * to a plain navigation link, and without any target to null. While the
 * plugin is active, render.php replaces all of it.
 */
import { RichText } from '@wordpress/block-editor';

/**
 * Save the fallback markup.
 *
 * @param {Object} props            Save props.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element|null} Fallback markup, or null without a target.
 */
export default function saveFallbackNav( { attributes } ) {
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

	const labelContent = (
		<RichText.Content
			tagName="span"
			className="wp-block-navigation-item__label"
			value={ label }
		/>
	);

	if ( ! links.length ) {
		return (
			<li className="wp-block-navigation-item">
				<a className="wp-block-navigation-item__content" href={ url }>
					{ labelContent }
				</a>
			</li>
		);
	}

	return (
		<li className="wp-block-navigation-item wp-block-navigation-submenu has-child">
			{ url ? (
				<a className="wp-block-navigation-item__content" href={ url }>
					{ labelContent }
				</a>
			) : (
				<span className="wp-block-navigation-item__content">
					{ labelContent }
				</span>
			) }
			<span
				className="wp-block-navigation__submenu-icon"
				aria-hidden="true"
			>
				<svg
					viewBox="0 0 12 12"
					width="12"
					height="12"
					aria-hidden="true"
					focusable="false"
					fill="none"
				>
					<path
						d="M2 4.25 6 8l4-3.75"
						stroke="currentColor"
						strokeWidth="1.5"
						fill="none"
					/>
				</svg>
			</span>
			<ul className="wp-block-navigation__submenu-container">
				{ links.map( ( link, index ) => (
					<li key={ index } className="wp-block-navigation-item">
						<a
							className="wp-block-navigation-item__content"
							href={ link.url }
						>
							<span className="wp-block-navigation-item__label">
								{ link.label }
							</span>
						</a>
					</li>
				) ) }
			</ul>
		</li>
	);
}

/**
 * Deprecation entries that travel with the save — registered identically
 * by Pro and the stand-in so pre-fallback content stays valid in both.
 *
 * @param {Object} metadata The block.json metadata.
 * @return {Object[]} Deprecation entries.
 */
export function saveFallbackDeprecations( metadata ) {
	return [
		// Blocks saved before any fallback serialized as a bare
		// self-closing comment; keep them valid and migrate on save.
		// (The interim plain-link fallback needs no entry: with
		// fallbackLinks defaulting to empty, the current save regenerates
		// it byte-identically.)
		{
			attributes: metadata.attributes,
			supports: metadata.supports,
			save: () => null,
		},
	];
}
