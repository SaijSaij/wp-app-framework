<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TwigBroken;

use WebCodingCat\WpAppFramework\Twig\TemplateFilter;
use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

final class BrokenCallables
{
    #[TemplateFunction('bad-name')]
    public function invalidName(): string
    {
        return '';
    }

    #[TemplateFunction('range')]
    public function builtInFunction(): string
    {
        return '';
    }

    #[TemplateFilter('upper')]
    public function builtInFilter(string $value): string
    {
        return $value;
    }

    #[TemplateFunction('twice')]
    public function first(): string
    {
        return '';
    }

    #[TemplateFunction('twice')]
    public function second(): string
    {
        return '';
    }

    #[TemplateFunction('static_function')]
    public static function staticFunction(): string
    {
        return '';
    }

    // @phpstan-ignore missingType.return (fixture: the missing return type is the error under test)
    #[TemplateFunction('no_return_type')]
    public function noReturnType()
    {
        return '';
    }

    #[TemplateFunction('void_function')]
    public function voidFunction(): void {}

    // @phpstan-ignore return.unusedType (fixture: the nullable return type is the error under test)
    #[TemplateFunction('nullable_html', html: true)]
    public function nullableHtml(): ?string
    {
        return null;
    }

    #[TemplateFilter('no_value')]
    public function filterWithoutValue(): string
    {
        return '';
    }

    #[TemplateFunction('variadic')]
    public function variadic(string ...$values): string
    {
        return implode('', $values);
    }

    #[TemplateFunction('with_service')]
    public function withService(Helper $helper): string
    {
        return '';
    }

    #[TemplateFilter('same_name_as_function')]
    public function sameNameFilter(string $value): string
    {
        return $value;
    }

    #[TemplateFunction('same_name_as_function')]
    public function sameNameFunction(): string
    {
        return '';
    }
}
