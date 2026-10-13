<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use Closure;
use LogicException;

/**
 * Tracks the framework block being rendered, whether the current render
 * request comes from the block editor or its preview of the front end, and
 * which variants a cached block declares. One instance per framework
 * instance, provided by the framework at runtime.
 *
 * @internal Theme code uses the template functions field(), inner_blocks() and is_editor().
 *
 * @phpstan-type RenderingBlock array{name: string, fields: array<string, array<string, mixed>>, innerBlocks: bool, classes: list<string>, attributes: array<string, string>, wrapper: bool, cached: list<string>|null}
 */
final class RenderContext
{
    /** @var list<RenderingBlock> */
    private array $blocks = [];

    private bool $editor = false;

    private bool $preview = false;

    /**
     * Runs a block's render callback with the block as current block.
     *
     * @param array<string, array<string, mixed>> $fields         Inline fields by path pattern, e.g. "buttons.*.label".
     * @param Closure(): string                   $render
     * @param list<string>                        $classes        Class names of the block supports for the root element.
     * @param string|null                         $missingWrapper Error message if the block renders without printing the
     *                                                            wrapper attributes; null if it may (no block supports,
     *                                                            no view scripts).
     * @param array<string, string>               $attributes     Further attributes of the root element (the marker of
     *                                                            the view scripts).
     * @param list<string>|null                   $cached         Types of the variants of a block whose output is
     *                                                            cached (#[Cache]); null if it is not cached.
     */
    public function renderBlock(string $name, array $fields, bool $innerBlocks, Closure $render, array $classes = [], ?string $missingWrapper = null, array $attributes = [], ?array $cached = null): string
    {
        $index = count($this->blocks);
        $this->blocks[] = ['name' => $name, 'fields' => $fields, 'innerBlocks' => $innerBlocks, 'classes' => $classes, 'attributes' => $attributes, 'wrapper' => false, 'cached' => $cached];
        try {
            $html = $render();
            if ($missingWrapper !== null && !$this->blocks[$index]['wrapper']) {
                throw new LogicException($missingWrapper);
            }

            return $html;
        } finally {
            array_pop($this->blocks);
        }
    }

    /**
     * Records that the current block printed the attributes of its root element
     * and returns the class names of its block supports and its further root
     * attributes; none outside of a block.
     *
     * @return array{classes: list<string>, attributes: array<string, string>}
     */
    public function applyWrapper(): array
    {
        $current = array_key_last($this->blocks);
        if ($current === null) {
            return ['classes' => [], 'attributes' => []];
        }
        $this->blocks[$current]['wrapper'] = true;

        return ['classes' => $this->blocks[$current]['classes'], 'attributes' => $this->blocks[$current]['attributes']];
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
     * Runs a render for the preview of the front end in the block editor: rendered like the front end, never cached.
     *
     * @template T
     * @param Closure(): T $render
     * @return T
     */
    public function renderForPreview(Closure $render): mixed
    {
        $previous = $this->preview;
        $this->preview = true;
        try {
            return $render();
        } finally {
            $this->preview = $previous;
        }
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    /**
     * Checks a template function that depends on the request while the current block is cached: it needs one of the
     * accepted variants; with none accepted, it cannot be used in cached blocks at all.
     *
     * @param list<string> $accepted Types of the variants (CachePolicy) that make the function safe, e.g. "logged_in".
     * @param string       $reason   What the function depends on, e.g. "whether the visitor is logged in".
     */
    public function checkCached(string $function, array $accepted, string $reason): void
    {
        $current = array_key_last($this->blocks);
        $cached = $current === null ? null : $this->blocks[$current]['cached'];
        if ($cached === null || array_intersect($accepted, $cached) !== []) {
            return;
        }

        throw new LogicException(sprintf(
            'Block %s is cached (#[Cache]), but its template calls %s(), which depends on %s; %s.',
            $this->blocks[$current]['name'],
            $function,
            $reason,
            $accepted === []
                ? 'it cannot be used in cached blocks'
                : 'add ' . implode(' or ', array_map(static fn(string $type): string => match ($type) {
                    'logged_in' => 'new LoggedInVary()',
                    'role' => 'new RoleVary()',
                    'user' => 'new UserVary()',
                    default => $type,
                }, $accepted)) . ' to vary of #[Cache]',
        ));
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
