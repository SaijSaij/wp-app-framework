<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use InvalidArgumentException;
use Saij\WpAppFramework\Twig\TemplateFunction;

/**
 * Twig functions that mark the editable parts of a block template. In the
 * block editor the runtime replaces marked elements with inline editors; on the
 * front end the marks are empty.
 */
final readonly class EditorTemplateFunctions
{
    public function __construct(private RenderContext $context)
    {
    }

    /**
     * Marks the element of an inline field (#[PlainText], #[RichText], #[Image]):
     * `<h1{{ field('headline') }}>` or, in a list, `field(['buttons', loop.index0, 'label'])`.
     * The path must name an inline field of the rendered block that is edited
     * in the canvas (not with inspector: true); otherwise an exception is
     * thrown, in the editor and on the front end alike.
     *
     * @param string|list<string|int> $path
     */
    #[TemplateFunction('field', html: true)]
    public function field(string|array $path): string
    {
        $block = $this->context->getBlock('field()');
        $segments = is_string($path) ? explode('.', $path) : $path;
        if ($segments === [] || !array_is_list($segments)) {
            throw new InvalidArgumentException(sprintf('field() in block %s needs a path such as "headline" or ["buttons", 0, "label"].', $block['name']));
        }

        $pattern = [];
        foreach ($segments as $segment) {
            if (is_int($segment) || (is_string($segment) && ctype_digit($segment))) {
                $pattern[] = '*';
            } elseif (is_string($segment) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) === 1) {
                $pattern[] = $segment;
            } else {
                throw new InvalidArgumentException(sprintf('field() in block %s: path segments must be attribute names or list indexes, %s given.', $block['name'], var_export($segment, true)));
            }
        }

        $name = implode('.', array_map(strval(...), $segments));
        $field = $block['fields'][implode('.', $pattern)] ?? null;
        if ($field !== null && ($field['inspector'] ?? false)) {
            throw new InvalidArgumentException(sprintf(
                'field() in block %s: "%s" is edited in the inspector (inspector: true); do not mark it with field().',
                $block['name'],
                $name,
            ));
        }
        if ($field === null) {
            throw new InvalidArgumentException(sprintf(
                'field() in block %s: "%s" is not an inline field of the block (%s).',
                $block['name'],
                $name,
                $block['fields'] === [] ? 'it has none' : 'inline fields: ' . implode(', ', array_keys($block['fields'])),
            ));
        }

        return $this->context->isEditor() ? sprintf(' data-field="%s"', esc_attr($name)) : '';
    }

    /**
     * Marks the element that contains the inner blocks: `<div{{ inner_blocks() }}>{{ content|raw }}</div>`.
     * Only for blocks whose render() accepts string $content.
     */
    #[TemplateFunction('inner_blocks', html: true)]
    public function innerBlocks(): string
    {
        $block = $this->context->getBlock('inner_blocks()');
        if (!$block['innerBlocks']) {
            throw new InvalidArgumentException(sprintf('inner_blocks() in block %s: the block has no inner blocks; its render() must accept string $content.', $block['name']));
        }

        return $this->context->isEditor() ? ' data-inner-blocks' : '';
    }

    /**
     * Whether the block is rendered for the block editor. Templates render
     * inline fields in the editor even when they are empty, so the editor can
     * show them: `{% if attrs.topline or is_editor() %}`.
     */
    #[TemplateFunction('is_editor')]
    public function isEditor(): bool
    {
        return $this->context->isEditor();
    }
}
