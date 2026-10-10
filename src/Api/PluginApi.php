<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use Attribute;

/**
 * Injects the public API (`#[PublicApi]`) of a plugin into a constructor
 * parameter of a service: `#[PluginApi('my-plugin')] ?NoteApi $notes = null`.
 *
 * The parameter must be nullable with the default null: it receives null if
 * the plugin is not active. The class is not loaded by the build, so the plugin
 * may be missing; require it as a development dependency for the IDE and static
 * analysis. Resolving the service before `plugins_loaded` is an error, because
 * not all plugins are loaded then.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class PluginApi
{
    /**
     * @param string $plugin Slug of the plugin (its directory name).
     */
    public function __construct(
        public string $plugin,
    ) {}
}
