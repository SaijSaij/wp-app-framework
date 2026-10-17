<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestValid;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class Api
{
    public const string NAMESPACE = 'v1';
}
