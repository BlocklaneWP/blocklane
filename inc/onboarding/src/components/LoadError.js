/**
 * LoadError — the one load-failure notice (design spec V22 and R3/R3a; spec
 * the C-settings spec (2026-10-12) §3.3). Core's error Notice, not
 * dismissible (nothing else would bring the content back), with the
 * screen's sentence ("[What] could not be loaded. [reassurance]") and one
 * secondary Retry. It sits in the content area only: the page header and
 * tabs keep rendering around it. When the caller has the server's own
 * message, it is the notice's second line (NoticeDetail; Settings SD13,
 * SEO SD-3).
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

import { NoticeDetail } from './NoticeDetail';

/**
 * @param {Object}     props
 * @param {string}     props.message  What could not be loaded, and what is safe.
 * @param {string}     [props.detail] The server's message, drawn as a muted second line.
 * @param {() => void} props.onRetry  Loads again.
 * @return {JSX.Element} The notice.
 */
export const LoadError = ( { message, detail, onRetry } ) => (
	<div className="blocklane-load-error" data-notice="load-error">
		<Notice
			status="error"
			isDismissible={ false }
			actions={ [
				{
					label: __( 'Retry', 'blocklane' ),
					variant: 'secondary',
					onClick: onRetry,
				},
			] }
		>
			{ message }
			<NoticeDetail>{ detail }</NoticeDetail>
		</Notice>
	</div>
);

export default LoadError;
