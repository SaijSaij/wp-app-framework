<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestBroken;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Rest\Permission\Permission;

#[NoService]
final class OwnPermission implements Permission
{
}
