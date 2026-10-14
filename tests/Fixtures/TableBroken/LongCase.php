<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TableBroken;

enum LongCase: string
{
    case Long = 'a value of more than 191 characters, which does not fit into the column of an enum: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
}
