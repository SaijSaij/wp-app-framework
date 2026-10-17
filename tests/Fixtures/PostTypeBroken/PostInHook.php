<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Hook\Hook;

final class PostInHook
{
    #[Hook('save_post')]
    public function run(int $id, Valid $post): void {}
}
