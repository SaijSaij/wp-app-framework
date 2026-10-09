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
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TemplateFunction
{
    public function __construct(
        public string $name,
        public bool $html = false,
    ) {
    }
}
