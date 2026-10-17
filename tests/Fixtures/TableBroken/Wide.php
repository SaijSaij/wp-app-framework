<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\Length;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;

#[Table('wide')]
final class Wide extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        #[Length(16382)]
        protected string $text = '',
    ) {}
}
