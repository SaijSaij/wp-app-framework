<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * The block categories WordPress registers by default.
 */
enum BlockCategory: string
{
    case Text = 'text';
    case Media = 'media';
    case Design = 'design';
    case Widgets = 'widgets';
    case Theme = 'theme';
    case Embed = 'embed';
}
