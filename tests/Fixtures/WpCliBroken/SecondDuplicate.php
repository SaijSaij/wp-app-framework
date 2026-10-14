<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliBroken;

use Saij\WpAppFramework\WpCli\CliCommand;
use Saij\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('dup', 'Second.')]
final class SecondDuplicate
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
