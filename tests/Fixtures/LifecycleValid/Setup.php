<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LifecycleValid;

use Saij\WpAppFramework\Lifecycle\OnActivation;
use Saij\WpAppFramework\Lifecycle\OnDeactivation;
use Saij\WpAppFramework\Lifecycle\OnUninstall;

final readonly class Setup
{
    public function __construct(private Journal $journal) {}

    #[OnActivation]
    public function install(): void
    {
        $this->journal->write('install');
    }

    #[OnDeactivation]
    public function pause(Journal $journal): void
    {
        $journal->write('pause');
    }

    #[OnUninstall]
    public function purge(): void
    {
        $this->journal->write('purge');
    }
}
