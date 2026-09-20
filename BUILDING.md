# Building Blocklane from source

The PHP needs no build. The JavaScript and CSS under `inc/*/src`, `inc/onboarding/src` and `inc/extensions/src` are built with [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) into the `build/` directories the plugin loads.

Requirements: Node.js 22 and npm 10 (what the plugin's own CI uses).

```
npm ci
npm run build
```

`npm run build` runs every `build:*` script in `package.json`, one per bundle. The plugin directory's release zip is this tree with the `src/` directories and the build tooling left out; the `build/` output is what ships.

`npm run lint:js`, `npm run lint:css` and the `test:*` scripts run the linters and the unit tests the source ships with.
