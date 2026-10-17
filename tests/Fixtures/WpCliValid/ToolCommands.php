<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid;

use WebCodingCat\WpAppFramework\WpCli\CliCommand;
use WebCodingCat\WpAppFramework\WpCli\CliOutput;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;

#[CliCommand('tools', 'Tools.')]
final class ToolCommands
{
    #[CliSubcommand('ping', 'Answer with pong.')]
    public function ping(CliOutput $output): void
    {
        $output->log('pong');
        $output->table([['name' => 'a', 'count' => 1]], ['name', 'count']);
    }
}
