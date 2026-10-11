<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Support;

use ReflectionClass;
use ReflectionNamedType;
use Saij\WpAppFramework\Block\BlockCollector;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Editor\EditorScript;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[BlockSupport]` classes during the build and the
 * `#[UseSupport]` declarations of block classes. A support service is resolved
 * only when a block uses it.
 *
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-type BlockSupportUse array{definition: SupportDefinition, default: mixed}
 */
#[NoService]
final class SupportCollector
{
    /** camelCase, like the attribute names WordPress uses. */
    private const string ATTRIBUTE_PATTERN = '~^[a-z][a-zA-Z0-9]*$~';

    private const string METHOD = 'className';

    private const string VALUE_TYPES = 'int, float, string, bool or a backed enum, optionally nullable';

    /** @var list<string> */
    private array $errors = [];

    /** @var array<class-string, SupportDefinition> */
    private array $supports = [];

    /** @var list<class-string> Classes with an invalid #[BlockSupport]; using them reports no second error. */
    private array $invalid = [];

    private readonly EntryPointInspector $inspector;

    /**
     * @param string      $frameworkDirectory Source directory of the framework, which must not declare block supports.
     * @param string|null $scriptDirectory    Directory the consumer's build compiles scripts from (its `src/`).
     */
    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
        private readonly string $frameworkDirectory,
        private readonly ?string $scriptDirectory = null,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function collect(array $classes): void
    {
        /** @var array<string, class-string> $attributes Attribute name => support class, to find duplicates. */
        $attributes = [];

        foreach ($classes as $name => $class) {
            $declarations = $class->getAttributes(BlockSupport::class);
            if ($declarations === []) {
                continue;
            }

            try {
                $support = $declarations[0]->newInstance();
            } catch (Throwable $exception) {
                $this->fail($name, sprintf('Invalid #[BlockSupport] on %s: %s', $name, $exception->getMessage()));
                continue;
            }

            $file = (string) realpath((string) $class->getFileName());
            $error = match (true) {
                str_starts_with($file, $this->frameworkDirectory . '/') => sprintf('%s has #[BlockSupport], but the framework declares no block supports; they belong to the theme or plugin.', $name),
                preg_match(self::ATTRIBUTE_PATTERN, $support->attribute) !== 1 => sprintf(
                    '#[BlockSupport] on %s has the invalid attribute name "%s": use camelCase letters and digits, starting with a lowercase letter, e.g. "colorScheme".',
                    $name,
                    $support->attribute,
                ),
                in_array($support->attribute, BlockCollector::RESERVED_ATTRIBUTES, true) => sprintf(
                    '#[BlockSupport] on %s uses the attribute name "%s", which WordPress reserves (%s).',
                    $name,
                    $support->attribute,
                    implode(', ', BlockCollector::RESERVED_ATTRIBUTES),
                ),
                isset($attributes[$support->attribute]) => sprintf(
                    '#[BlockSupport] on %s uses the attribute name "%s", which %s already declares.',
                    $name,
                    $support->attribute,
                    $attributes[$support->attribute],
                ),
                trim($support->label) === '' => sprintf('#[BlockSupport] on %s has an empty label.', $name),
                default => $this->inspector->serviceError($name, $name, '#[BlockSupport]'),
            };
            if ($error !== null) {
                $this->fail($name, $error);
                continue;
            }
            $attributes[$support->attribute] = $name;

            $definition = $this->definition($class, $support, $file);
            if ($definition === null) {
                $this->invalid[] = $name;
                continue;
            }
            $this->supports[$name] = $definition;
        }
    }

    /**
     * The supports a block class uses, in declaration order; resolves their services.
     *
     * @param ReflectionClass<object> $class
     * @param list<string>            $attributeNames Attributes of the block's attribute class.
     * @return list<BlockSupportUse>|null Null on errors (recorded).
     */
    public function blockSupports(ReflectionClass $class, array $attributeNames): ?array
    {
        $name = $class->getName();
        $uses = [];
        $valid = true;

        foreach ($class->getAttributes(UseSupport::class) as $attribute) {
            try {
                $use = $attribute->newInstance();
            } catch (Throwable $exception) {
                $this->errors[] = sprintf('Invalid #[UseSupport] on %s: %s', $name, $exception->getMessage());
                $valid = false;
                continue;
            }
            $arguments = $attribute->getArguments();
            $hasDefault = array_key_exists('default', $arguments) || array_key_exists(1, $arguments);

            if (in_array($use->support, $this->invalid, true)) {
                // The declaration of the support is invalid; that error is reported already.
                $valid = false;
                continue;
            }
            $definition = $this->supports[$use->support] ?? null;
            $error = match (true) {
                $definition === null => sprintf(
                    '#[UseSupport] on %s names %s, which is no class with #[BlockSupport] in the scanned PSR-4 directories of the project.',
                    $name,
                    $use->support,
                ),
                isset($uses[$use->support]) => sprintf('#[UseSupport] on %s names %s twice.', $name, $use->support),
                in_array($definition->getAttribute(), $attributeNames, true) => sprintf(
                    '#[UseSupport(%s)] on %s adds the attribute "%s", which the block attribute class already declares.',
                    $use->support,
                    $name,
                    $definition->getAttribute(),
                ),
                $hasDefault => self::defaultError($definition->getValue(), $use->default, sprintf('#[UseSupport(%s)] on %s', $use->support, $name)),
                default => null,
            };
            if ($error !== null || $definition === null) {
                $this->errors[] = (string) $error;
                $valid = false;
                continue;
            }

            $uses[$use->support] = ['definition' => $definition, 'default' => $hasDefault ? $use->default : $definition->getDefault()];
        }

        if (!$valid) {
            return null;
        }
        foreach (array_keys($uses) as $support) {
            $this->resolver->resolve($support);
        }

        return array_values($uses);
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param class-string $class
     */
    private function fail(string $class, string $error): void
    {
        $this->errors[] = $error;
        $this->invalid[] = $class;
    }

    /**
     * Checks className() and derives schema, value description and control from its parameter.
     *
     * @param ReflectionClass<object> $class
     */
    private function definition(ReflectionClass $class, BlockSupport $support, string $file): ?SupportDefinition
    {
        $name = $class->getName();
        if (!$class->hasMethod(self::METHOD)) {
            $this->errors[] = sprintf('%s has #[BlockSupport], but no className() method returning the class names for a value.', $name);

            return null;
        }

        $method = $class->getMethod(self::METHOD);
        $location = sprintf('%s::className()', $name);
        $returnType = $method->getReturnType();
        $parameters = $method->getParameters();
        $parameter = $parameters[0] ?? null;
        $error = match (true) {
            !$method->isPublic() || $method->isStatic() => sprintf('%s of a #[BlockSupport] class must be public and non-static.', $location),
            !$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'string' => sprintf('%s of a #[BlockSupport] class must return string or ?string.', $location),
            count($parameters) !== 1 || $parameter === null || $parameter->isVariadic() || $parameter->isPassedByReference() => sprintf(
                '%s of a #[BlockSupport] class must accept exactly one parameter, the value of the attribute.',
                $location,
            ),
            $parameter->getAttributes() !== [] => sprintf(
                'parameter $%s of %s must not have attributes; label and group of the control come from #[BlockSupport].',
                $parameter->getName(),
                $location,
            ),
            !$parameter->isDefaultValueAvailable() => sprintf(
                'parameter $%s of %s needs a default value: the attribute can always be missing in the content.',
                $parameter->getName(),
                $location,
            ),
            default => null,
        };
        if ($error !== null || $parameter === null) {
            $this->errors[] = (string) $error;

            return null;
        }

        $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || !(in_array($type->getName(), ['int', 'float', 'string', 'bool'], true) || enum_exists($type->getName()))) {
            $this->errors[] = sprintf('%s: the value of a block support must be %s.', $parameterLocation, self::VALUE_TYPES);

            return null;
        }
        $field = $this->values->parameter($parameter, $parameterLocation);
        if ($field === null || $field['control'] === null) {
            return null;
        }
        [$editorScript, $error] = EditorScript::locate($file, $name, $this->scriptDirectory);
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        return new SupportDefinition(
            $name,
            $support->attribute,
            $support->group,
            $field['schema'],
            $field['value'],
            ['label' => $support->label] + $field['control'],
            $parameter->getDefaultValue(),
            $editorScript,
        );
    }

    /**
     * @param Value $value
     */
    private static function defaultError(array $value, mixed $default, string $location): ?string
    {
        $valid = match (true) {
            $default === null => $value['nullable'],
            $value['type'] === 'enum' => $default instanceof $value['class'],
            $value['type'] === 'int' => is_int($default),
            $value['type'] === 'float' => is_float($default) || is_int($default),
            $value['type'] === 'string' => is_string($default),
            $value['type'] === 'bool' => is_bool($default),
            default => false,
        };
        if ($valid) {
            return null;
        }

        return sprintf(
            '%s: default must be %s%s, %s given.',
            $location,
            $value['type'] === 'enum' || $value['type'] === 'object' ? 'a case of ' . $value['class'] : $value['type'],
            $value['nullable'] ? ' or null' : '',
            get_debug_type($default),
        );
    }
}
