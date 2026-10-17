<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Asset;

use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Twig\TemplateFiles;

/**
 * Finds the image variants the templates of a project ask for during the
 * build: every call of `asset_image()` whose attributes have `widths` or
 * `formats`. Both must be written as literals in the call, and so must the
 * name of the image, because the build creates the variants before any
 * template renders.
 *
 * @internal
 *
 * @phpstan-import-type AssetEntry from AssetCollector
 * @phpstan-type VariantSet array{widths: list<int>, formats: list<string>}
 */
#[NoService]
final class ImageVariantCollector
{
    public const string FUNCTION = 'asset_image';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $notices = [];

    /**
     * @param string|null               $templateDirectory The project's `src/`; null without one.
     * @param array<string, AssetEntry> $assets            The static assets of the project.
     * @return array<string, AssetEntry> The assets, with the variants the templates ask for.
     */
    public function collect(?string $templateDirectory, array $assets): array
    {
        if ($templateDirectory === null || !is_dir($templateDirectory)) {
            return $assets;
        }

        $environment = new Environment(new ArrayLoader());
        // Only the calls of asset_image() matter; any other function, filter or test parses.
        $environment->registerUndefinedFunctionCallback(static fn(string $name): TwigFunction => new TwigFunction($name));
        $environment->registerUndefinedFilterCallback(static fn(string $name): TwigFilter => new TwigFilter($name));
        $environment->registerUndefinedTestCallback(static fn(string $name): TwigTest => new TwigTest($name));

        /** @var array<string, array{widths: array<int, true>, formats: array<string, true>}> $requested */
        $requested = [];
        foreach (TemplateFiles::names($templateDirectory) as $template) {
            try {
                $module = $environment->parse($environment->tokenize(new Source((string) file_get_contents($templateDirectory . '/' . $template), $template)));
            } catch (Throwable $exception) {
                // The template itself fails when it renders; the build only looks for image variants.
                $this->notices[] = sprintf('Template %s was not searched for image variants: %s', $template, $exception->getMessage());

                continue;
            }
            foreach (self::calls($module) as $call) {
                $this->request($call, $template, $assets, $requested);
            }
        }

        foreach ($requested as $name => ['widths' => $widths, 'formats' => $formats]) {
            if (!isset($assets[$name])) {
                continue;
            }
            $original = $assets[$name]['width'];
            $kept = array_values(array_filter(array_keys($widths), static fn(int $width): bool => $original === null || $width <= $original));
            sort($kept);
            $left = array_diff(array_keys($widths), $kept);
            if ($left !== []) {
                $this->notices[] = sprintf('Image %s: the widths %s exceed its width of %d pixels and are left out.', $name, implode(', ', $left), (int) $original);
            }
            $formatList = array_keys($formats);
            sort($formatList);
            $assets[$name]['variants'] = ['widths' => $kept, 'formats' => $formatList];
        }

        return $assets;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<string> Remarks for the output of the build: left-out widths, templates that do not parse.
     */
    public function notices(): array
    {
        return $this->notices;
    }

    /**
     * @return list<FunctionExpression>
     */
    private static function calls(Node $node): array
    {
        $calls = [];
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === self::FUNCTION) {
            $calls[] = $node;
        }
        foreach ($node as $child) {
            array_push($calls, ...self::calls($child));
        }

        return $calls;
    }

    /**
     * @param array<string, AssetEntry>                                                    $assets
     * @param array<string, array{widths: array<int, true>, formats: array<string, true>}> $requested
     */
    private function request(FunctionExpression $call, string $template, array $assets, array &$requested): void
    {
        $arguments = $call->getNode('arguments');
        $argument = static fn(int $position, string $name): ?Node => $arguments->hasNode((string) $position)
            ? $arguments->getNode((string) $position)
            : ($arguments->hasNode($name) ? $arguments->getNode($name) : null);
        $where = sprintf('%s() in %s, line %d', self::FUNCTION, $template, $call->getTemplateLine());

        $attributes = $argument(2, 'attributes');
        if (!$attributes instanceof ArrayExpression) {
            // Attributes that are no literal map cannot ask for variants here; asking for them there fails at run time.
            return;
        }
        /** @var array<string, Node> $options */
        $options = [];
        foreach (self::pairs($attributes) as ['key' => $key, 'value' => $value]) {
            if ($key instanceof ConstantExpression && in_array($key->getAttribute('value'), ['widths', 'formats', 'sizes'], true)) {
                $options[(string) $key->getAttribute('value')] = $value;
            }
        }
        if (!isset($options['widths']) && !isset($options['formats'])) {
            return;
        }

        $nameNode = $argument(0, 'name');
        $name = $nameNode instanceof ConstantExpression ? $nameNode->getAttribute('value') : null;
        if (!is_string($name)) {
            $this->errors[] = sprintf('%s asks for image variants, so it needs the name of the image as a literal string.', $where);

            return;
        }
        $relative = str_starts_with($name, '@theme/') || str_starts_with($name, '@plugin/') ? substr($name, (int) strpos($name, '/') + 1) : $name;
        if (!isset($assets[$relative])) {
            $this->errors[] = sprintf('%s asks for variants of %s, which is no static asset of the project (assets/%s).', $where, $name, $relative);

            return;
        }
        $source = ImageVariants::sourceFormat($relative);
        if ($source === null) {
            $this->errors[] = sprintf('%s asks for variants of %s; only %s images have variants.', $where, $name, implode(', ', array_keys(ImageVariants::SOURCES)));

            return;
        }
        $widths = self::literals($options['widths'] ?? null);
        if ($widths === null || $widths === [] || array_filter($widths, static fn(mixed $width): bool => !is_int($width) || $width < 1) !== []) {
            $this->errors[] = sprintf('%s needs "widths" as a literal list of positive integers, e.g. [640, 1280].', $where);

            return;
        }
        if (!isset($options['sizes'])) {
            $this->errors[] = sprintf('%s has "widths" without "sizes"; the browser needs both to choose a width.', $where);
        }
        $formats = isset($options['formats']) ? self::literals($options['formats']) : [];
        if ($formats === null || array_filter($formats, static fn(mixed $format): bool => !is_string($format) || !isset(ImageVariants::FORMATS[$format])) !== []) {
            $this->errors[] = sprintf('%s needs "formats" as a literal list of %s.', $where, implode(', ', array_keys(ImageVariants::FORMATS)));

            return;
        }

        $entry = $requested[$relative] ?? ['widths' => [], 'formats' => []];
        foreach ($widths as $width) {
            /** @var int $width Checked above. */
            $entry['widths'][$width] = true;
        }
        foreach ($formats as $format) {
            /** @var string $format Checked above. */
            $entry['formats'][$format] = true;
        }
        $requested[$relative] = $entry;
    }

    /**
     * @return list<array{key: Node, value: Node}>
     */
    private static function pairs(ArrayExpression $array): array
    {
        /** @var list<array{key: Node, value: Node}> Keys and values are expression nodes. */
        return $array->getKeyValuePairs();
    }

    /**
     * The values of a literal list; null for anything else.
     *
     * @return list<mixed>|null
     */
    private static function literals(?Node $node): ?array
    {
        if (!$node instanceof ArrayExpression) {
            return null;
        }
        $values = [];
        foreach (self::pairs($node) as ['value' => $value]) {
            if (!$value instanceof ConstantExpression) {
                return null;
            }
            $values[] = $value->getAttribute('value');
        }

        return $values;
    }
}
