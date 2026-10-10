<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

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
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TemplateFilter
{
    public function __construct(
        public string $name,
        public bool $html = false,
    ) {}
}
