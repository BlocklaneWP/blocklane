/**
 * NoticeDetail — the one server detail line an error notice may carry
 * (the C-settings spec (2026-10-12) SD13; SEO SD-3): the server's own
 * message as a muted second line under the notice's sentence, so an expired
 * nonce or a refused request is never an unexplained failure. Read by
 * LoadError and by the error notices of a failed write: the Settings save
 * error and the Site Visibility save error (D-SV3). Nothing renders without
 * a detail.
 */

/**
 * @param {Object} props
 * @param {string} [props.children] The server's message ('' or none: no line).
 * @return {JSX.Element|null} The line.
 */
export const NoticeDetail = ( { children } ) =>
	children ? <p className="blocklane-notice-detail">{ children }</p> : null;

export default NoticeDetail;
