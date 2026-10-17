<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestBroken;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Rest\Permission\Permission;

#[NoService]
final class OwnPermission implements Permission {}
