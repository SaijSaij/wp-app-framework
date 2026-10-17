<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

/**
 * The element at the end of a container's inner blocks for adding another
 * block (`#[InnerBlocks(appender)]`).
 *
 * @api Enums
 */
enum Appender: string
{
    /** WordPress decides: an empty paragraph to type into, or a "+" button if the default block is not allowed. */
    case Default = 'default';
    /** Always the "+" button (InnerBlocks.ButtonBlockAppender), as in core/buttons. */
    case Button = 'button';
    /** No appender; blocks are added through the toolbar, the inserter or the list view. */
    case None = 'none';
}
