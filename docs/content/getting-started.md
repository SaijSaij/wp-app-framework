# Getting started

In this walkthrough you set up a block theme called `my-theme` and give it a first block: a teaser with a title and a text that content editors type directly into the editor canvas, and a switch in the inspector that highlights it. Along the way you see every file a consumer of the framework needs and why it is there.

Plugins and child themes with code of their own are set up the same way. [Boot and modes](./guides/boot.md), [Plugins](./guides/plugins.md) and [Child themes](./guides/child-themes.md) describe what differs.

## What you need

You need PHP 8.5, Composer and Node 24. The simplest way to get them is [wp-dev-env](https://github.com/webcodingcat/wp-dev-env): it runs them in a container together with a local WordPress, so you only need Docker on your machine.

## The quick way: the starter

The [starter](https://github.com/webcodingcat/wp-app-starter) creates a theme, a child theme or a plugin with everything this page explains:

```sh
composer create-project saij/wp-app-starter my-theme
```

The directory name becomes the slug. The installer asks for the kind of project, its name, the Composer package, the PHP namespace and the namespace of your blocks, and whether to add an example block, the code checks (PHPStan, PHP-CS-Fixer, ESLint), the [release workflow](./build-and-release/ci.md) and browser tests for [wp-test-env](https://github.com/webcodingcat/wp-test-env). Then it installs the dependencies and, if Node 24 is available, builds the scripts and styles. The project's README lists its commands.

The rest of this page builds the same theme by hand, so you see every file and why it is there.

## The files of the theme

When you're done, the theme looks like this:

```
my-theme/
├── composer.json
├── functions.php
├── package.json
├── style.css
├── theme.json
├── templates/index.html
├── webpack.config.js
├── assets/
│   └── styles.css
└── src/
    └── Block/Teaser/
        ├── Teaser.php
        ├── TeaserAttributes.php
        └── Teaser.twig
```

`style.css`, `theme.json` and `templates/index.html` are the same as in any block theme. `composer.json` and `functions.php` bring in the framework and start it. `package.json` and `webpack.config.js` build the JavaScript for the editor and the stylesheet. Your own code lives in `src/`; the stylesheet lives in `assets/`, the directory for everything that belongs to no PHP class.

### composer.json

Composer installs the framework and tells it where your classes are. The framework comes from [Packagist](https://packagist.org/packages/saij/wp-app-framework) like any other package; the lock file pins the exact version. The PSR-4 namespace `Acme\MyTheme\` maps to `src/`, and every class in there is something the framework can use – a block, a hook, a service.

```json
{
    "name": "acme/my-theme",
    "type": "wordpress-theme",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.5",
        "saij/wp-app-framework": "^0.16"
    },
    "autoload": {
        "psr-4": { "Acme\\MyTheme\\": "src/" }
    },
    "scripts": {
        "build-manifest": "wp-app-framework build",
        "release": "wp-app-framework release"
    },
    "extra": {
        "wp-app-framework": {
            "namespace": "my-theme"
        },
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

A few entries deserve a closer look:

- `type` tells the framework's build what kind of project this is. `wordpress-theme` covers themes and child themes, `wordpress-plugin` plugins.
- The two scripts give you short commands for the framework's tools: `composer build-manifest` checks your code, and `composer release` builds a ZIP for production.
- `extra.wp-app-framework.namespace` names your blocks, REST routes and settings: the block `teaser` below is registered as `my-theme/teaser`. Choose it once; content refers to it ([Concepts](./concepts.md#consumers-and-instances) explains why it is separate from the directory name).
- `extra.strauss` configures the prefixing of the release build. A release renames the framework and all dependencies into your own namespace (here `Acme\MyTheme\Vendor\`), so they cannot collide with other themes or plugins. [Release](./build-and-release/release.md#strauss-settings) explains each value.

### style.css

The theme header works as usual. `Version:` is the version the release build puts into the ZIP's file name:

```css
/*
Theme Name: My Theme
Version: 0.1.0
Requires at least: 7.1
Requires PHP: 8.5
Text Domain: my-theme
*/
```

### functions.php

`functions.php` loads Composer's autoloader and boots the framework. A release ships the prefixed dependencies in `vendor-prefixed/` instead of `vendor/`, so the file picks whichever exists:

```php
<?php

declare(strict_types=1);

// Release builds ship prefixed dependencies in vendor-prefixed/; source checkouts use Composer's vendor/.
require is_file(__DIR__ . '/vendor-prefixed/autoload.php')
    ? __DIR__ . '/vendor-prefixed/autoload.php'
    : __DIR__ . '/vendor/autoload.php';

Saij\WpAppFramework\Theme::boot();
```

That is the only call into the framework you write by hand. Everything else – blocks, hooks, routes – the framework finds through the attributes on your classes.

### package.json and webpack.config.js

The block editor needs JavaScript: the framework's editor runtime, which makes your blocks editable, and any editor scripts of your own. Blocks with [front-end scripts](./guides/frontend.md) need them built as well. The framework ships only the sources of the runtime. Your theme builds them together with its own scripts and its stylesheet, using `@wordpress/scripts` and a webpack helper from the framework (see [Editor](./guides/editor.md#build)).

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

The helper needs the slug of the theme, which is its directory name:

```js
module.exports = require( '@saij/wp-app-framework/webpack' )( {
	slug: 'my-theme',
	root: __dirname,
} );
```

### assets/styles.css

Blocks are styled with Tailwind CSS. If the theme has an `assets/styles.css`, the build compiles it with Tailwind, and the framework loads it on the front end and in the editor canvas. The stylesheet maps Tailwind's tokens to the values of `theme.json`. Here `bg-primary` uses the color `primary`, which `theme.json` defines:

```css
@layer theme, base, components, utilities;
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities) source(none);
@source "../src/**/*.twig";

@theme inline {
	--color-primary: var(--wp--preset--color--primary);
}
```

[Styles](./guides/styles.md) explains each line.

## A first block

A block consists of three parts: a class that describes the block and renders it, a class that holds its attributes, and a Twig template with the markup. They sit next to each other in `src/Block/Teaser/`.

The block class carries `#[Block]` with the block's name, title, category and icon. Its constructor asks for the `TemplateRenderer`, which the framework provides, and `render()` passes the attributes to the template:

```php
<?php

declare(strict_types=1);

namespace Acme\MyTheme\Block\Teaser;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Twig\TemplateRenderer;

#[Block('teaser', title: 'Teaser', category: BlockCategory::Text, icon: 'text')]
final readonly class Teaser
{
    public function __construct(private TemplateRenderer $renderer) {}

    public function render(TeaserAttributes $attrs): string
    {
        return $this->renderer->render('Block/Teaser/Teaser.twig', ['attrs' => $attrs]);
    }
}
```

The attribute class lists what content editors can set. Each constructor parameter becomes a block attribute. `#[PlainText]` and `#[RichText]` mark text that is edited directly in the canvas; the plain `bool` gets a toggle in the block inspector automatically.

```php
<?php

declare(strict_types=1);

namespace Acme\MyTheme\Block\Teaser;

use Saij\WpAppFramework\Value\PlainText;
use Saij\WpAppFramework\Value\RichText;

final readonly class TeaserAttributes
{
    public function __construct(
        #[PlainText] public string $title = '',
        #[RichText(allowedFormats: ['core/bold', 'core/italic'])] public string $text = '',
        public bool $highlighted = false,
    ) {}
}
```

The template renders the markup. `block_wrapper_attributes()` goes on the root element and adds the classes WordPress needs. `field('title')` marks the element that shows the title, so that the editor can make it editable; on the front end it prints nothing.

```twig
<section {{ block_wrapper_attributes({class: attrs.highlighted ? 'p-4 bg-primary' : 'p-4'}) }}>
    <h2{{ field('title') }}>{{ attrs.title }}</h2>
    <div{{ field('text') }}>{{ attrs.text|raw }}</div>
</section>
```

The template switches between two complete class names instead of building one from the attribute. That is deliberate: Tailwind finds class names by reading the templates, so it only generates classes that appear there in full.

[Blocks](./guides/blocks.md) covers all of this in depth.

## Build and run

Install the dependencies and build the assets. Run `composer install` first, because the `file:` dependency in `package.json` points into `vendor/`:

```sh
composer install
npm install
npm run build             # editor runtime, editor and front-end scripts and stylesheet into build/
composer build-manifest   # checks attributes and services; writes nothing
```

`composer build-manifest` is optional here, but useful: it checks your attributes and services and reports problems with class and parameter names.

Now activate the theme in WordPress and add the teaser to a page. You don't need to build anything else while you develop. The framework builds its manifest itself and caches it in `wp-content/cache/my-theme/` (see [Boot and modes](./guides/boot.md#modes)), and `npm run start` rebuilds the editor scripts and the stylesheet whenever you change them.

When you are ready to ship the theme, [Release](./build-and-release/release.md) shows how to build a ZIP that runs in any WordPress installation.
