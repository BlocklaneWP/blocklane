/**
 * The one page reload the Site Visibility screen makes: after a save, so
 * the server-rendered toolbar indicator shows the SAVED state (design spec,
 * the toolbar-indicator amendment). A module of its own so the tests can
 * observe it; jsdom does not implement navigation.
 */
export const reloadPage = () => window.location.reload();
