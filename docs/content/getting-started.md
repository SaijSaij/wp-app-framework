# Getting started

This page sets up a block theme `my-theme` with one block. Plugins and child themes with code of their own are set up the same way; see [Boot and modes](./guides/boot.md), [Plugins](./guides/plugins.md) and [Child themes](./guides/child-themes.md) for the differences.

You need PHP 8.5, Composer and Node 24 – or [wp-dev-env](https://github.com/saijsaij/wp-dev-env), which provides them in a container together with a local WordPress.

## Files

```
my-theme/
├── composer.json
├── functions.php
├── package.json
├── style.css
├── theme.json
├── templates/index.html
├── webpack.config.js
└── src/
    ├── styles.css
    └── Block/Teaser/
        ├── Teaser.php
        ├── TeaserAttributes.php
        └── Teaser.twig
```

**`composer.json`** requires the framework from its GitHub repository by version and declares the PSR-4 namespace of the theme's classes in `src/` and the settings of the [release build](./build-and-release/release.md):

```json
{
    "name": "acme/my-theme",
    "type": "wordpress-theme",
    "repositories": [
        { "type": "vcs", "url": "https://github.com/saijsaij/wp-app-framework" }
    ],
    "require": {
        "php": ">=8.5",
        "saij/wp-app-framework": "^0.3"
    },
    "autoload": {
        "psr-4": { "Acme\\MyTheme\\": "src/" }
    },
    "scripts": {
        "build-manifest": "wp-app-framework build",
        "release": "wp-app-framework release"
    },
    "extra": {
        "strauss": {
            "target_directory": "vendor-prefixed",
            "namespace_prefix": "Acme\\MyTheme\\Vendor\\",
            "classmap_prefix": "Acme_MyTheme_Vendor_",
            "include_root_autoload": true,
            "update_call_sites": ["src", "functions.php"],
            "delete_vendor_packages": false
        }
    },
    "config": {
        "platform": { "php": "8.5" }
    }
}
```

**`style.css`** carries the theme header; `Version:` is the version of the release:

```css
/*
Theme Name: My Theme
Version: 0.1.0
Requires at least: 7.1
Requires PHP: 8.5
Text Domain: my-theme
*/
```

**`functions.php`** loads the autoloader and boots the framework:

```php
<?php

declare(strict_types=1);

// Release builds ship prefixed dependencies in vendor-prefixed/; source checkouts use Composer's vendor/.
require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Theme::boot();
```

**`package.json`** and **`webpack.config.js`** build the editor runtime of the framework, the editor scripts and the stylesheet of the theme (see [Editor](./guides/editor.md#build)). The slug is the directory name of the theme:

```json
{
	"name": "my-theme",
	"private": true,
	"scripts": { "build": "wp-scripts build", "start": "wp-scripts start" },
	"devDependencies": {
		"@saij/wp-app-framework": "file:vendor/saij/wp-app-framework",
		"@tailwindcss/webpack": "^4.3.3",
		"@wordpress/scripts": "^36.1.0",
		"tailwindcss": "^4.3.3"
	}
}
```

```js
module.exports = require( './vendor/saij/wp-app-framework/webpack.config.js' )( {
	slug: 'my-theme',
	root: __dirname,
} );
```

**`src/styles.css`** is compiled with Tailwind and loaded on the front end and in the editor canvas; it maps Tailwind tokens to the values of `theme.json` (see [Styles](./guides/styles.md)):

```css
@layer theme, base, components, utilities;
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities) source(none);
@source "./**/*.twig";

@theme inline {
	--color-primary: var(--wp--preset--color--primary);
}
```

`theme.json` and `templates/index.html` are those of any block theme; `theme.json` defines the color `primary` used above.

## A first block

A block is a service class with `#[Block]` and a `render()` method, an attribute class and a Twig template next to it (see [Blocks](./guides/blocks.md)):

```php
<?php

declare(strict_types=1);

namespace Acme\MyTheme\Block\Teaser;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Twig\TemplateRenderer;

#[Block('my-theme/teaser', title: 'Teaser', category: BlockCategory::Text, icon: 'text')]
final readonly class Teaser
{
    public function __construct(private TemplateRenderer $renderer) {}

    public function render(TeaserAttributes $attrs): string
    {
        return $this->renderer->render('Block/Teaser/Teaser.twig', ['attrs' => $attrs]);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Acme\MyTheme\Block\Teaser;

use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;

final readonly class TeaserAttributes
{
    public function __construct(
        #[PlainText] public string $title = '',
        #[RichText(allowedFormats: ['core/bold', 'core/italic'])] public string $text = '',
        public bool $highlighted = false,
    ) {}
}
```

```twig
<section {{ block_wrapper_attributes({class: attrs.highlighted ? 'p-4 bg-primary' : 'p-4'}) }}>
    <h2{{ field('title') }}>{{ attrs.title }}</h2>
    <div{{ field('text') }}>{{ attrs.text|raw }}</div>
</section>
```

The title and the text are edited in the editor canvas; `highlighted` gets a toggle in the block inspector and switches between complete class names, which the stylesheet finds in the template.

## Build and run

```sh
composer install          # before npm: the file: dependency points into vendor/
npm install
npm run build             # editor runtime, editor scripts and stylesheet into build/
composer build-manifest   # checks attributes and services; writes nothing
```

Activate the theme in WordPress. In development the framework builds its manifest itself and caches it in `wp-content/cache/my-theme/` (see [Boot and modes](./guides/boot.md#modes)); `npm run start` rebuilds the editor scripts and the stylesheet on changes. For a release see [Release](./build-and-release/release.md).
