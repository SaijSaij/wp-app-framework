<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\WpCliValid;

enum ImportMode: string
{
    case Merge = 'merge';
    case Replace = 'replace';
}
