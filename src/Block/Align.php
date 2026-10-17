<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

/**
 * Alignments for the `align` block support.
 *
 * @api Enums
 */
enum Align: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    case Wide = 'wide';
    case Full = 'full';
}
