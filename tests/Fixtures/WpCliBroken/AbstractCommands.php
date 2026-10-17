<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliBroken;

use WebCodingCat\WpAppFramework\WpCli\CliCommand;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('abstract', 'Abstract.')]
abstract class AbstractCommands
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
