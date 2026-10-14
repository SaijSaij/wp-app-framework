<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliNamespace;

use Saij\WpAppFramework\WpCli\CliCommand;
use Saij\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('events', 'Events.')]
final class PostCommands
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
