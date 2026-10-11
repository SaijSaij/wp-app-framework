<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceBroken;

final readonly class Item
{
    public function __construct(public string $label = '') {}
}
