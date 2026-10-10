<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Hidden;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;

final readonly class BrokenEditorAttributes
{
    public function __construct(
        #[Image]
        public string $imageOnString = '',
        #[Image]
        public int $imageNotNullable = 0,
        #[PlainText]
        #[Image]
        public ?int $plainAndImage = null,
        #[Hidden]
        #[RichText]
        public string $hiddenRich = '',
        #[Label('  ')]
        public string $emptyLabel = '',
        #[RichText(allowedFormats: ['core/bold', ''])]
        public string $badFormats = '',
        public EmptyLabel $emptyCaseLabel = EmptyLabel::A,
        public LabeledValue $labeledValue = new LabeledValue(),
        #[Hidden]
        #[PlainText(inspector: true)]
        public string $hiddenInspector = '',
    ) {}
}
