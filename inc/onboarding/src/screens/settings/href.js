/**
 * settingsHref — THE way to link to the Settings screen or to one of its rows
 * (spec 2026-10-12-c-settings.md §3.6). Every door in the app goes through
 * here; ESLint refuses a `screen: 'settings'` property anywhere else
 * (eslint.config.cjs), and the PHP side has its one twin,
 * Settings::settings_url(). The tab is never written: the screen derives it
 * from the row (a valid `item` wins over `tab`) or opens its default tab.
 *
 * @param {string} [item] A row slug to open and select.
 * @return {string} The URL.
 */
import { routeUrl } from '../../router';

export const settingsHref = ( item ) =>
	routeUrl( { screen: 'settings', item: item || null } );

export default settingsHref;
