<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\LifecycleBroken;

use Saij\WpAppFramework\Lifecycle\OnActivation;
use Saij\WpAppFramework\Lifecycle\OnDeactivation;
use Saij\WpAppFramework\Lifecycle\OnUninstall;

final class Methods
{
    #[OnActivation]
    protected function hidden(): void {}

    #[OnActivation]
    public static function statically(): void {}

    #[OnDeactivation]
    public function returns(): int
    {
        return 1;
    }

    #[OnDeactivation]
    public function argument(int $count): void {}

    #[OnActivation]
    public function variadic(Helper ...$helpers): void {}

    #[OnUninstall]
    public function uninstall(): void {}
}
