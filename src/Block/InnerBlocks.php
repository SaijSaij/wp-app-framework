<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * How the editor handles the inner blocks of a container block (a block whose
 * render() accepts string $content). Without this attribute all blocks are
 * allowed and WordPress' defaults apply.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class InnerBlocks
{
    /**
     * @param list<class-string|CoreBlock|string>|null $allowed      Blocks that may be inserted: block classes of the
     *                                                              same theme or plugin, core blocks, other blocks by
     *                                                              full name ("acme/slider"); null allows all.
     * @param list<TemplateBlock>|null                 $template     Inner blocks of a newly inserted block.
     * @param TemplateLock|null                        $templateLock Null inherits the lock of the parent.
     * @param TemplateBlock|null                       $defaultBlock Block the appender inserts (with $directInsert) and
     *                                                              the editor suggests.
     * @param bool                                     $directInsert The appender inserts $defaultBlock without asking.
     * @param list<class-string|CoreBlock|string>      $prioritized  Blocks offered first in the quick inserter of the
     *                                                              container and by the slash command.
     */
    public function __construct(
        public ?array $allowed = null,
        public ?array $template = null,
        public ?TemplateLock $templateLock = null,
        public ?Orientation $orientation = null,
        public ?TemplateBlock $defaultBlock = null,
        public bool $directInsert = false,
        public array $prioritized = [],
        public Appender $appender = Appender::Default,
    ) {}
}
