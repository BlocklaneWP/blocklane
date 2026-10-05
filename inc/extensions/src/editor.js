/**
 * Editor entry — bundles editor-only styles into build/editor.css.
 *
 * This stylesheet is enqueued only in the block editor (not on the frontend);
 * see Extensions_Handler::enqueue_block_editor_assets. editor.scss `@import`s each
 * control's editor styles, including the layout/grouping rules that place custom
 * inspector controls (e.g. the paragraph hover-decoration control) correctly.
 */
import './editor.scss';
