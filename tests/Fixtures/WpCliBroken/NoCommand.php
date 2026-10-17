<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliBroken;

use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;

final class NoCommand
{
    #[CliSubcommand('run', 'Run.')]
    public function run(): void {}
}
