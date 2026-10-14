<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cron;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[CronEvent]` and `#[CronTask]` methods during the
 * build and resolves the services they need.
 *
 * @phpstan-import-type CronArgument from CronEventDefinition
 * @phpstan-import-type Field from ValueSchemaBuilder
 */
#[NoService]
final class CronCollector
{
    /** Names of events and tasks: they are part of hook names. */
    public const string NAME_PATTERN = '~^[a-z][a-z0-9-]*$~';

    /** Start times: `HH:MM`. */
    private const string AT_PATTERN = '~^([01]\d|2[0-3]):[0-5]\d$~';

    /** Shortest interval of an event in seconds. */
    public const int MIN_INTERVAL = 60;

    /** How the resolver describes task classes, which are no services. */
    private const string TASK = 'a cron task';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<CronEventDefinition> */
    private array $events = [];

    /** @var list<CronTaskDefinition> */
    private array $tasks = [];

    private readonly EntryPointInspector $inspector;

    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * The task classes (the first parameter of `#[CronTask]` methods): data, not services.
     *
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return array<class-string, string> Class => "a cron task".
     */
    public static function taskClasses(array $classes): array
    {
        $tasks = [];
        foreach ($classes as $name => $class) {
            foreach ($class->getMethods() as $method) {
                if ($method->getAttributes(CronTask::class) === [] || $method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }
                $type = ($method->getParameters()[0] ?? null)?->getType();
                $task = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $classes[$type->getName()] ?? null : null;
                if ($task !== null && !$task->isInterface() && !$task->isTrait() && !$task->isEnum() && !$task->isAbstract()) {
                    $tasks[$task->getName()] = self::TASK;
                }
            }
        }

        return $tasks;
    }

    /**
     * The name of the task of a task class: its short name in kebab case (`SendReminder` → `send-reminder`).
     */
    public static function taskName(string $class): string
    {
        $short = substr($class, (int) strrpos('\\' . $class, '\\'));

        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $short));
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function collect(array $classes): void
    {
        /** @var array<string, string> $eventNames Name => location of the first event with it. */
        $eventNames = [];
        /** @var array<string, class-string> $taskNames Name => task class. */
        $taskNames = [];
        /** @var array<class-string, string> $handlers Task class => location of its handler. */
        $handlers = [];

        foreach ($classes as $name => $class) {
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }
                $event = $method->getAttributes(CronEvent::class) !== [];
                $task = $method->getAttributes(CronTask::class) !== [];
                if (!$event && !$task) {
                    continue;
                }

                $location = sprintf('%s::%s()', $name, $method->getName());
                if ($event && $task) {
                    $this->errors[] = sprintf('%s has #[CronEvent] and #[CronTask]; a method is either a recurring event or the handler of a task.', $location);
                    continue;
                }

                $attribute = $event ? '#[CronEvent]' : '#[CronTask]';
                $error = $this->inspector->serviceError($name, $location, $attribute)
                    ?? $this->inspector->publicInstanceMethodError($method, $location, $attribute)
                    ?? (EntryPointInspector::isVoid($method) ? null : sprintf('%s has %s and must return void.', $location, $attribute));
                if ($error !== null) {
                    $this->errors[] = $error;
                    continue;
                }

                if ($event) {
                    $definition = $this->event($name, $method, $location);
                    if ($definition === null) {
                        continue;
                    }
                    if (isset($eventNames[$definition->getName()])) {
                        $this->errors[] = sprintf('#[CronEvent] name "%s" is used by %s and %s; each event needs a name of its own.', $definition->getName(), $eventNames[$definition->getName()], $location);
                        continue;
                    }
                    $eventNames[$definition->getName()] = $location;
                    $this->events[] = $definition;
                } else {
                    $definition = $this->task($name, $method, $location);
                    if ($definition === null) {
                        continue;
                    }
                    $taskClass = $definition->getTask();
                    if (isset($handlers[$taskClass])) {
                        $this->errors[] = sprintf('%s is handled by %s and %s; each task class has exactly one #[CronTask] handler.', $taskClass, $handlers[$taskClass], $location);
                        continue;
                    }
                    $handlers[$taskClass] = $location;
                    if (isset($taskNames[$definition->getName()])) {
                        $this->errors[] = sprintf(
                            'The task classes %s and %s have the same short name, so both tasks would be named "%s"; rename one of them.',
                            $taskNames[$definition->getName()],
                            $taskClass,
                            $definition->getName(),
                        );
                        continue;
                    }
                    $taskNames[$definition->getName()] = $taskClass;
                    $this->tasks[] = $definition;
                }

                $this->resolver->resolve($name);
                foreach ($definition->getArguments() as $argument) {
                    $this->resolver->resolve($argument['service']);
                }
            }
        }
    }

    /**
     * @return list<CronEventDefinition>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<CronTaskDefinition>
     */
    public function tasks(): array
    {
        return $this->tasks;
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
    private function event(string $class, ReflectionMethod $method, string $location): ?CronEventDefinition
    {
        try {
            $event = $method->getAttributes(CronEvent::class)[0]->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = self::invalid('#[CronEvent]', $location, $exception);

            return null;
        }

        $prefix = sprintf('#[CronEvent] on %s: ', $location);
        $interval = $event->interval;
        $errors = [];
        if (preg_match(self::NAME_PATTERN, $event->name) !== 1) {
            $errors[] = sprintf('%sthe name "%s" must consist of lowercase letters, digits and hyphens and start with a letter.', $prefix, $event->name);
        }
        if (is_int($interval) && $interval < self::MIN_INTERVAL) {
            $errors[] = sprintf('%sthe interval must be at least %d seconds, %d given.', $prefix, self::MIN_INTERVAL, $interval);
        }
        if ($event->at !== null) {
            if (preg_match(self::AT_PATTERN, $event->at) !== 1) {
                $errors[] = sprintf('%sat must be a time of day as HH:MM (00:00 to 23:59), "%s" given.', $prefix, $event->at);
            }
            if (is_int($interval) || $interval === CronInterval::Hourly) {
                $errors[] = sprintf('%sat needs the interval CronInterval::Daily, CronInterval::TwiceDaily or CronInterval::Weekly.', $prefix);
            }
            if ($interval === CronInterval::Weekly && $event->weekday === null) {
                $errors[] = sprintf('%sa weekly event with at needs a weekday.', $prefix);
            }
        }
        if ($event->weekday !== null && ($interval !== CronInterval::Weekly || $event->at === null)) {
            $errors[] = sprintf('%sweekday needs the interval CronInterval::Weekly and at.', $prefix);
        }

        /** @var list<CronArgument> $arguments */
        $arguments = [];
        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
            $error = $this->inspector->parameterError($parameter, $parameterLocation, 'cron events');
            $service = $this->resolver->injectedType($parameter);
            if ($error === null && $service === null) {
                $error = sprintf('%s: WP-Cron passes no arguments to recurring events; the parameters of a #[CronEvent] method must be services.', $parameterLocation);
            }
            if ($error !== null || $service === null) {
                $errors[] = (string) $error;
                continue;
            }
            $arguments[] = ['param' => $parameter->getName(), 'service' => $service];
        }

        if ($errors !== []) {
            array_push($this->errors, ...$errors);

            return null;
        }

        /** @var non-empty-string $name Checked against NAME_PATTERN. */
        $name = $event->name;
        /** @var positive-int|non-empty-string $intervalValue At least MIN_INTERVAL or the name of a WordPress interval. */
        $intervalValue = is_int($interval) ? $interval : $interval->value;
        /** @var non-empty-string|null $at Checked against AT_PATTERN. */
        $at = $event->at;

        return new CronEventDefinition($name, $intervalValue, $at, $event->weekday?->value, $class, $method->getName(), $arguments);
    }

    /**
     * @param class-string $class
     */
    private function task(string $class, ReflectionMethod $method, string $location): ?CronTaskDefinition
    {
        try {
            $method->getAttributes(CronTask::class)[0]->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = self::invalid('#[CronTask]', $location, $exception);

            return null;
        }

        $parameters = $method->getParameters();
        $first = $parameters[0] ?? null;
        if ($first === null) {
            $this->errors[] = sprintf('%s has #[CronTask] and needs the task as first parameter: a value class whose object carries the data of the task.', $location);

            return null;
        }

        $taskLocation = sprintf('parameter $%s of %s', $first->getName(), $location);
        $type = $first->getType();
        $task = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        $error = match (true) {
            $first->isVariadic() || $first->isPassedByReference() => sprintf('%s: the task parameter must not be variadic or by reference.', $taskLocation),
            $task === null => sprintf('%s: the task must be typed with a value class, %s given.', $taskLocation, $type === null ? 'no type' : (string) $type),
            $type?->allowsNull() === true => sprintf('%s: the task must not be nullable.', $taskLocation),
            is_subclass_of($task, AbstractPost::class) => sprintf('%s: %s is a post class; a task stores the ID of a post (int $postId) and the handler loads the post.', $taskLocation, $task),
            default => null,
        };
        if ($error !== null || $task === null) {
            $this->errors[] = (string) $error;

            return null;
        }

        /** @var class-string $task A class: enums, interfaces and missing classes fail as value classes below. */
        $fields = $this->values->objectFields($task, $taskLocation);
        if ($fields === null) {
            // The value schema builder reports why.
            return null;
        }

        $errors = $this->referenceErrors($fields, 'task', $taskLocation);
        $name = self::taskName($task);
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $errors[] = sprintf('%s: the task name "%s" derived from the class name %s must consist of lowercase letters, digits and hyphens and start with a letter; rename the class.', $taskLocation, $name, $task);
        }

        /** @var list<CronArgument> $arguments */
        $arguments = [];
        foreach (array_slice($parameters, 1) as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
            $error = $this->inspector->parameterError($parameter, $parameterLocation, 'cron tasks');
            $service = $this->resolver->injectedType($parameter);
            if ($error === null && $service === null) {
                $error = sprintf('%s: the parameters after the task of a #[CronTask] method must be services.', $parameterLocation);
            }
            if ($error !== null || $service === null) {
                $errors[] = (string) $error;
                continue;
            }
            $arguments[] = ['param' => $parameter->getName(), 'service' => $service];
        }

        if ($errors !== []) {
            array_push($this->errors, ...$errors);

            return null;
        }

        /** @var non-empty-string $name Checked against NAME_PATTERN. */
        return new CronTaskDefinition($name, $task, $class, $method->getName(), $first->getName(), $arguments);
    }

    /**
     * References at any depth of a task: the value factory resolves them with the visibility rules of the front end,
     * so a draft would become null in the background.
     *
     * @param array<string, Field> $fields
     * @return list<string>
     */
    private function referenceErrors(array $fields, string $path, string $location): array
    {
        $errors = [];
        foreach ($fields as $name => $field) {
            $value = $field['value'];
            $fieldPath = $path . '.' . $name;
            if ($value['type'] === 'list') {
                $value = $value['items'];
                $fieldPath .= '[*]';
            }
            if (isset($value['reference'])) {
                $errors[] = sprintf(
                    '%s: "%s" is a reference (#[PostReference], #[TermReference], #[UserReference] or a post class), which resolves with the visibility rules of the front end; a task stores the ID as int and the handler loads the object.',
                    $location,
                    $fieldPath,
                );
                continue;
            }
            if ($value['type'] === 'object') {
                $nested = $this->values->objectFields($value['class'], $location);
                if ($nested !== null) {
                    array_push($errors, ...$this->referenceErrors($nested, $fieldPath, $location));
                }
            }
        }

        return $errors;
    }

    private static function invalid(string $attribute, string $location, Throwable $exception): string
    {
        $message = $exception->getMessage();

        return sprintf(
            'Invalid %s on %s: %s%s',
            $attribute,
            $location,
            $message,
            str_contains($message, 'Undefined constant')
                ? ' (attribute arguments are evaluated in the build, where constants WordPress defines, such as MINUTE_IN_SECONDS, do not exist; write the number itself)'
                : '',
        );
    }
}
