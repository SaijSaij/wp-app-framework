<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestListBroken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final readonly class Item
{
    public function __construct(public string $title = '') {}
}
