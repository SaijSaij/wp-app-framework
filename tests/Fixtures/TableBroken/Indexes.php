<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Indexed;
use Saij\WpAppFramework\Table\Length;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\Table;
use Saij\WpAppFramework\Table\Text;

/**
 * Indexes that cannot be created.
 */
#[Table('indexes')]
final class Indexes extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        #[Indexed]
        protected ?int $id = null,
        #[Text]
        #[Indexed]
        protected string $text = '',
        #[Length(192)]
        #[Indexed]
        protected string $wide = '',
        #[Indexed(position: 1)]
        #[Indexed(group: 'nowhere')]
        #[Indexed(group: 'Bad-Group', position: 1)]
        #[Indexed(group: 'primary', position: 1)]
        protected int $a = 0,
        #[Indexed]
        #[Indexed]
        #[Indexed(group: 'gap', position: 1)]
        #[Indexed(group: 'mixed', position: 1, unique: true)]
        #[Indexed(group: 'b', position: 1)]
        protected int $b = 0,
        #[Indexed(group: 'gap', position: 3)]
        #[Indexed(group: 'mixed', position: 2)]
        #[Indexed(group: 'same', position: 1)]
        #[Indexed(group: 'same', position: 2)]
        protected int $c = 0,
        #[Indexed]
        #[Indexed(group: 'only_d', position: 1)]
        #[Indexed(group: 'a_group_name_that_is_longer_than_the_sixty_four_characters_of_mariadb', position: 1)]
        protected int $d = 0,
    ) {}
}
