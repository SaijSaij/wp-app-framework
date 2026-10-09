<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestValid;

use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final class Api
{
    public const string NAMESPACE = 'fixture/v1';
}
