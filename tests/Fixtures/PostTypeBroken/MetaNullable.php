<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\PostTypeBroken;

use WebCodingCat\WpAppFramework\Post\PostMeta;

#[PostMeta('nullable', title: 'Meta', postTypes: [Valid::class])]
final class MetaNullable
{
    public function __construct(public ?string $text = null) {}
}
