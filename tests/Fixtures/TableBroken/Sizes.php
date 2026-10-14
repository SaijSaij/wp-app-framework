<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Indexed;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

/**
 * An index of more than 3072 bytes and a row of more than 65535.
 */
#[Table('sizes')]
final class Sizes extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        #[Indexed(group: 'wide', position: 1)]
        protected string $a = '',
        #[Indexed(group: 'wide', position: 2)]
        protected string $b = '',
        #[Indexed(group: 'wide', position: 3)]
        protected string $c = '',
        #[Indexed(group: 'wide', position: 4)]
        protected string $d = '',
        #[Indexed(group: 'wide', position: 5)]
        protected string $e = '',
    ) {}
}
