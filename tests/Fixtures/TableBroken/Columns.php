<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\Length;
use Saij\WpAppFramework\Table\PrimaryKey;
use Saij\WpAppFramework\Table\RenamedFrom;
use Saij\WpAppFramework\Table\Table;
use Saij\WpAppFramework\Table\Text;

/**
 * Column names, lengths and earlier names.
 */
#[Table('columns')]
final class Columns extends AbstractRow
{
    public function __construct(
        #[PrimaryKey]
        protected ?int $id = null,
        protected int $postId = 0,
        protected int $post_id = 0,
        protected string $aParameterNameThatIsMuchTooLongForAColumnOfMariaDbAndMySqlTables = '',
        #[Length(20)]
        #[Text]
        protected string $both = '',
        #[Length(0)]
        protected string $empty = '',
        #[Length(10)]
        protected int $number = 0,
        #[Length(3)]
        protected string $short = 'longer',
        #[RenamedFrom('')]
        #[RenamedFrom('renamed')]
        protected string $renamed = '',
        #[RenamedFrom('name')]
        #[RenamedFrom('name')]
        protected string $title = '',
        #[RenamedFrom('postId')]
        protected string $taken = '',
        #[RenamedFrom('caption')]
        protected string $subtitle = '',
        #[RenamedFrom('caption')]
        protected string $again = '',
    ) {}
}
