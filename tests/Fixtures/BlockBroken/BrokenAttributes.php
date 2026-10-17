<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use DateTimeImmutable;
use WebCodingCat\WpAppFramework\Value\Content;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\RichText;
use WebCodingCat\WpAppFramework\Value\Url;

final readonly class BrokenAttributes
{
    /**
     * @param array<mixed>                 $plainArray
     * @param list<Unit>                   $unitList
     * @param list<DateTimeImmutable>      $outside
     */
    public function __construct(
        public string $noDefault,
        public string $className = '',
        #[PlainText]
        public int $plainInt = 0,
        #[PlainText]
        #[RichText]
        public string $both = '',
        #[RichText]
        #[Content]
        public string $richContent = '',
        #[Url]
        public int $urlInt = 0,
        #[Url]
        #[PlainText]
        public string $urlText = '',
        public array $plainArray = [],
        #[ListOf(Mode::class)]
        public string $listOnString = '',
        #[ListOf(Unit::class)]
        public array $unitList = [],
        #[ListOf(DateTimeImmutable::class)]
        public array $outside = [],
        public int|string $union = 0,
        public mixed $anything = null,
        public Mode $mode = Mode::On,
    ) {}
}
