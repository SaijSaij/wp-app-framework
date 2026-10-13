<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use Attribute;

/**
 * Marks a service as a REST controller and defines the REST namespace of its
 * `#[RestRoute]` methods, optionally with a base path prepended to every route.
 *
 * The REST namespace is the namespace of the theme or plugin
 * (`extra.wp-app-framework.namespace`) followed by the version, e.g. `v1` for
 * `sections/v1`. The version has no leading or trailing slash. The base path is
 * empty or starts with a slash and does not end with one (e.g. `/items`); like
 * route paths it may contain `{name}` placeholders.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RestController
{
    public function __construct(
        public string $version,
        public string $base = '',
    ) {}
}
