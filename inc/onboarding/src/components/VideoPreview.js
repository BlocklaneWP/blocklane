/**
 * VideoPreview — the details card's video slot (design spec Amendment E;
 * the C-settings spec (2026-10-12) §0 S9, Amendment 1 SD17).
 *
 * This package draws ONE state, "Coming soon": a 16:9 neutral surface with
 * an inactive play glyph and a caption. It is not interactive and holds no
 * media element: no request leaves the page because of it. The glyph is an
 * inline `@wordpress/primitives` SVG, never an image file (ESLint
 * REMOTE_MEDIA refuses a remote one in any media element). The first real
 * video is a later package with its own security read (SD17's constraints:
 * the player mounts only inside the opened modal after a click, the poster is
 * a local asset, the source is self-hosted or youtube-nocookie).
 */
import { __ } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';

/** A play triangle in a circle, drawn for this slot (24-unit grid). */
const playGlyph = (
	<SVG
		className="blocklane-video-preview__glyph"
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
		focusable="false"
	>
		<Path d="M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19Zm0 1.5a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-2 4.25v7.5L16 12l-6-3.75Z" />
	</SVG>
);

export const VideoPreview = () => (
	<div className="blocklane-video-preview">
		{ playGlyph }
		<p className="blocklane-video-preview__caption">
			{ __( 'Video coming soon', 'blocklane' ) }
		</p>
	</div>
);

export default VideoPreview;
