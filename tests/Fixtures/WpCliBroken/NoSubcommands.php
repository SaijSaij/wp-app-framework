<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliBroken;

use Saij\WpAppFramework\WpCli\CliCommand;

#[CliCommand('empty', 'Nothing.')]
final class NoSubcommands {}
