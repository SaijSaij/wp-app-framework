<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use Attribute;

/**
 * Registers a public method of a service as a Twig function.
 *
 * The service is resolved only when a template calls the function. The return
 * value is escaped like any other output unless `html` is true; then it is
 * printed as is and the method must return a string. Arguments are passed
 * unescaped, so an HTML function must escape every argument it puts into its
 * result.
 *
 * With `public: true`, a plugin offers the function to the templates of other
 * consumers (the theme, other plugins). Its name must start with the template
 * prefix of the plugin (the slug with underscores, e.g. `my_plugin_`) and its
 * signature must not use classes of the framework or of other dependencies.
 * Consumers declare the plugin in `extra.wp-app-framework.template-plugins`
 * of their composer.json and call the function by its name.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TemplateFunction
{
    public function __construct(
        public string $name,
        public bool $html = false,
        public bool $public = false,
    ) {}
}
