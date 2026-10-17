<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiBroken;

use WebCodingCat\WpAppFramework\Api\PluginApi;
use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Tests\Fixtures\OtherPlugin\NotesApi;

final class HookWithPluginApi
{
    #[Hook('init')]
    public function run(
        #[PluginApi('other-plugin')]
        ?NotesApi $notes = null,
    ): void {}
}
