<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiBroken;

use WebCodingCat\WpAppFramework\Api\PluginApi;
use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Tests\Fixtures\OtherPlugin\NotesApi;

final class BrokenConsumer
{
    // @phpstan-ignore constructor.unusedParameter, constructor.unusedParameter, constructor.unusedParameter, constructor.unusedParameter (Intentionally invalid #[PluginApi] parameters; only the build reads them.)
    public function __construct(
        #[PluginApi('other-plugin')]
        NotesApi $notNullable,
        #[PluginApi('Other Plugin')]
        ?NotesApi $badSlug = null,
        #[PluginApi('other-plugin')]
        ?int $builtin = null,
        #[PluginApi('other-plugin')]
        ?LeakingApi $own = null,
    ) {}

    #[Hook('init')]
    public function run(): void {}
}
