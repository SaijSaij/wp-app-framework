<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TwigBroken;

use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;

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

    #[TemplateFunction('no_return_type')]
    public function noReturnType()
    {
        return '';
    }

    #[TemplateFunction('void_function')]
    public function voidFunction(): void
    {
    }

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
