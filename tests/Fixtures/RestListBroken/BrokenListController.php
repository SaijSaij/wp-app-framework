<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestListBroken;

use Saij\WpAppFramework\Rest\Format;
use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\Url;

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
