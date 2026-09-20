/**
 * Generic info card with title + body + outbound link button. Mirrors BlocklanePro
 * Pro's InfoCard partial: used for the Documentation grid and Changelog
 * sections on the dashboard home.
 *
 * `badge` renders a small pill beside the title (e.g. "Coming soon");
 * `disabled` keeps the footer button visible but inert — for destinations
 * that aren't live yet, the button communicates intent without linking out.
 * `internal` points the button at a wp-admin screen instead of the web: same
 * tab, no external icon. Defaults off, so every existing card is unchanged.
 */

import { Button } from '@wordpress/components';
import { external } from '@wordpress/icons';

export const InfoCard = ( {
	title,
	tagline,
	link,
	buttonText,
	badge,
	disabled,
	internal,
} ) => (
	<article className="blocklane-pro-info-card">
		<div className="blocklane-pro-info-card__body">
			<h3 className="blocklane-pro-info-card__title">{ title }</h3>
			{ typeof tagline === 'string' ? (
				<p className="blocklane-pro-info-card__tagline">{ tagline }</p>
			) : (
				<div className="blocklane-pro-info-card__tagline">
					{ tagline }
				</div>
			) }
		</div>
		{ link || ( disabled && buttonText ) ? (
			<div className="blocklane-pro-info-card__footer">
				<Button
					variant="tertiary"
					href={ disabled ? undefined : link }
					target={ disabled || internal ? undefined : '_blank' }
					rel={ disabled || internal ? undefined : 'noreferrer' }
					disabled={ disabled }
					icon={ internal ? undefined : external }
					iconPosition="right"
					__next40pxDefaultSize
				>
					{ buttonText }
				</Button>
				{ badge ? (
					<span className="blocklane-pro-info-card__badge">
						{ badge }
					</span>
				) : null }
			</div>
		) : null }
	</article>
);
