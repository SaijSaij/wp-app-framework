<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Support;

use Attribute;

/**
 * Declares a custom block support: blocks of the same theme or plugin that
 * use it with `#[UseSupport]` get an attribute, a control in the inspector and
 * the class names it returns on their root element.
 *
 * The class is a service with a public method `className()`. Its only
 * parameter is the value of the attribute: its type (int, float, string, bool
 * or a backed enum, optionally nullable) derives the schema and the control,
 * its default is the default of the attribute. The method returns the class
 * names for the value (separated by spaces), or null or '' for none; it runs
 * while the block renders, so the service may depend on other services.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class BlockSupport
{
    /**
     * @param string       $attribute Name of the block attribute, in camelCase (e.g. "colorScheme").
     * @param string       $label     Label of the control in the inspector.
     * @param SupportGroup $group     Where the inspector shows the control.
     */
    public function __construct(
        public string $attribute,
        public string $label,
        public SupportGroup $group = SupportGroup::Styles,
    ) {}
}
