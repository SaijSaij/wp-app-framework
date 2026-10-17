<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\Indexed;
use WebCodingCat\WpAppFramework\Table\Length;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\Table;
use WebCodingCat\WpAppFramework\Table\Text;

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
