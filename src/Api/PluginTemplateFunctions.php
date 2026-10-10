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
     * so its public template functions and filters are available.
     */
    #[TemplateFunction('has_plugin')]
    public function hasPlugin(string $slug): bool
    {
        return PluginBridge::isBooted($slug);
    }
}
