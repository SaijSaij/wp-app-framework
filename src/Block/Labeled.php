<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

/**
 * Implemented by a backed enum to label its cases in the editor. The build
 * calls label() for every case; without this interface the editor shows the
 * case values.
 */
interface Labeled
{
    public function label(): string;
}
