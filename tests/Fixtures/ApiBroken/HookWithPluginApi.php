<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiBroken;

use Saij\WpAppFramework\Api\PluginApi;
use Saij\WpAppFramework\Hook\Hook;
use Saij\WpAppFramework\Tests\Fixtures\OtherPlugin\NotesApi;

final class HookWithPluginApi
{
    #[Hook('init')]
    public function run(
        #[PluginApi('other-plugin')]
        ?NotesApi $notes = null,
    ): void {}
}
