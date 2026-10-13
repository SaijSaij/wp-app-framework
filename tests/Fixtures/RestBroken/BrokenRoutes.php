<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RestBroken;

use Saij\WpAppFramework\Rest\HttpMethod;
use Saij\WpAppFramework\Rest\Param;
use Saij\WpAppFramework\Rest\Permission\Callback;
use Saij\WpAppFramework\Rest\Permission\Capability;
use Saij\WpAppFramework\Rest\Permission\PublicAccess;
use Saij\WpAppFramework\Rest\RestController;
use Saij\WpAppFramework\Rest\RestRoute;

#[RestController('v1')]
final class BrokenRoutes
{
    #[RestRoute('/empty-methods', [], new PublicAccess())]
    public function emptyMethods(): bool
    {
        return true;
    }

    #[RestRoute('/anonymous', HttpMethod::Get, new Callback(static function (): bool {
        return true;
    }))]
    public function anonymous(): bool
    {
        return true;
    }

    #[RestRoute('/callback-return', HttpMethod::Get, new Callback(self::notBool(...)))]
    public function callbackReturn(): bool
    {
        return true;
    }

    #[RestRoute('/callback-argument', HttpMethod::Get, new Callback(self::unknownArgument(...)))]
    public function callbackArgument(string $other = ''): bool
    {
        return true;
    }

    #[RestRoute('/own-permission', HttpMethod::Get, new OwnPermission())]
    public function ownPermission(): bool
    {
        return true;
    }

    #[RestRoute('/empty-capability', HttpMethod::Get, new Capability(''))]
    public function emptyCapability(): bool
    {
        return true;
    }

    #[RestRoute('/placeholder/{missing}', HttpMethod::Get, new PublicAccess())]
    public function placeholderWithoutParameter(): bool
    {
        return true;
    }

    #[RestRoute('/optional/{id}', HttpMethod::Get, new PublicAccess())]
    public function optionalPathParameter(int $id = 1): bool
    {
        return true;
    }

    #[RestRoute('/float/{ratio}', HttpMethod::Get, new PublicAccess())]
    public function floatPathParameter(float $ratio): bool
    {
        return true;
    }

    /**
     * @param list<int> $list
     */
    #[RestRoute('/array', HttpMethod::Get, new PublicAccess())]
    public function arrayParameter(array $list): bool
    {
        return true;
    }

    // @phpstan-ignore missingType.parameter (fixture: the missing parameter type is the error under test)
    #[RestRoute('/untyped', HttpMethod::Get, new PublicAccess())]
    public function untypedParameter($value): bool
    {
        return true;
    }

    #[RestRoute('/nullable', HttpMethod::Get, new PublicAccess())]
    public function nullableWithoutDefault(?int $count): bool
    {
        return true;
    }

    #[RestRoute('/param-mismatch', HttpMethod::Get, new PublicAccess())]
    public function paramMismatch(#[Param(minimum: 1)] string $name): bool
    {
        return true;
    }

    #[RestRoute('/bad-pattern', HttpMethod::Get, new PublicAccess())]
    public function badPattern(#[Param(pattern: '(')] string $name): bool
    {
        return true;
    }

    #[RestRoute('/invalid path', HttpMethod::Get, new PublicAccess())]
    public function invalidCharacters(): bool
    {
        return true;
    }

    #[RestRoute('no-slash', HttpMethod::Get, new PublicAccess())]
    public function noLeadingSlash(): bool
    {
        return true;
    }

    #[RestRoute('/duplicate', HttpMethod::Get, new PublicAccess())]
    public function first(): bool
    {
        return true;
    }

    #[RestRoute('/duplicate', [HttpMethod::Post, HttpMethod::Get], new PublicAccess())]
    public function second(): bool
    {
        return true;
    }

    #[RestRoute('/static', HttpMethod::Get, new PublicAccess())]
    public static function staticRoute(): bool
    {
        return true;
    }

    public static function notBool(): int
    {
        return 1;
    }

    public static function unknownArgument(int $other): bool
    {
        return true;
    }
}
