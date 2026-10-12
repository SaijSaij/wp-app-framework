<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockView;

use Saij\WpAppFramework\Block\Support\BlockSupport;

/** A block support with a view script (Reveal.view.js). */
#[BlockSupport('revealOnScroll', label: 'Reveal on scroll')]
final class Reveal
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'reveals' : null;
    }
}
