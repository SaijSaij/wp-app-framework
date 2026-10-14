<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliBroken;

use Saij\WpAppFramework\WpCli\CliCommand;
use Saij\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('Bad_Name', 'Bad name.')]
final class BadName
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
