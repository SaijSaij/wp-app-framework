<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('valid')]
final class Valid extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected string $name = '',
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
