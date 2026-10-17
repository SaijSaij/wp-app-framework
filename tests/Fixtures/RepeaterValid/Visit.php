<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterValid;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('visit')]
final class Visit extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        private ?int $id = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
