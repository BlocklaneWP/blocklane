/**
 * Root component. The Shell renders immediately and waits on nothing.
 *
 * Nothing at the root knows about editions or licenses. The license context
 * used to mount here, which meant the free artifact carried it too and fired
 * the license status request on every dashboard load against a route it does not
 * register; it now mounts inside the Home hero SLOT its owning unit ships
 * (screens/home/LicenseHero.js), so an artifact without that unit has no
 * provider, no request and no license code at all. The license stays purely
 * informational — nothing gates on it — so mounting it a level down costs one
 * request per Home mount and no wait anywhere.
 */

import { Shell } from './components/Shell';
import { ErrorBoundary } from './components/ErrorBoundary';

export const App = () => (
	<ErrorBoundary>
		<Shell />
	</ErrorBoundary>
);
