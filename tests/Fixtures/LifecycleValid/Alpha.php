<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LifecycleValid;

use Saij\WpAppFramework\Lifecycle\OnActivation;
use Saij\WpAppFramework\Lifecycle\OnDeactivation;

/**
 * Runs before Setup: the methods of a phase run in the order of class and method name.
 */
final readonly class Alpha
{
    public function __construct(private Journal $journal) {}

    #[OnActivation]
    #[OnDeactivation]
    public function both(): void
    {
        $this->journal->write('alpha');
    }
}
