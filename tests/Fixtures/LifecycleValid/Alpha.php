<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LifecycleValid;

use WebCodingCat\WpAppFramework\Lifecycle\OnActivation;
use WebCodingCat\WpAppFramework\Lifecycle\OnDeactivation;

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
