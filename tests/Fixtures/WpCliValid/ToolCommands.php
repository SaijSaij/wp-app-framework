<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\WpCliValid;

use Saij\WpAppFramework\WpCli\CliCommand;
use Saij\WpAppFramework\WpCli\CliOutput;
use Saij\WpAppFramework\WpCli\CliSubcommand;

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
