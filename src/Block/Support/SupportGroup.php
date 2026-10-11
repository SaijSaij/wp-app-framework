<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Support;

/**
 * Where the inspector shows the control of a block support. Neither is shown
 * in contentOnly mode.
 */
enum SupportGroup: string
{
    /** The "Styles" tab. */
    case Styles = 'styles';

    /** The "Settings" panel of the block, next to its other controls. */
    case Settings = 'settings';
}
