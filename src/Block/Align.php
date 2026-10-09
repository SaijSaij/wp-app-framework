<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Alignments for the `align` block support.
 */
enum Align: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    case Wide = 'wide';
    case Full = 'full';
}
