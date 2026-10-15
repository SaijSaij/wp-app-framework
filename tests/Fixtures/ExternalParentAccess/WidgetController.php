<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParentAccess;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;

// The build reads the type of a request argument; it must not load the widget to do so.
#[RestController('v1', '/widgets')]
final class WidgetController
{
    /**
     * @return array<string, string>
     */
    #[RestRoute('', HttpMethod::Get, new PublicAccess())]
    public function list(Widget $widget): array
    {
        return [];
    }
}
