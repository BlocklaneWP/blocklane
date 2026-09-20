/**
 * SEO screen — Overview tab.
 *
 * Status cards mirroring the state the other two tabs manage: site
 * visibility, verification, and per-post content coverage (factual counts,
 * never a score). Each card links to where the state is managed.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';

import { seo as seoApi } from '../../api/client';
import { buildRouteUrl } from '../../router';
import { errorMessage, StatusRow, VERIFICATION_SERVICES } from './shared';

// The last fetched overview, kept across tab flips (tabs mount/unmount):
// render it immediately, refresh in the background — the counts endpoint is
// the screen's most expensive request, and bouncing between tabs shouldn't
// re-pay it with a full-tab spinner each time.
let lastOverview = null;

// A coverage ring: SVG donut showing count/total, with the numbers beneath.
const Ring = ( { count, total, label } ) => {
	const r = 26;
	const c = 2 * Math.PI * r;
	const pct = total > 0 ? count / total : 0;

	return (
		<div className="blocklane-pro-seo-screen__ring">
			<svg viewBox="0 0 64 64" width="64" height="64" aria-hidden="true">
				<circle
					cx="32"
					cy="32"
					r={ r }
					fill="none"
					stroke="#e0e0e0"
					strokeWidth="6"
				/>
				<circle
					cx="32"
					cy="32"
					r={ r }
					fill="none"
					stroke={ pct >= 1 ? '#00a32a' : '#3858e9' }
					strokeWidth="6"
					strokeLinecap="round"
					strokeDasharray={ `${ c * pct } ${ c }` }
					transform="rotate(-90 32 32)"
				/>
			</svg>
			<div className="blocklane-pro-seo-screen__ring-count">
				{ count } / { total }
			</div>
			<div className="blocklane-pro-seo-screen__ring-label">
				{ label }
			</div>
		</div>
	);
};

export const OverviewTab = ( { onNavigate } ) => {
	const [ loading, setLoading ] = useState( ! lastOverview );
	const [ error, setError ] = useState( '' );
	const [ data, setData ] = useState( lastOverview );

	useEffect( () => {
		seoApi
			.overview()
			.then( ( fresh ) => {
				lastOverview = fresh;
				setData( fresh );
			} )
			.catch( ( err ) => setError( errorMessage( err ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	if ( loading ) {
		return (
			<div className="blocklane-pro-loading">
				<Spinner />
			</div>
		);
	}

	// A failed background refresh keeps showing the cached counts; the
	// error only takes the screen when there is nothing to show at all.
	if ( ! data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error || __( 'Could not load the overview.', 'blocklane' ) }
			</Notice>
		);
	}

	const { visibility, verification, coverage } = data;
	const advancedUrl = buildRouteUrl( { screen: 'advanced', adv: 'seo' } );

	return (
		<div className="blocklane-pro-seo-screen__overview">
			<div className="blocklane-pro-seo-screen__cards">
				{ /* Content SEO leads on its own full-width row (is-wide);
				     the other cards pair up in two columns below. */ }
				<div className="blocklane-pro-seo-screen__overview-card is-wide">
					<h2>{ __( 'Content SEO', 'blocklane' ) }</h2>
					<p className="blocklane-pro-seo-screen__section-intro">
						{ sprintf(
							/* translators: %d: number of published posts and pages. */
							__(
								'Across %d published items — what has each SEO field set.',
								'blocklane'
							),
							coverage.total
						) }
					</p>
					<div className="blocklane-pro-seo-screen__rings">
						<Ring
							count={ coverage.with_schema }
							total={ coverage.total }
							label={ __( 'Schema applied', 'blocklane' ) }
						/>
						<Ring
							count={ coverage.with_title }
							total={ coverage.total }
							label={ __( 'SEO title set', 'blocklane' ) }
						/>
						<Ring
							count={ coverage.with_description }
							total={ coverage.total }
							label={ __(
								'Meta description added',
								'blocklane'
							) }
						/>
						<Ring
							count={ coverage.with_search_visible }
							total={ coverage.total }
							label={ __(
								'Visible to search engines',
								'blocklane'
							) }
						/>
					</div>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () => onNavigate( 'content' ) }
					>
						{ __( 'Manage content', 'blocklane' ) }
					</Button>
				</div>

				<div className="blocklane-pro-seo-screen__overview-card">
					<h2>{ __( 'Site visibility', 'blocklane' ) }</h2>
					<ul>
						<StatusRow
							on={ visibility.search_engines_visible }
							offTone="warning"
						>
							{ visibility.search_engines_visible
								? __( 'Search engines allowed', 'blocklane' )
								: __(
										'Search engines discouraged',
										'blocklane'
									) }
						</StatusRow>
						<StatusRow on={ visibility.sitemap_active }>
							{ visibility.sitemap_active
								? __( 'Sitemap active', 'blocklane' )
								: __( 'Sitemap off', 'blocklane' ) }
						</StatusRow>
						{ visibility.site_locked && (
							<StatusRow on={ false } offTone="warning">
								{ __(
									'Site locked — crawlers can’t reach it',
									'blocklane'
								) }
							</StatusRow>
						) }
					</ul>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () => onNavigate( 'settings', 'visibility' ) }
					>
						{ __( 'Manage visibility', 'blocklane' ) }
					</Button>
				</div>

				<div className="blocklane-pro-seo-screen__overview-card">
					<h2>{ __( 'Site verification', 'blocklane' ) }</h2>
					<ul>
						{ VERIFICATION_SERVICES.map( ( { key, label } ) => (
							<StatusRow
								key={ key }
								on={ !! verification[ key ] }
							>
								{ label }
								<span className="blocklane-pro-seo-screen__status-value">
									{ verification[ key ]
										? __( 'Set', 'blocklane' )
										: __( 'Not set', 'blocklane' ) }
								</span>
							</StatusRow>
						) ) }
					</ul>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () =>
							onNavigate( 'settings', 'verification' )
						}
					>
						{ __( 'Manage verification', 'blocklane' ) }
					</Button>
				</div>
			</div>

			<p className="blocklane-pro-seo-screen__disable-hint">
				{ __( 'Using a different SEO solution?', 'blocklane' ) }{ ' ' }
				<a href={ advancedUrl }>
					{ __( 'Disable Blocklane SEO', 'blocklane' ) }
				</a>
			</p>
		</div>
	);
};
