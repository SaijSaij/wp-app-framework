<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliBroken;

use WebCodingCat\WpAppFramework\WpCli\CliCommand;

#[CliCommand('empty', 'Nothing.')]
final class NoSubcommands {}
