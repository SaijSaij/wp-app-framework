<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

#[Table('properties')]
final class Properties extends AbstractRow
{
    private int $cache = 0;

    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        public string $title = '',
        string $plain = '',
    ) {
        $this->cache = strlen($plain);
    }

    public function getCache(): int
    {
        return $this->cache;
    }
}
