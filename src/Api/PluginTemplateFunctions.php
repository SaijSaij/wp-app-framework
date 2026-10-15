<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions about plugins built with the framework.
 */
final class PluginTemplateFunctions
{
    /**
     * Whether the plugin with this slug is active and has booted the framework,
     * so its public template functions and filters are available. Calling them
     * without the plugin is an error, so check first.
     *
     * ```twig
     * {% if has_plugin('my-plugin') %}
     *     {{ my_plugin_note_count() }}
     * {% endif %}
     * ```
     *
     * @param string $slug Slug of the plugin, e.g. `my-plugin`.
     */
    #[TemplateFunction('has_plugin')]
    public function hasPlugin(string $slug): bool
    {
        return InstanceBridge::isBooted($slug);
    }
}
