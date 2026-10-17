<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class CardAttributes
{
    public function __construct(#[PlainText(excerpt: true)] public string $title = '') {}
}
