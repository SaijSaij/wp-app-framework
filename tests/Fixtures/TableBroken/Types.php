<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use DateTime;
use DateTimeInterface;
use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;
use WebCodingCat\WpAppFramework\Value\PlainText;

/**
 * Types that are no columns, and a marker of another kind.
 */
#[Table('types')]
final class Types extends AbstractRow
{
    /**
     * @param array<string> $list
     */
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected array $list = [],
        protected ?Valid $other = null,
        protected int|string $union = 0,
        protected mixed $anything = null,
        protected ?DateTime $time = null,
        protected ?DateTimeInterface $moment = null,
        #[PlainText]
        protected string $marked = '',
    ) {}
}
