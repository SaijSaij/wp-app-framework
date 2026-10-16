<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterValid;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

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
