# Building Blocklane from source

The PHP needs no build. The JavaScript and CSS under `inc/*/src`, `inc/onboarding/src` and `inc/extensions/src` are built with [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) into the `build/` directories the plugin loads.

Requirements: Node.js 22 and npm 10 (what the plugin's own CI uses).

Two files under `inc/shared/` are generated copies, committed here so the editor
stand-ins build from one definition: `inc/shared/mega-menu/block-metadata.json`
and `inc/shared/forms/form-step-metadata.json` are the Pro blocks' `block.json`,
rendered by the plugin's edition generator (which this source tree does not
include). Do not edit them; a release regenerates them.

```
npm ci
npm run build
```

`npm run build` runs every `build:*` script in `package.json`, one per bundle. Every bundle is built through the same config chain, which this tree includes: `webpack.config.js` at the root (the `--webpack-src-dir` builds) and `inc/extensions/webpack.config.js` (the extensions bundle), both of which load `bin/third-party-notices.cjs` to write each build folder's `THIRD-PARTY-NOTICES.txt`. The plugin directory's release zip is this tree with the `src/` directories and the build tooling left out; the `build/` output is what ships, and it is byte-identical to what `npm ci && npm run build` produces here (the release pipeline rebuilds this tree and compares).

`npm run lint:js` and `npm run lint:css` run the linters.
