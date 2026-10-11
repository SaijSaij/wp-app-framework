<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use Closure;
use LogicException;

/**
 * Tracks the framework block being rendered and whether the current render
 * request comes from the block editor. One instance per framework instance,
 * provided by the framework at runtime.
 *
 * @internal Theme code uses the template functions field(), inner_blocks() and is_editor().
 *
 * @phpstan-type RenderingBlock array{name: string, fields: array<string, array<string, mixed>>, innerBlocks: bool, classes: list<string>, wrapper: bool}
 */
final class RenderContext
{
    /** @var list<RenderingBlock> */
    private array $blocks = [];

    private bool $editor = false;

    /**
     * Runs a block's render callback with the block as current block.
     *
     * @param array<string, array<string, mixed>> $fields         Inline fields by path pattern, e.g. "buttons.*.label".
     * @param Closure(): string                   $render
     * @param list<string>                        $classes        Class names of the block supports for the root element.
     * @param bool                                $requireWrapper The block uses block supports: rendering without
     *                                                            printing the wrapper attributes is an error.
     */
    public function renderBlock(string $name, array $fields, bool $innerBlocks, Closure $render, array $classes = [], bool $requireWrapper = false): string
    {
        $index = count($this->blocks);
        $this->blocks[] = ['name' => $name, 'fields' => $fields, 'innerBlocks' => $innerBlocks, 'classes' => $classes, 'wrapper' => false];
        try {
            $html = $render();
            if ($requireWrapper && !$this->blocks[$index]['wrapper']) {
                throw new LogicException(sprintf(
                    'Block %s uses block supports (#[UseSupport]), but did not print its root element with block_wrapper_attributes(), so the class names of the supports are missing.',
                    $name,
                ));
            }

            return $html;
        } finally {
            array_pop($this->blocks);
        }
    }

    /**
     * Records that the current block printed the attributes of its root element
     * and returns the class names of its block supports; none outside of a block.
     *
     * @return list<string>
     */
    public function applyWrapper(): array
    {
        $current = array_key_last($this->blocks);
        if ($current === null) {
            return [];
        }
        $this->blocks[$current]['wrapper'] = true;

        return $this->blocks[$current]['classes'];
    }

    /**
     * Runs a render for the block editor: templates mark their inline fields and inner blocks.
     *
     * @template T
     * @param Closure(): T $render
     * @return T
     */
    public function renderForEditor(Closure $render): mixed
    {
        $previous = $this->editor;
        $this->editor = true;
        try {
            return $render();
        } finally {
            $this->editor = $previous;
        }
    }

    public function isEditor(): bool
    {
        return $this->editor;
    }

    /**
     * @return RenderingBlock
     */
    public function getBlock(string $caller): array
    {
        return $this->blocks[array_key_last($this->blocks) ?? -1]
            ?? throw new LogicException(sprintf('%s is only available in templates of blocks rendered by the framework.', $caller));
    }
}
