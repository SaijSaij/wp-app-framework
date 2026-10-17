<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Rest;

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
 *
 * @api Attributes/REST
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RestController
{
    /**
     * @param string $version The version in the REST namespace, e.g. `v1` for `sections/v1`; no leading or trailing
     *                        slash. May be a class constant.
     * @param string $base    A path prepended to every route of the controller: empty or starting with a slash and not
     *                        ending with one, e.g. `/items`; may contain `{name}` placeholders.
     */
    public function __construct(
        public string $version,
        public string $base = '',
    ) {}
}
