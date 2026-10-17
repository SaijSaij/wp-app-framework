<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Value\Labeled;

final readonly class LabeledValue implements Labeled
{
    public function __construct(public string $text = '') {}

    public function label(): string
    {
        return $this->text;
    }
}
