<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditor;

use Saij\WpAppFramework\Value\PlainText;

final readonly class Caption
{
    public function __construct(#[PlainText] public string $text = '') {}
}
