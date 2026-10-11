<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions for block templates.
 */
final class BlockTemplateFunctions
{
    public function __construct(private readonly RenderContext $context) {}

    /**
     * The attributes of the block's root element from its supports (class
     * names, anchor, alignment), as returned by get_block_wrapper_attributes(),
     * plus the class names of its custom block supports (#[UseSupport]).
     * Empty outside of rendering a block.
     *
     * @param array<string, string> $extra Additional attributes; `class` and `style` are merged.
     */
    #[TemplateFunction('block_wrapper_attributes', html: true)]
    public function blockWrapperAttributes(array $extra = []): string
    {
        $classes = $this->context->applyWrapper();
        if ($classes !== []) {
            $extra['class'] = trim(($extra['class'] ?? '') . ' ' . implode(' ', $classes));
        }

        return get_block_wrapper_attributes($extra);
    }
}
