<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditor;

use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class Caption
{
    public function __construct(#[PlainText] public string $text = '') {}
}
