<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

/**
 * Implemented by a backed enum to label its cases in the editor. The build
 * calls label() for every case; without this interface the editor shows the
 * case values.
 *
 * @api Values and exceptions
 */
interface Labeled
{
    /**
     * The label of the case in the editor. The build calls it, so it must not depend on run-time state.
     */
    public function label(): string;
}
