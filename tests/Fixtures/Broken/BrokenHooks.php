<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Broken;

use Saij\WpAppFramework\Hook\Hook;

final class BrokenHooks
{
    #[Hook('broken_dependencies')]
    public function dependencies(
        InterfaceDependent $a,
        ExcludedDependent $b,
        ScalarDependent $c,
        OutsideDependent $d,
        CycleA $e,
        InternalDependent $f,
    ): void {}

    // @phpstan-ignore missingType.return (fixture: the missing return type is the error under test)
    #[Hook('broken_return')]
    public function missingReturnType() {}

    #[Hook('broken_static')]
    public static function staticHook(): void {}
}
