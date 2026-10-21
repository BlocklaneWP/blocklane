/**
 * SEO screen — Overview tab.
 *
 * Authored for the SEO package of the admin-dashboard redesign (spec
 * the C-seo spec (2026-10-12)) from the frame `64:1962`
 * (`free/seo/seotab=overview@1440`, Amendment I), its load-error and loading
 * frames (`112:61545`, `115:62604`) and D14: one full-width coverage card
 * with four meters, then a pair of equal-height cards (visibility to search
 * engines, ownership verification), then the hint for sites that use another
 * SEO tool. Each card links to where its state is managed.
 *
 * The meters are core's ProgressBar (D14), named by their own label, with the
 * count written beside them as "n of total".
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, ProgressBar, Spinner } from '@wordpress/components';

import { seo as seoApi } from '../../api/client';
import { settingsHref } from '../settings/href';
import { errorMessage } from '../../api/errors';
import { LoadError } from '../../components/LoadError';
import { VERIFICATION_SERVICES } from './shared';
import { ServiceMark } from './ServiceMark';
import { seoCopy } from '../../../../shared/seo-copy';

// The last overview this page fetched, kept across tab flips (tabs mount and
// unmount): it renders at once and refreshes in the background, because the
// counts are the screen's most expensive request.
let lastOverview = null;

/**
 * One coverage meter: its label, "n of total", and a bar at that share.
 *
 * @param {Object} props
 * @param {string} props.label What is counted.
 * @param {number} props.count How many published items have it.
 * @param {number} props.total How many published items there are.
 * @return {JSX.Element} The meter.
 */
const Meter = ( { label, count, total } ) => (
	<div className="blocklane-seo-overview__meter">
		<span className="blocklane-seo-overview__meter-label">{ label }</span>
		<span className="blocklane-seo-overview__meter-count">
			{ sprintf(
				/* translators: 1: items that have the field set, 2: published items. */
				__( '%1$d of %2$d', 'blocklane' ),
				count,
				total
			) }
		</span>
		<ProgressBar
			className="blocklane-seo-overview__meter-bar"
			value={ total > 0 ? Math.round( ( count / total ) * 100 ) : 0 }
			aria-label={ label }
		/>
	</div>
);

/**
 * A status line: a dot in the line's tone, its text, and an optional value
 * at the far end. The tone class is the last class on the item.
 *
 * @param {Object}      props
 * @param {string}      props.tone     is-on | is-off-neutral | is-off-warning.
 * @param {JSX.Element} props.children The line's text (and value).
 * @return {JSX.Element} The line.
 */
const StatusLine = ( { tone, children } ) => (
	<li className={ `blocklane-seo-overview__status ${ tone }` }>
		<span className="blocklane-seo-overview__dot" aria-hidden="true" />
		{ children }
	</li>
);

export const OverviewTab = ( { onNavigate } ) => {
	// The strings shared/seo-copy.js owns (a clean-room rewrite).
	const copy = seoCopy();
	const [ data, setData ] = useState( lastOverview );
	const [ loading, setLoading ] = useState( ! lastOverview );
	// A failed first load: { detail }, the server's message (SD-3); null otherwise.
	const [ failed, setFailed ] = useState( null );
	// Bumped by Retry to fetch again.
	const [ attempt, setAttempt ] = useState( 0 );

	useEffect( () => {
		let stale = false;
		seoApi
			.overview()
			.then( ( fresh ) => {
				if ( stale ) {
					return;
				}
				lastOverview = fresh;
				setData( fresh );
				setFailed( null );
			} )
			.catch(
				( err ) =>
					! stale && setFailed( { detail: errorMessage( err ) } )
			)
			.finally( () => ! stale && setLoading( false ) );
		return () => {
			stale = true;
		};
	}, [ attempt ] );

	if ( loading ) {
		return (
			<div className="blocklane-pro-loading" data-loading>
				<Spinner />
			</div>
		);
	}

	// A failed background refresh keeps the cached counts on screen; the
	// error takes the tab only when there is nothing to show (R3).
	if ( ! data ) {
		return failed ? (
			<LoadError
				message={ __(
					'The SEO overview could not be loaded.',
					'blocklane'
				) }
				detail={ failed.detail }
				onRetry={ () => {
					setLoading( true );
					setAttempt( ( n ) => n + 1 );
				} }
			/>
		) : null;
	}

	const { visibility, verification, counts } = data;
	const offUrl = settingsHref( 'seo' );

	return (
		<div className="blocklane-seo-overview">
			<section
				className="blocklane-seo-overview__card"
				data-card="coverage"
			>
				<h2 className="blocklane-seo-overview__title">
					{ copy.overviewContentHeading }
				</h2>
				<p className="blocklane-seo-overview__intro">
					{ sprintf(
						/* translators: %d: number of published posts and pages. */
						__(
							'Across %d published items — what has each SEO field set.',
							'blocklane'
						),
						counts.published
					) }
				</p>
				<div className="blocklane-seo-overview__meters">
					<Meter
						label={ copy.ringSchema }
						count={ counts.schema }
						total={ counts.published }
					/>
					<Meter
						label={ copy.ringTitle }
						count={ counts.title }
						total={ counts.published }
					/>
					<Meter
						label={ copy.ringDescription }
						count={ counts.description }
						total={ counts.published }
					/>
					<Meter
						label={ copy.ringIndexable }
						count={ counts.indexable }
						total={ counts.published }
					/>
				</div>
				<div>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => onNavigate( 'content' ) }
					>
						{ copy.overviewManageContent }
					</Button>
				</div>
			</section>

			<div className="blocklane-seo-overview__pair">
				<section
					className="blocklane-seo-overview__card"
					data-card="visibility"
				>
					<h2 className="blocklane-seo-overview__title">
						{ copy.siteVisibilityHeading }
					</h2>
					<ul className="blocklane-seo-overview__list">
						<StatusLine
							tone={
								visibility.indexing_allowed
									? 'is-on'
									: 'is-off-warning'
							}
						>
							{ visibility.indexing_allowed
								? __( 'Search engines allowed', 'blocklane' )
								: __(
										'Search engines discouraged',
										'blocklane'
									) }
						</StatusLine>
						<StatusLine
							tone={
								visibility.sitemap_enabled
									? 'is-on'
									: 'is-off-neutral'
							}
						>
							{ visibility.sitemap_enabled
								? __( 'Sitemap active', 'blocklane' )
								: __( 'Sitemap off', 'blocklane' ) }
						</StatusLine>
						{ visibility.site_locked ? (
							<StatusLine tone="is-off-warning">
								{ __(
									'Site locked — crawlers can’t reach it',
									'blocklane'
								) }
							</StatusLine>
						) : null }
					</ul>
					<div>
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								onNavigate( 'settings', 'visibility' )
							}
						>
							{ copy.overviewManageVisibility }
						</Button>
					</div>
				</section>

				<section
					className="blocklane-seo-overview__card"
					data-card="verification"
				>
					<h2 className="blocklane-seo-overview__title">
						{ copy.siteVerificationHeading }
					</h2>
					<ul className="blocklane-seo-overview__list">
						{ VERIFICATION_SERVICES.map( ( { key, label } ) => {
							const saved = !! verification[ key ];
							return (
								<StatusLine
									key={ key }
									tone={ saved ? 'is-on' : 'is-off-neutral' }
								>
									<ServiceMark service={ key } />
									<span className="blocklane-seo-overview__name">
										{ label }
									</span>
									<span
										className="blocklane-seo-overview__value"
										data-value={
											saved ? 'saved' : 'missing'
										}
									>
										{ saved
											? copy.verificationAdded
											: copy.verificationMissing }
									</span>
								</StatusLine>
							);
						} ) }
					</ul>
					<div>
						<Button
							variant="secondary"
							size="compact"
							onClick={ () =>
								onNavigate( 'settings', 'verification' )
							}
						>
							{ copy.overviewManageVerification }
						</Button>
					</div>
				</section>
			</div>

			<p className="blocklane-seo-overview__hint">
				<span>{ copy.disableLead }</span>
				<a href={ offUrl }>{ copy.disableLink }</a>
			</p>
		</div>
	);
};
