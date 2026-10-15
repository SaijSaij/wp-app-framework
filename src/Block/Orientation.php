<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Direction in which the inner blocks of a container are laid out; the editor
 * uses it for the movers and the drop indicator (`#[InnerBlocks(orientation)]`).
 *
 * @api Enums
 */
enum Orientation: string
{
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';
}
