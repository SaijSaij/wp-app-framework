<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\TwigValid;

use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;

final class TextHelpers
{
    private static int $instances = 0;

    public function __construct(private readonly Prefix $prefix)
    {
        self::$instances++;
    }

    public static function getInstances(): int
    {
        return self::$instances;
    }

    #[TemplateFunction('greet')]
    public function greet(string $name): string
    {
        return $this->prefix->get() . ' <' . $name . '>';
    }

    #[TemplateFunction('badge', html: true)]
    public function badge(string $label): string
    {
        return '<b>' . $label . '</b>';
    }

    #[TemplateFilter('wrap')]
    public function wrap(string $value, string $tag = 'i'): string
    {
        return '<' . $tag . '>' . $value . '</' . $tag . '>';
    }

    #[TemplateFilter('wrap_html', html: true)]
    public function wrapHtml(string $value): string
    {
        return '<i>' . $value . '</i>';
    }

    /**
     * @param list<mixed> $items
     */
    #[TemplateFunction('count_items')]
    public function countItems(array $items): int
    {
        return count($items);
    }
}
