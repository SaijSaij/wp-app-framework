<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliNamespace;

use WebCodingCat\WpAppFramework\WpCli\CliCommand;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('events', 'Events.')]
final class PostCommands
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
