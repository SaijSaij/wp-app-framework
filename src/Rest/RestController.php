<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

use Attribute;

/**
 * Marks a service as a REST controller and defines the REST namespace of its
 * `#[RestRoute]` methods, optionally with a base path prepended to every route.
 *
 * The namespace has no leading or trailing slash (e.g. `sections/v1`). The base
 * path is empty or starts with a slash and does not end with one (e.g. `/items`);
 * like route paths it may contain `{name}` placeholders.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RestController
{
    public function __construct(
        public string $namespace,
        public string $base = '',
    ) {
    }
}
