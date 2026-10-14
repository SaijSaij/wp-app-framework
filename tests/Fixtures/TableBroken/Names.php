<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;

/**
 * An invalid name, a name too long and a name another class uses as earlier name.
 */
#[Table('Bad-Name', renamedFrom: ['a_name_that_is_far_too_long_for_the_prefix_of_wordpress', 'valid'])]
final class Names extends AbstractRow
{
    public function __construct(#[PrimaryKey] protected ?int $id = null) {}
}
