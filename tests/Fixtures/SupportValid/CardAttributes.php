<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

use Saij\WpAppFramework\Block\PlainText;

final readonly class CardAttributes
{
    public function __construct(#[PlainText(excerpt: true)] public string $title = '') {}
}
