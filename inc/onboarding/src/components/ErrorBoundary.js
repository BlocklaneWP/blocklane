/**
 * Error boundary for the dashboard.
 *
 * A screen that throws during render should not white-screen the whole app —
 * it should show a recoverable fallback while the sidebar (and every other
 * screen) keeps working. Error boundaries have no hook equivalent, so this is a
 * class component by necessity.
 *
 * The fallback doubles as a self-service diagnostic: it shows the error and a
 * one-click copyable report (screen, message, JS stack, React component stack,
 * plugin/WP/PHP versions, URL, timestamp, browser) so an admin can share it with
 * support — or paste it into an AI assistant — to triage fast.
 *
 * Used in two places: around each screen (keyed by the active slug, so
 * navigating to another screen remounts it and clears the error), and once at
 * the top of the app as a last resort for shell-level failures.
 */

import { Component, createRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { pluginName } from '../edition';

export class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null, report: '', copied: false };
		this.reportRef = createRef();
		this.handleReset = this.handleReset.bind( this );
		this.handleCopy = this.handleCopy.bind( this );
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, errorInfo ) {
		// Compose the shareable report once, at catch time, so it's stable
		// (timestamp included) and identical to what "Copy details" copies.
		this.setState( { report: this.composeReport( error, errorInfo ) } );

		// This is the boundary's job: surface the caught error for debugging.
		// eslint-disable-next-line no-console
		if ( typeof console !== 'undefined' && console.error ) {
			// eslint-disable-next-line no-console
			console.error(
				`${ pluginName() } dashboard error:`,
				error,
				errorInfo
			);
		}
	}

	composeReport( error, errorInfo ) {
		const s = window.blocklaneProAdmin || {};
		const componentStack =
			( errorInfo && errorInfo.componentStack ) || 'n/a';

		return [
			`${ pluginName() } dashboard error`,
			'',
			`Screen:     ${ this.props.screen || 'app' }`,
			`Message:    ${ ( error && error.message ) || 'Unknown error' }`,
			`Plugin:     ${ s.version || 'n/a' }`,
			`WordPress:  ${ s.wpVersion || 'n/a' }`,
			`PHP:        ${ s.phpVersion || 'n/a' }`,
			`URL:        ${ window.location.href }`,
			`When:       ${ new Date().toISOString() }`,
			`User agent: ${ navigator.userAgent }`,
			'',
			'Stack:',
			( error && error.stack ) || 'n/a',
			'',
			'Component stack:',
			componentStack.trim(),
		].join( '\n' );
	}

	handleReset() {
		this.setState( { error: null, report: '', copied: false } );
	}

	handleCopy() {
		const { report } = this.state;
		const markCopied = () => this.setState( { copied: true } );
		const selectField = () => {
			const el = this.reportRef.current;
			if ( el ) {
				el.focus();
				el.select();
			}
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard
				.writeText( report )
				.then( markCopied )
				.catch( selectField );
		} else {
			// Insecure context / no API: surface the field so it can be copied by hand.
			selectField();
		}
	}

	render() {
		const { error, report, copied } = this.state;

		if ( ! error ) {
			return this.props.children;
		}

		return (
			<div className="blocklane-pro-error-boundary" role="alert">
				<h2 className="blocklane-pro-error-boundary__title">
					{ __( 'This screen ran into a problem', 'blocklane' ) }
				</h2>
				<p className="blocklane-pro-error-boundary__message">
					{ __(
						'Something on this screen failed to load. Try again, or switch to another screen from the sidebar — the rest of the dashboard still works. Copy the details to share with support.',
						'blocklane'
					) }
				</p>
				{ error.message ? (
					<pre className="blocklane-pro-error-boundary__detail">
						{ String( error.message ) }
					</pre>
				) : null }
				<details className="blocklane-pro-error-boundary__details">
					<summary>{ __( 'Error report', 'blocklane' ) }</summary>
					<textarea
						ref={ this.reportRef }
						className="blocklane-pro-error-boundary__report"
						readOnly
						rows={ 10 }
						value={ report }
						onFocus={ ( event ) => event.target.select() }
					/>
				</details>
				<div className="blocklane-pro-error-boundary__actions">
					<Button
						variant="primary"
						onClick={ this.handleReset }
						__next40pxDefaultSize
					>
						{ __( 'Try again', 'blocklane' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ this.handleCopy }
						__next40pxDefaultSize
					>
						{ copied
							? __( 'Copied!', 'blocklane' )
							: __( 'Copy details', 'blocklane' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => window.location.reload() }
						__next40pxDefaultSize
					>
						{ __( 'Reload dashboard', 'blocklane' ) }
					</Button>
				</div>
			</div>
		);
	}
}
