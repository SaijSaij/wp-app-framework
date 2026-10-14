<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliBroken;

use Saij\WpAppFramework\WpCli\CliSubcommand;

final class NoCommand
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
