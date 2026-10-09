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
 * @phpstan-type RenderingBlock array{name: string, fields: array<string, array<string, mixed>>, innerBlocks: bool}
 */
final class RenderContext
{
    /** @var list<RenderingBlock> */
    private array $blocks = [];

    private bool $editor = false;

    /**
     * Runs a block's render callback with the block as current block.
     *
     * @param array<string, array<string, mixed>> $fields Inline fields by path pattern, e.g. "buttons.*.label".
     * @param Closure(): string                   $render
     */
    public function renderBlock(string $name, array $fields, bool $innerBlocks, Closure $render): string
    {
        $this->blocks[] = ['name' => $name, 'fields' => $fields, 'innerBlocks' => $innerBlocks];
        try {
            return $render();
        } finally {
            array_pop($this->blocks);
        }
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
