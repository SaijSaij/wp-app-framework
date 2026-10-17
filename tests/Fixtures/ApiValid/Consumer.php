<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid;

use WebCodingCat\WpAppFramework\Api\PluginApi;
use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Tests\Fixtures\OtherPlugin\NotesApi;

/**
 * Uses the public API of another plugin.
 */
final class Consumer
{
    public function __construct(
        private readonly NoteStore $store,
        #[PluginApi('other-plugin')]
        private readonly ?NotesApi $notes = null,
    ) {}

    #[Hook('init')]
    public function run(): void {}

    public function count(): int
    {
        return $this->notes === null ? 0 : count($this->store->all());
    }
}
