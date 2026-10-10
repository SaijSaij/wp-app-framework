<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Labeled;

final readonly class LabeledValue implements Labeled
{
    public function __construct(public string $text = '') {}

    public function label(): string
    {
        return $this->text;
    }
}
