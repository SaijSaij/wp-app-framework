<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block\Markup;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A block found in block markup by the BlockMarkupParser.
 */
#[NoService]
final readonly class ParsedBlock
{
    /**
     * @param array<array-key, mixed>|null $attributes Decoded attributes; null if the JSON is invalid.
     * @param string                       $innerHtml  HTML between the block comments without the inner blocks.
     * @param list<ParsedBlock>            $innerBlocks
     * @param bool                         $php        The block comment contains PHP code (patterns).
     */
    public function __construct(
        private string $name,
        private ?array $attributes,
        private int $line,
        private string $innerHtml = '',
        private array $innerBlocks = [],
        private bool $php = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getInnerHtml(): string
    {
        return $this->innerHtml;
    }

    /**
     * @return list<ParsedBlock>
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
    }

    public function hasPhp(): bool
    {
        return $this->php;
    }
}
