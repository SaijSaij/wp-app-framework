<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TableBroken;

use WebCodingCat\WpAppFramework\Table\AbstractRow;
use WebCodingCat\WpAppFramework\Table\Length;
use WebCodingCat\WpAppFramework\Table\PrimaryKey;
use WebCodingCat\WpAppFramework\Table\RenamedFrom;
use WebCodingCat\WpAppFramework\Table\Table;
use WebCodingCat\WpAppFramework\Table\Text;

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
