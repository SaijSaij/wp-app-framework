<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RestListBroken;

use WebCodingCat\WpAppFramework\Rest\Format;
use WebCodingCat\WpAppFramework\Rest\HttpMethod;
use WebCodingCat\WpAppFramework\Rest\Param;
use WebCodingCat\WpAppFramework\Rest\Permission\Callback;
use WebCodingCat\WpAppFramework\Rest\Permission\PublicAccess;
use WebCodingCat\WpAppFramework\Rest\RestController;
use WebCodingCat\WpAppFramework\Rest\RestRoute;
use WebCodingCat\WpAppFramework\Value\Color;
use WebCodingCat\WpAppFramework\Value\DateTime;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\Url;

#[RestController('v1')]
final class BrokenListController
{
    #[RestRoute('/single', HttpMethod::Post, new PublicAccess())]
    public function single(Item $item): bool
    {
        return true;
    }

    /**
     * @param list<Kind> $kinds
     */
    #[RestRoute('/keyword', HttpMethod::Get, new PublicAccess())]
    public function keyword(#[Param(minLength: 1)] #[ListOf(Kind::class)] array $kinds = []): bool
    {
        return true;
    }

    #[RestRoute('/format', HttpMethod::Get, new PublicAccess())]
    public function format(#[Url] #[Param(format: Format::Uri)] string $link = ''): bool
    {
        return true;
    }

    /**
     * @param list<Kind> $kinds
     */
    #[RestRoute('/appender', HttpMethod::Get, new PublicAccess())]
    public function appender(#[ListOf(Kind::class, appender: true)] array $kinds = []): bool
    {
        return true;
    }

    #[RestRoute('/pattern', HttpMethod::Get, new PublicAccess())]
    public function pattern(#[Color] #[Param(pattern: '^#')] string $color = ''): bool
    {
        return true;
    }

    #[RestRoute('/date-pattern', HttpMethod::Get, new PublicAccess())]
    public function datePattern(#[DateTime] #[Param(pattern: '^2')] string $date = ''): bool
    {
        return true;
    }

    /**
     * @param list<Kind> $kinds
     */
    #[RestRoute('/callback', HttpMethod::Get, new Callback(self::allowed(...)))]
    public function callback(#[ListOf(Kind::class)] array $kinds = []): bool
    {
        return true;
    }

    /**
     * @param list<Item> $kinds
     */
    public static function allowed(#[ListOf(Item::class)] array $kinds = []): bool
    {
        return true;
    }
}
