<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Twig\TemplateFiles;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Finds the texts of the templates of a project: the calls of `__()`, `_x()`, `_n()` and `_nx()` whose texts are
 * literal strings (a text built at run time cannot be translated ahead).
 *
 * @internal
 */
#[NoService]
final class TemplateTexts
{
    /** The arguments of each function: text, plural and context by position. */
    private const array FUNCTIONS = [
        '__' => ['text' => 0],
        '_x' => ['text' => 0, 'context' => 1],
        '_n' => ['text' => 0, 'plural' => 1],
        '_nx' => ['text' => 0, 'plural' => 1, 'context' => 3],
    ];

    /** @var list<string> */
    private array $notices = [];

    /**
     * @param string $directory The project's `src/`.
     * @param string $prefix    Path of the directory relative to the project, for the references (`src`).
     */
    public function collect(string $directory, string $prefix, PotFile $pot): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $environment = new Environment(new ArrayLoader());
        // Only the calls of the translation functions matter; any other function, filter or test parses.
        $environment->registerUndefinedFunctionCallback(static fn(string $name): TwigFunction => new TwigFunction($name));
        $environment->registerUndefinedFilterCallback(static fn(string $name): TwigFilter => new TwigFilter($name));
        $environment->registerUndefinedTestCallback(static fn(string $name): TwigTest => new TwigTest($name));

        foreach (TemplateFiles::names($directory) as $template) {
            try {
                $module = $environment->parse($environment->tokenize(new Source((string) file_get_contents($directory . '/' . $template), $template)));
            } catch (Throwable $exception) {
                $this->notices[] = sprintf('Template %s was not searched for texts: %s', $template, $exception->getMessage());

                continue;
            }
            foreach (self::calls($module) as $call) {
                $this->add($call, $prefix . '/' . $template, $pot);
            }
        }
    }

    /**
     * @return list<string> Templates that do not parse, and calls whose texts are no literal strings.
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
        $name = $node instanceof FunctionExpression ? $node->getAttribute('name') : null;
        if ($node instanceof FunctionExpression && is_string($name) && isset(self::FUNCTIONS[$name])) {
            $calls[] = $node;
        }
        foreach ($node as $child) {
            array_push($calls, ...self::calls($child));
        }

        return $calls;
    }

    private function add(FunctionExpression $call, string $file, PotFile $pot): void
    {
        $name = $call->getAttribute('name');
        if (!is_string($name) || !isset(self::FUNCTIONS[$name])) {
            return;
        }
        $arguments = $call->getNode('arguments');
        $values = [];
        foreach (self::FUNCTIONS[$name] as $role => $position) {
            $node = $arguments->hasNode((string) $position) ? $arguments->getNode((string) $position) : null;
            $value = $node instanceof ConstantExpression ? $node->getAttribute('value') : null;
            if (!is_string($value)) {
                $this->notices[] = sprintf('%s() in %s, line %d: the %s is no literal string and cannot be translated ahead.', $name, $file, $call->getTemplateLine(), $role);

                return;
            }
            $values[$role] = $value;
        }
        $pot->add($values['text'], $values['context'] ?? null, $values['plural'] ?? null, $file . ':' . $call->getTemplateLine());
    }
}
