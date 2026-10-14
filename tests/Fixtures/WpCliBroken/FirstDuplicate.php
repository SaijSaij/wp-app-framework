<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliBroken;

use Saij\WpAppFramework\WpCli\CliCommand;
use Saij\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('dup', 'First.')]
final class FirstDuplicate
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
