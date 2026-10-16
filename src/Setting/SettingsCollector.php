<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Setting;

use ReflectionClass;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[SettingsGroup]` classes during the build and
 * derives their schema, defaults and form controls.
 *
 * @phpstan-import-type Control from ValueSchemaBuilder
 * @phpstan-type SettingsGroupEntry array{
 *     name: non-empty-string,
 *     option: non-empty-string,
 *     title: string,
 *     description: string,
 *     position: int,
 *     class: class-string,
 *     schema: array{type: 'object', properties: array<string, array<string, mixed>>},
 *     default: array<string, mixed>,
 *     controls: array<string, Control>,
 *     richText: list<string>,
 *     identifiers: list<string>,
 *     script: string|null
 * }
 */
#[NoService]
final class SettingsCollector
{
    private const string NAME_PATTERN = '~^[a-z][a-z0-9_]*$~';

    /** Width of the option_name column of wp_options. */
    private const int MAX_OPTION_LENGTH = 191;

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param string                $frameworkDirectory Source directory of the framework, which must not declare settings.
     * @param non-empty-string      $namespace          Namespace of the project, for the option names.
     * @param string|null           $title              Name of the theme or plugin, the title of its settings page.
     * @param string|null           $scriptDirectory    Directory the consumer's build compiles scripts from (its `src/`),
     *                                                  for the settings scripts next to the groups; null without one.
     */
    public function __construct(
        private readonly ValueSchemaBuilder $values,
        private readonly string $frameworkDirectory,
        private readonly string $namespace,
        private readonly ?string $title = null,
        private readonly ?string $scriptDirectory = null,
    ) {}

    /**
     * The scanned classes with `#[SettingsGroup]`, valid or not: they are no services.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @return list<class-string>
     */
    public static function groupClasses(array $classes): array
    {
        $groups = [];
        foreach ($classes as $name => $class) {
            if ($class->getAttributes(SettingsGroup::class) !== []) {
                $groups[] = $name;
            }
        }

        return $groups;
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<SettingsGroupEntry> Ordered by position, then name.
     */
    public function collect(array $classes): array
    {
        $entries = [];
        /** @var array<string, class-string> $names Group name => class, to find duplicates. */
        $names = [];

        foreach (self::groupClasses($classes) as $name) {
            $class = $classes[$name];
            try {
                $group = $class->getAttributes(SettingsGroup::class)[0]->newInstance();
            } catch (Throwable $exception) {
                $this->errors[] = sprintf('Invalid #[SettingsGroup] on %s: %s', $name, $exception->getMessage());
                continue;
            }

            $file = (string) realpath((string) $class->getFileName());
            $option = SettingsStore::optionName($this->namespace, $group->name);
            $error = match (true) {
                str_starts_with($file, $this->frameworkDirectory . '/') => sprintf('%s has #[SettingsGroup], but the framework declares no settings; they belong to the theme or plugin.', $name),
                preg_match(self::NAME_PATTERN, $group->name) !== 1 => sprintf(
                    '#[SettingsGroup] on %s has the invalid name "%s": use lowercase letters, digits and "_", starting with a letter, e.g. "general".',
                    $name,
                    $group->name,
                ),
                isset($names[$group->name]) => sprintf('#[SettingsGroup] on %s uses the name "%s", which %s already uses.', $name, $group->name, $names[$group->name]),
                trim($group->title) === '' => sprintf('#[SettingsGroup] on %s has an empty title.', $name),
                strlen($option) > self::MAX_OPTION_LENGTH => sprintf(
                    '#[SettingsGroup] on %s results in the option name "%s", which is longer than %d characters (the limit of WordPress\' options table); use a shorter name.',
                    $name,
                    $option,
                    self::MAX_OPTION_LENGTH,
                ),
                default => null,
            };
            if ($error !== null) {
                $this->errors[] = $error;
                continue;
            }
            $names[$group->name] = $name;

            [$script, $scriptError] = ScriptKind::Settings->locate($file, $name, $this->scriptDirectory);
            if ($scriptError !== null) {
                $this->errors[] = $scriptError;
                continue;
            }
            $entry = $this->entry($name, $group, $option, $script);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries !== [] && ($this->title === null || trim($this->title) === '')) {
            $this->errors[] = 'The project declares settings groups, but has no name for its settings page: add a "Theme Name:" header to style.css (themes and child themes) or a "Plugin Name:" header to the main file {slug}.php (plugins).';
        }

        usort($entries, static fn(array $a, array $b): int => [$a['position'], $a['name']] <=> [$b['position'], $b['name']]);

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param class-string     $class
     * @param non-empty-string $option
     * @param string|null      $script The settings script below the script directory, without extension.
     * @return SettingsGroupEntry|null
     */
    private function entry(string $class, SettingsGroup $group, string $option, ?string $script): ?array
    {
        $location = sprintf('#[SettingsGroup] on %s', $class);
        $fields = $this->values->objectFields($class, $location, true);
        // Checked even if the class is invalid otherwise, so all errors are reported at once.
        $parameters = $this->values->hasClass($class) ? $this->values->getClass($class)->getConstructor()?->getParameters() ?? [] : [];

        $properties = [];
        $default = [];
        $controls = [];
        $richText = [];
        $valid = $fields !== null;
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $parameterLocation = sprintf('parameter $%s of settings group class %s::__construct()', $name, $class);
            if (!$parameter->isDefaultValueAvailable()) {
                $this->errors[] = sprintf('%s needs a default value: a setting is missing until it is saved.', $parameterLocation);
                $valid = false;
                continue;
            }

            $field = $fields[$name] ?? null;
            if ($field === null) {
                continue;
            }
            $schema = $field['schema'];
            $value = ValueSchemaBuilder::export($parameter->getDefaultValue());
            if ($value !== null) {
                $schema['default'] = $value;
            }
            $properties[$name] = $schema;
            $default[$name] = $value;
            if ($field['control'] !== null) {
                $controls[$name] = $field['control'];
            }
            foreach ($field['fields'] as $editorField) {
                if ($editorField['kind'] === 'rich') {
                    $richText[] = $editorField['path'] === '' ? $name : $name . '.' . $editorField['path'];
                }
            }
        }

        if (!$valid || $group->name === '') {
            return null;
        }

        return [
            'name' => $group->name,
            'option' => $option,
            'title' => $group->title,
            'description' => $group->description,
            'position' => $group->position,
            'class' => $class,
            'schema' => ['type' => 'object', 'properties' => $properties],
            'default' => $default,
            'controls' => $controls,
            'richText' => $richText,
            'identifiers' => $this->values->identifierPaths($fields ?? []),
            'script' => $script,
        ];
    }
}
