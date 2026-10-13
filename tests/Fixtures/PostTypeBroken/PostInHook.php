<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use Saij\WpAppFramework\Hook\Hook;

final class PostInHook
{
    #[Hook('save_post')]
    public function run(int $id, Valid $post): void {}
}
