<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Value\Hidden;
use WebCodingCat\WpAppFramework\Value\Image;
use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\RichText;

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
