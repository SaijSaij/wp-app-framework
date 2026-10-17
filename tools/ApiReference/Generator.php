<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tools\ApiReference;

use Attribute;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use SplFileInfo;
use WebCodingCat\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use WebCodingCat\WpAppFramework\Manifest\Discovery\Source;
use WebCodingCat\WpAppFramework\Twig\TemplateFilter;
use WebCodingCat\WpAppFramework\Twig\TemplateFunction;

/**
 * Generates the PHP and Twig part of the API reference in `docs/content/api/`
 * from the code: one page per class marked `@api <group>` and one per Twig
 * function of the framework, plus the sidebar of these pages.
 *
 * Everything a page shows comes from signatures and doc comments. A class,
 * public method, constant or parameter without description, a type naming an
 * internal class of the framework and a `@see` without target are errors: the
 * documentation build fails instead of publishing an incomplete reference.
 */
final class Generator
{
    /** Groups of the sidebar in their order; `@api <group>` names one of them. */
    public const array GROUPS = [
        'Attributes/Blocks',
        'Attributes/Values',
        'Attributes/Services and hooks',
        'Attributes/REST',
        'Attributes/Settings',
        'Attributes/Posts',
        'Attributes/Tables',
        'Attributes/Cache',
        'Attributes/Cron',
        'Attributes/WP-CLI',
        'Attributes/Lifecycle',
        'Attributes/Twig',
        'Boot classes',
        'Services',
        'Posts and queries',
        'Query fields',
        'Tables',
        'Cache',
        'Values and exceptions',
        'Enums',
    ];

    /** Directory of the generated PHP pages below `docs/content/`. */
    public const string PHP_PAGES = 'api/php';

    /** Directory of the generated Twig pages below `docs/content/`. */
    public const string TWIG_PAGES = 'api/twig';

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, string> Page below `docs/content/` without `.md` by full class name. */
    private array $pages = [];

    /** @var array<string, string> Group by full class name. */
    private array $groups = [];

    /**
     * @param string $source  The framework's `src/` directory.
     * @param string $prefix  Its namespace prefix, e.g. `WebCodingCat\WpAppFramework\`.
     * @param string $content The `docs/content/` directory.
     */
    public function __construct(
        private readonly string $source,
        private readonly string $prefix,
        private readonly string $content,
    ) {}

    /**
     * Writes the pages and returns the sidebar, or throws with every error found.
     *
     * @return list<array<mixed>> Sidebar groups for Rspress.
     * @throws RuntimeException
     */
    public function generate(): array
    {
        $classes = [];
        foreach (ClassDiscovery::classes(new Source($this->prefix, $this->source)) as $name) {
            /** @var class-string $name Loaded by the autoloader, which follows the same PSR-4 rules. */
            $class = new ReflectionClass($name);
            $classes[$name] = $class;
            $doc = DocBlock::parse($class->getDocComment());
            $group = $doc->getApi();
            if ($group === null) {
                continue;
            }
            if (!in_array($group, self::GROUPS, true)) {
                $this->errors[] = sprintf('%s: @api names the group "%s"; use one of %s.', $name, $group, implode(', ', self::GROUPS));
                continue;
            }
            $this->groups[$name] = $group;
            $this->pages[$name] = self::PHP_PAGES . '/' . str_replace('\\', '/', substr($name, strlen($this->prefix)));
        }

        $types = new Types($this->prefix, $this->pages);
        $files = [];
        foreach (array_keys($this->pages) as $name) {
            $files[$this->pages[$name]] = $this->classPage($classes[$name], $types);
        }
        $functions = $this->twigFunctions($classes);
        foreach ($functions as $function) {
            $files[$function['page']] = $this->twigPage($function, $types);
        }

        $errors = array_values(array_unique([...$this->errors, ...$types->errors()]));
        if ($errors !== []) {
            throw new RuntimeException("The API reference is incomplete:\n - " . implode("\n - ", $errors));
        }

        foreach ([self::PHP_PAGES, self::TWIG_PAGES] as $directory) {
            self::removeDirectory($this->content . '/' . $directory);
        }
        foreach ($files as $page => $markdown) {
            $file = $this->content . '/' . $page . '.md';
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0o777, true)) {
                throw new RuntimeException(sprintf('Cannot create the directory of %s.', $file));
            }
            file_put_contents($file, $markdown);
        }

        return $this->sidebar($functions);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function classPage(ReflectionClass $class, Types $types): string
    {
        $name = $class->getName();
        $page = $this->pages[$name];
        $doc = DocBlock::parse($class->getDocComment());
        $attribute = $class->getAttributes(Attribute::class)[0] ?? null;
        $title = $attribute !== null ? '#[' . $class->getShortName() . ']' : $class->getShortName();

        if ($doc->getDescription() === '') {
            $this->errors[] = sprintf('%s has no description.', $name);
        }

        $lines = ['# ' . $title, '', self::kind($class), '', '```php', 'use ' . $name . ';', '```', ''];
        if ($doc->getDescription() !== '') {
            $lines[] = $doc->getDescription();
            $lines[] = '';
        }
        array_push($lines, ...$this->see($doc, $name, $page));

        if ($class->isEnum()) {
            array_push($lines, ...$this->cases(new ReflectionEnum(self::enum($name))));
        }

        $constructor = $class->getConstructor();
        if (
            $constructor !== null && $constructor->isPublic() && !$class->isAbstract() && !$class->isInterface()
            && !$this->isInternal($constructor) && $this->isFramework($constructor->getDeclaringClass())
        ) {
            $heading = $attribute !== null ? 'Arguments' : 'Constructor';
            $lines[] = '## ' . $heading;
            $lines[] = '';
            $lines[] = '```php';
            $lines[] = $attribute !== null
                ? '#[' . $class->getShortName() . '(' . $this->parameterList($constructor) . ')]'
                : 'new ' . $class->getShortName() . '(' . $this->parameterList($constructor) . ')';
            $lines[] = '```';
            $lines[] = '';
            $methodDoc = $this->docOf($constructor);
            if ($attribute === null && $methodDoc->getDescription() !== '') {
                $lines[] = $methodDoc->getDescription();
                $lines[] = '';
            }
            array_push($lines, ...$this->parameterTable($constructor, $class, $page, $types));
        }

        array_push($lines, ...$this->constants($class));

        $methods = array_filter(
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $method): bool => !$method->isConstructor() && !str_starts_with($method->getName(), '__')
                && !$method->isInternal() && $this->isFramework($method->getDeclaringClass()) && !$this->isInternal($method),
        );
        usort($methods, static fn(ReflectionMethod $a, ReflectionMethod $b): int => [$b->isStatic(), $a->getName()] <=> [$a->isStatic(), $b->getName()]);
        if ($methods !== []) {
            $lines[] = '## Methods';
            $lines[] = '';
            foreach ($methods as $method) {
                array_push($lines, ...$this->method($method, $class, $page, $types));
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function kind(ReflectionClass $class): string
    {
        $attribute = $class->getAttributes(Attribute::class)[0] ?? null;
        if ($attribute !== null) {
            $flags = $attribute->newInstance()->flags;
            $targets = array_keys(array_filter([
                'classes' => Attribute::TARGET_CLASS,
                'methods' => Attribute::TARGET_METHOD,
                'parameters' => Attribute::TARGET_PARAMETER,
                'properties' => Attribute::TARGET_PROPERTY,
            ], static fn(int $flag): bool => ($flags & $flag) !== 0));

            return sprintf('Attribute on %s%s.', implode(' and ', $targets), ($flags & Attribute::IS_REPEATABLE) !== 0 ? ', repeatable' : '');
        }
        if ($class->isEnum()) {
            $backing = new ReflectionEnum(self::enum($class->getName()))->getBackingType();

            return $backing === null ? 'Enum.' : sprintf('Enum backed by `%s`.', $backing);
        }

        return match (true) {
            $class->isInterface() => 'Interface.',
            $class->isAbstract() => 'Abstract class.',
            default => 'Class.',
        } . self::parent($class);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function parent(ReflectionClass $class): string
    {
        $parent = $class->getParentClass();
        $interfaces = array_filter($class->getInterfaceNames(), static fn(string $interface): bool => !str_starts_with($interface, 'Stringable') && !str_starts_with($interface, 'Throwable'));

        $parts = [];
        if ($parent !== false) {
            $parts[] = sprintf('extends `%s`', Types::shortName($parent->getName()));
        }
        if (!$class->isInterface() && $interfaces !== []) {
            $parts[] = sprintf('implements %s', implode(', ', array_map(static fn(string $interface): string => '`' . Types::shortName($interface) . '`', $interfaces)));
        }

        return $parts === [] ? '' : ' It ' . implode(' and ', $parts) . '.';
    }

    /**
     * @return list<string>
     */
    private function see(DocBlock $doc, string $location, string $page): array
    {
        $links = [];
        foreach ($doc->getSee() as $target) {
            $path = explode('#', $target, 2)[0];
            $anchor = explode('#', $target, 2)[1] ?? null;
            $file = $this->content . '/' . $path;
            if (!str_ends_with($path, '.md') || !is_file($file)) {
                $this->errors[] = sprintf('%s: @see %s names no page below docs/content/.', $location, $target);
                continue;
            }
            preg_match('/^# (.+)$/m', (string) file_get_contents($file), $match);
            $links[] = sprintf('[%s](%s%s)', $match[1] ?? $path, Types::link($page, substr($path, 0, -3)), $anchor === null ? '' : '#' . $anchor);
        }

        return $links === [] ? [] : ['See ' . implode(', ', $links) . '.', ''];
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     *
     * @return list<string>
     */
    private function cases(ReflectionEnum $enum): array
    {
        $backed = $enum->isBacked();
        $lines = ['## Cases', '', $backed ? '| Case | Value | Description |' : '| Case | Description |', $backed ? '|---|---|---|' : '|---|---|'];
        foreach ($enum->getCases() as $case) {
            $description = str_replace(["\n", '|'], [' ', '\\|'], DocBlock::parse($case->getDocComment())->getDescription());
            $lines[] = $backed && $case instanceof ReflectionEnumBackedCase
                ? sprintf('| `%s` | `%s` | %s |', $case->getName(), Types::value($case->getBackingValue()), $description)
                : sprintf('| `%s` | %s |', $case->getName(), $description);
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return list<string>
     */
    private function constants(ReflectionClass $class): array
    {
        $lines = [];
        foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->isEnumCase() || !$this->isFramework($constant->getDeclaringClass())) {
                continue;
            }
            $doc = DocBlock::parse($constant->getDocComment());
            if ($doc->isInternal()) {
                continue;
            }
            if ($doc->getDescription() === '') {
                $this->errors[] = sprintf('%s::%s has no description.', $class->getName(), $constant->getName());
            }
            $lines[] = sprintf('| `%s` | `%s` | %s |', $constant->getName(), str_replace('|', '\\|', Types::value($constant->getValue())), str_replace("\n", ' ', $doc->getDescription()));
        }

        return $lines === [] ? [] : ['## Constants', '', '| Constant | Value | Description |', '|---|---|---|', ...$lines, ''];
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return list<string>
     */
    private function method(ReflectionMethod $method, ReflectionClass $class, string $page, Types $types): array
    {
        $doc = $this->docOf($method);
        $location = sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());
        if ($doc->getDescription() === '') {
            $this->errors[] = sprintf('%s has no description.', $location);
        }
        $declaring = $method->getDeclaringClass();
        $return = $method->getReturnType();
        $signature = sprintf(
            '%s%s(%s)%s',
            $method->isStatic() ? $class->getShortName() . '::' : '',
            $method->getName(),
            $this->parameterList($method),
            $return === null ? '' : ': ' . Types::native($return),
        );

        $lines = ['### ' . $method->getName() . '()', '', '```php', $signature, '```', ''];
        if ($doc->getDescription() !== '') {
            $lines[] = $doc->getDescription();
            $lines[] = '';
        }
        array_push($lines, ...$this->parameterTable($method, $declaring, $page, $types));
        $returnType = $doc->getReturnType();
        if ($return !== null && !in_array(Types::native($return), ['void', 'never'], true)) {
            $lines[] = sprintf(
                '**Returns** %s%s',
                $types->render($returnType ?? Types::native($return), $declaring, $page, $location, false),
                $doc->getReturnDescription() === '' ? '' : ': ' . $doc->getReturnDescription(),
            );
            $lines[] = '';
        }
        foreach ($doc->getThrows() as $throws) {
            $lines[] = sprintf('**Throws** %s%s', $types->render($throws['type'], $declaring, $page, $location, false), $throws['description'] === '' ? '' : ': ' . $throws['description']);
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param ReflectionClass<object> $declaring
     *
     * @return list<string>
     */
    private function parameterTable(ReflectionMethod $method, ReflectionClass $declaring, string $page, Types $types): array
    {
        $parameters = $method->getParameters();
        if ($parameters === []) {
            return [];
        }
        $doc = $this->docOf($method);
        $location = sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());
        $lines = ['| Parameter | Type | Default | Description |', '|---|---|---|---|'];
        foreach ($parameters as $parameter) {
            $param = $doc->getParam($parameter->getName());
            if ($param === null || $param['description'] === '') {
                $this->errors[] = sprintf('Parameter $%s of %s has no description.', $parameter->getName(), $location);
            }
            $type = $param !== null && $param['type'] !== '' ? $param['type'] : Types::native($parameter->getType());
            $lines[] = sprintf(
                '| `%s%s` | %s | %s | %s |',
                $parameter->isVariadic() ? '...' : '',
                $parameter->getName(),
                $type === '' ? '' : $types->render($type, $declaring, $page, sprintf('Parameter $%s of %s', $parameter->getName(), $location)),
                self::default($parameter),
                str_replace("\n\n", '<br/><br/>', $param['description'] ?? ''),
            );
        }
        $lines[] = '';

        return $lines;
    }

    private function parameterList(ReflectionMethod $method): string
    {
        return implode(', ', array_map(
            static function (ReflectionParameter $parameter): string {
                $type = Types::native($parameter->getType());
                $default = $parameter->isDefaultValueAvailable() ? ' = ' . self::defaultCode($parameter) : '';

                return ($type === '' ? '' : $type . ' ') . ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName() . $default;
            },
            $method->getParameters(),
        ));
    }

    private static function default(ReflectionParameter $parameter): string
    {
        if ($parameter->isVariadic()) {
            return '';
        }
        if (!$parameter->isDefaultValueAvailable()) {
            return 'required';
        }

        return '`' . str_replace('|', '\\|', self::defaultCode($parameter)) . '`';
    }

    private static function defaultCode(ReflectionParameter $parameter): string
    {
        try {
            if ($parameter->isDefaultValueConstant()) {
                $constant = (string) $parameter->getDefaultValueConstantName();

                return str_contains($constant, '::') ? Types::shortName(explode('::', $constant)[0]) . '::' . explode('::', $constant)[1] : $constant;
            }

            return Types::value($parameter->getDefaultValue());
        } catch (ReflectionException) {
            return '…';
        }
    }

    /**
     * The doc comment of a method, or of the method it implements or overrides if it has no description itself.
     */
    private function docOf(ReflectionMethod $method): DocBlock
    {
        $doc = DocBlock::parse($method->getDocComment());
        if ($doc->getDescription() !== '' || $method->isConstructor()) {
            return $doc;
        }
        foreach ($this->prototypes($method) as $prototype) {
            $inherited = DocBlock::parse($prototype->getDocComment());
            if ($inherited->getDescription() !== '') {
                return $inherited;
            }
        }

        return $doc;
    }

    private function isInternal(ReflectionMethod $method): bool
    {
        if (DocBlock::parse($method->getDocComment())->isInternal()) {
            return true;
        }
        foreach ($this->prototypes($method) as $prototype) {
            if (DocBlock::parse($prototype->getDocComment())->isInternal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methods of parent classes and interfaces with the same name.
     *
     * @return list<ReflectionMethod>
     */
    private function prototypes(ReflectionMethod $method): array
    {
        $class = $method->getDeclaringClass();
        $candidates = [];
        $parent = $class->getParentClass();
        while ($parent !== false) {
            $candidates[] = $parent;
            $parent = $parent->getParentClass();
        }
        foreach ($class->getInterfaces() as $interface) {
            $candidates[] = $interface;
        }

        $prototypes = [];
        foreach ($candidates as $candidate) {
            if ($candidate->hasMethod($method->getName())) {
                $prototype = $candidate->getMethod($method->getName());
                if ($prototype->getDeclaringClass()->getName() !== $class->getName()) {
                    $prototypes[] = $prototype;
                }
            }
        }

        return $prototypes;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function isFramework(ReflectionClass $class): bool
    {
        return str_starts_with($class->getName(), $this->prefix);
    }

    /**
     * Template functions and filters the framework registers itself.
     *
     * @param array<string, ReflectionClass<object>> $classes
     *
     * @return list<array{name: string, kind: string, html: bool, method: ReflectionMethod, class: ReflectionClass<object>, page: string, group: string}>
     */
    private function twigFunctions(array $classes): array
    {
        $functions = [];
        foreach ($classes as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                foreach ([TemplateFunction::class => 'function', TemplateFilter::class => 'filter'] as $attribute => $kind) {
                    foreach ($method->getAttributes($attribute) as $found) {
                        $instance = $found->newInstance();
                        $functions[] = [
                            'name' => $instance->name,
                            'kind' => $kind,
                            'html' => $instance->html,
                            'method' => $method,
                            'class' => $class,
                            // Rspress leaves out files whose name starts with "_" (partials): __() is on fn__.md.
                            'page' => self::TWIG_PAGES . '/' . (str_starts_with($instance->name, '_') ? 'fn' : '') . $instance->name,
                            'group' => str_ends_with($class->getName(), 'WordPressTemplateFunctions') ? 'WordPress' : 'Framework',
                        ];
                    }
                }
            }
        }
        usort($functions, static fn(array $a, array $b): int => $a['name'] <=> $b['name']);

        return $functions;
    }

    /**
     * @param array{name: string, kind: string, html: bool, method: ReflectionMethod, class: ReflectionClass<object>, page: string, group: string} $function
     */
    private function twigPage(array $function, Types $types): string
    {
        $method = $function['method'];
        $doc = DocBlock::parse($method->getDocComment());
        $location = sprintf('Template %s %s() (%s::%s())', $function['kind'], $function['name'], $function['class']->getName(), $method->getName());
        if ($doc->getDescription() === '') {
            $this->errors[] = sprintf('%s has no description.', $location);
        }
        $parameters = $method->getParameters();
        $arguments = implode(', ', array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName() . ($parameter->isDefaultValueAvailable() ? ' = ' . self::twigValue(self::defaultCode($parameter)) : ''),
            $function['kind'] === 'filter' ? array_slice($parameters, 1) : $parameters,
        ));
        $usage = $function['kind'] === 'filter'
            ? sprintf('{{ value|%s%s }}', $function['name'], $arguments === '' ? '' : '(' . $arguments . ')')
            : sprintf('{{ %s(%s) }}', $function['name'], $arguments);

        $lines = [
            '# ' . $function['name'] . '()',
            '',
            sprintf(
                'Twig %s of the framework%s.',
                $function['kind'],
                $function['html'] ? '; its result is HTML and is printed without escaping' : '; its result is escaped like any value',
            ),
            '',
            '```twig',
            $usage,
            '```',
            '',
        ];
        if ($doc->getDescription() !== '') {
            $lines[] = $doc->getDescription();
            $lines[] = '';
        }
        array_push($lines, ...$this->see($doc, $location, $function['page']));
        array_push($lines, ...$this->parameterTable($method, $function['class'], $function['page'], $types));
        $return = $method->getReturnType();
        if ($return !== null) {
            $lines[] = sprintf(
                '**Returns** %s%s',
                $types->render($doc->getReturnType() ?? Types::native($return), $function['class'], $function['page'], $location, false),
                $doc->getReturnDescription() === '' ? '' : ': ' . $doc->getReturnDescription(),
            );
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function twigValue(string $php): string
    {
        return match ($php) {
            '[]' => '{}',
            default => $php,
        };
    }

    /**
     * @param list<array{name: string, kind: string, html: bool, method: ReflectionMethod, class: ReflectionClass<object>, page: string, group: string}> $functions
     *
     * @return list<array<mixed>>
     */
    private function sidebar(array $functions): array
    {
        $tree = [];
        foreach (self::GROUPS as $group) {
            $classes = array_keys(array_filter($this->groups, static fn(string $own): bool => $own === $group));
            usort($classes, static fn(string $a, string $b): int => strcasecmp(Types::shortName($a), Types::shortName($b)));
            $items = array_map(
                fn(string $class): array => [
                    'text' => str_starts_with($group, 'Attributes/') ? '#[' . Types::shortName($class) . ']' : Types::shortName($class),
                    'link' => '/' . $this->pages[$class],
                ],
                $classes,
            );
            if ($items === []) {
                continue;
            }
            [$top, $sub] = array_pad(explode('/', $group, 2), 2, null);
            if ($sub === null) {
                $tree[] = ['text' => $top, 'collapsible' => true, 'collapsed' => true, 'items' => $items];
                continue;
            }
            $index = array_search($top, array_column($tree, 'text'), true);
            if ($index === false) {
                $tree[] = ['text' => $top, 'collapsible' => true, 'collapsed' => true, 'items' => []];
                $index = array_key_last($tree);
            }
            $tree[$index]['items'][] = ['text' => $sub, 'collapsible' => true, 'collapsed' => true, 'items' => $items];
        }

        $twig = [];
        foreach (['Framework', 'WordPress'] as $group) {
            $twig[] = [
                'text' => $group,
                'collapsible' => true,
                'collapsed' => true,
                'items' => array_values(array_map(
                    static fn(array $function): array => ['text' => $function['name'] . '()', 'link' => '/' . $function['page']],
                    array_filter($functions, static fn(array $function): bool => $function['group'] === $group),
                )),
            ];
        }
        $tree[] = ['text' => 'Twig functions', 'collapsible' => true, 'collapsed' => true, 'items' => $twig];

        return array_values($tree);
    }

    /**
     * @return class-string<\UnitEnum>
     */
    private static function enum(string $class): string
    {
        if (!enum_exists($class)) {
            throw new RuntimeException(sprintf('%s is no enum.', $class));
        }

        return $class;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}
