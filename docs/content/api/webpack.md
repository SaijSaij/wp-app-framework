# Webpack helper

The webpack helper builds the editor runtime of your theme or plugin, your editor and view scripts, the shared modules and the stylesheet. Your `webpack.config.js` calls it:

```js
module.exports = require( '@webcodingcat/wp-app-framework/webpack' )( {
	slug: 'my-theme',
	root: __dirname,
} );
```

| Option | Meaning |
|---|---|
| `slug` | The slug of the theme or plugin: its directory name. The script handles and the JavaScript global derive from it. |
| `root` | The directory of the project. |

Until 0.15 the helper was required by its path, `./vendor/webcodingcat/wp-app-framework/webpack.config.js`; that still works.

## Conventions

| Source | Built to | Loaded |
|---|---|---|
| `src/**/Name.editor.js` next to a block or block support class | `build/**/Name.editor.js` | in the editor with the runtime |
| `src/**/Name.view.js` next to a block or block support class | `build/**/Name.view.js` | where the block renders |
| `assets/**/*.editor.js` | `build/assets/**/*.editor.js` | in the post and site editor |
| `assets/**/*.view.js` (document views) | `build/assets/**/*.view.js` | on every page of the front end |
| `assets/shared/**/*.js`, imported as `#shared/<name>` | `build/assets/shared/**/*.js` | with the scripts that import them |
| `assets/styles.css` | `build/styles.css`, `build/styles.preload.php` | on the front end and in the editor canvas |

`#shared/*` needs the entry `"imports": { "#shared/*": "./assets/shared/*.js" }` in your `package.json`. The framework's own scripts go to `build/vendor/wp-app-framework/`. See [Editor](../guides/editor.md#build) for the complete list, the script handles and the errors of the helper, and [Front-end scripts](../guides/frontend.md) and [Styles](../guides/styles.md).

## Imports

| Import | Resolves to |
|---|---|
| `@webcodingcat/wp-app-framework` | the editor API of your theme or plugin (see [JavaScript](./js/editor/registerControl.md)); not in view scripts |
| `@webcodingcat/wp-app-framework/view` | the view API (see [BlockView](./js/view/BlockView.md)) |
| `#shared/<name>` | a shared module of your theme or plugin |
| `@wordpress/*` | the scripts of WordPress, as with `@wordpress/scripts` |
