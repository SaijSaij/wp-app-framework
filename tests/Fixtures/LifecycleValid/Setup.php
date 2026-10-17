<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\LifecycleValid;

use WebCodingCat\WpAppFramework\Lifecycle\OnActivation;
use WebCodingCat\WpAppFramework\Lifecycle\OnDeactivation;
use WebCodingCat\WpAppFramework\Lifecycle\OnUninstall;

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
