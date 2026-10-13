<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Lock of the inner blocks of a container (`#[InnerBlocks(templateLock)]`).
 */
enum TemplateLock: string
{
    /** Inner blocks cannot be inserted, moved or removed. */
    case All = 'all';
    /** Inner blocks can be moved, but not inserted or removed. */
    case Insert = 'insert';
    /** Only the content of the inner blocks can be edited. */
    case ContentOnly = 'contentOnly';
    /** Unlocks the inner blocks inside a locked parent (`templateLock: false` in WordPress). */
    case Unlocked = 'unlocked';
}
