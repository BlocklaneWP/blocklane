# Shared editor source (`inc/shared/`)

Single source of truth for editor-side primitives used by more than one
bundle. Build-input only — nothing in here ships or loads at runtime; each
bundle's webpack build compiles its own copy in, so there is no runtime
coupling and no extra requests.
(`inc/shared` is excluded in `.distignore`.)

## How to use it

Import with a relative path from any bundle's `src/` — webpack follows
imports outside the `--webpack-src-dir` root:

```js
// from inc/popups/src/index.js
import useToolsPanelDropdownMenuProps from '../../shared/use-tools-panel-dropdown-menu-props';

// from inc/menu-designer/src/mega-menu/edit.js
import { ANIMATION_OPTIONS } from '../../../shared/animation-options';
```

```scss
// from inc/extensions/src/editor.scss
@import '../../shared/styles/core-mirrors';
```

## Rules

1. **Add here when a second bundle needs it.** One consumer: keep it local.
   Two consumers: move it here and re-point both — never copy. A "keep in
   sync by hand" comment is the smell this directory exists to remove.
2. **Mirrored core recipes are copied verbatim** with a comment naming the
   core source (file/function). When a WP update changes core's version,
   update the one copy here.
3. **Measure, don't approximate.** Anything mirroring core UI must be
   verified against the rendered core control in the editor (computed
   geometry, not eyeballs) before it lands.
4. **PHP↔JS values don't belong here.** Anything both PHP and JS need
   (breakpoints, feature flags) flows through a localized global
   (`blocklaneProExtensions`) so there is exactly one definition, in PHP.

## Inventory

| File | What | Consumers |
|---|---|---|
| `use-tools-panel-dropdown-menu-props.js` | Core's private ToolsPanel options-menu placement (fly out left of the sidebar) | extensions (animation, background-url, responsive-controls), popups |
| `animation-options.js` | Open-animation vocabulary for the shared `blocklaneProAnimate*` keyframes | menu-designer (mega menu, core overlay), popups |
| `styles/_core-mirrors.scss` | Core CSS mirrored for our controls (HeightControl fieldset reset → `.blocklane-pro-height-control`) | extensions |

## Building a new inspector control? Inherit, don't rewrite

- Panel = `ToolsPanel` + `dropdownMenuProps={ useToolsPanelDropdownMenuProps() }`.
- Pin (`isShownByDefault`) only the control that IS the feature — the
  picker, the required field. Everything else stays unpinned so it hides
  until it holds a value or is enabled from the panel menu (core's
  Dimensions model: Padding pinned, Margin hidden). A panel where every
  item is pinned has thrown away the reason to use ToolsPanel.
- Every core component gets its modern props: `__next40pxDefaultSize`,
  `__nextHasNoMarginBottom` (or `size="__unstable-large"` where that's the
  API). No `Spacer` wrappers around sliders.
- Paired unit + slider rows use core's HeightControl anatomy: a
  `fieldset.blocklane-pro-height-control` with a
  `BaseControl.VisualLabel as="legend"` above a `Flex` of two `FlexItem
  isBlock` halves — label on the legend, `hideLabelFromVision` on the input.
- Text styling through component tokens (`Text variant="muted" size={12}`),
  not bespoke CSS.
- Block-toolbar items: multiple related actions collapse into one
  `ToolbarDropdownMenu` (core's Replace-flow pattern), and Manage Classes
  always sorts last — its BlockEdit filter runs at priority 100, so register
  new toolbar HOCs at the default 10 and ordering takes care of itself.
