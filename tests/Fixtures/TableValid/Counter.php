<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableValid;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

/**
 * A second table with protected columns and a field class written by hand.
 */
#[Table('counter')]
final class Counter extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected string $name = '',
        protected int $count = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
