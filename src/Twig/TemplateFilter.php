<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use Attribute;

/**
 * Registers a public method of a service as a Twig filter.
 *
 * The filtered value is passed as the first parameter, filter arguments follow.
 * The service is resolved only when a template applies the filter. The return
 * value is escaped like any other output unless `html` is true; then it is
 * printed as is and the method must return a string. An HTML filter receives
 * the filtered value HTML-escaped unless it is already safe (e.g. `|raw`);
 * further arguments are passed unescaped and must be escaped by the method.
 *
 * With `public: true`, a plugin offers the filter to the templates of other
 * consumers (the theme, other plugins), and a theme to the templates of its
 * child theme. Its name must start with the template prefix of the plugin or
 * theme (the slug with underscores, e.g. `my_plugin_`) and its signature must
 * not use classes of the framework or of other dependencies. Consumers declare
 * the plugin in `extra.wp-app-framework.template-plugins` of their
 * composer.json and call the filter by its name; for a child theme, its parent
 * theme is declared automatically. Child themes cannot offer filters.
 *
 * @api Attributes/Twig
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TemplateFilter
{
    /**
     * @param string $name   Name of the filter in templates, a valid identifier, e.g. `highlight`; it must not reuse
     *                       a filter Twig provides.
     * @param bool   $html   The method returns HTML (`string`), which is printed unescaped; it receives the filtered
     *                       value HTML-escaped unless the value is already safe.
     * @param bool   $public Offer the filter to the templates of other consumers: a plugin to themes and other
     *                       plugins, a theme to its child themes. The name must then start with the template prefix.
     */
    public function __construct(
        public string $name,
        public bool $html = false,
        public bool $public = false,
    ) {}
}
