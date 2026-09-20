/**
 * Byte formatting that mirrors PHP's size_format() with its default 0
 * decimals — the front hints use size_format, and every editor/admin
 * surface must render the same string (three hand-rolled formatters had
 * already drifted before this module existed).
 */
const KB = 1024;

export const formatSize = ( bytes ) => {
	if ( bytes >= KB * KB * KB ) {
		return Math.round( bytes / ( KB * KB * KB ) ) + ' GB';
	}
	if ( bytes >= KB * KB ) {
		return Math.round( bytes / ( KB * KB ) ) + ' MB';
	}
	if ( bytes >= KB ) {
		return Math.round( bytes / KB ) + ' KB';
	}
	return bytes + ' B';
};
