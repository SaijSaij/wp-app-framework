<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParentAccess;

use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;

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
