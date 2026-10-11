/**
 * Main entry point for the block-editor extensions.
 *
 * @package
 */

// Import frontend styles only
import './style.scss';

// Editor styles are imported separately in editor.js

// Import control modules.
// Transparent Header sits above Animation deliberately: fills render into an
// inspector slot in registration order, and registration order is this list.
import './controls/transparent-header';
import './controls/responsive-controls';
