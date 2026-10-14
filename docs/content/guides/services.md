# Services

Most classes in a theme or plugin need other classes: a hook needs a formatter, a block needs the template renderer, a REST route needs a repository. You don't create these objects yourself, and you don't register them anywhere. You ask for them in the constructor, and the framework's container passes them in.

```php
final class Formatter
{
    public function format(string $title, int $postId): string { /* … */ }
}

final class TitleFilter
{
    public function __construct(private readonly Formatter $formatter) {}

    #[Hook('the_title')]
    public function filter(string $title, int $postId = 0): string
    {
        return $this->formatter->format($title, $postId);
    }
}
```

`TitleFilter` asks for a `Formatter`, so the container creates one and passes it in. `Formatter` needs no registration: it is a class in the theme's `src/`, which makes it a service.

## Which classes are services

Every concrete class under the consumer's PSR-4 directories (`autoload.psr-4` in its `composer.json`) and under the framework's `src/` is a *service candidate*. These are not:

- interfaces, abstract classes, traits and enums;
- attribute classes;
- classes marked with `#[NoService]` (`Saij\WpAppFramework\DependencyInjection\NoService`);
- [settings groups](./settings.md#using-settings). A constructor parameter typed with a settings group receives the group with its stored values instead;
- [post, meta and taxonomy classes](./posts.md#working-with-posts): they are data. Load posts with their class, `Event::get($id)`;
- classes with [`#[MetaFields]`](./queries.md#meta-fields-in-queries): their static methods provide fields for queries;
- [row classes](./tables.md) (`#[Table]`) and classes with [`#[TableFields]`](./tables.md#field-classes): rows are data. Load them with their class, `LogEntry::get($id)`.

The container wires services by the types of their constructor parameters. A parameter with a scalar type (`string`, `int` …) cannot be filled from the container and must have a default value. There is exactly one instance of each service per request; every class that asks for it gets the same object.

## Only what is used is built

You don't have to mark every class that is not meant as a service. The build starts from the *entry points* and follows their constructor parameters. Entry points are:

- methods with `#[Hook]`, `#[RestRoute]`, `#[CliSubcommand]`, `#[TemplateFunction]`, `#[TemplateFilter]`, `#[CronEvent]` or `#[CronTask]`, including the permission callbacks of REST routes;
- classes with `#[Block]`.

Only classes reachable from there are built and checked. A value object that no constructor asks for is simply ignored. A parameter of a reachable class that cannot be filled is a build error naming the class and parameter; two classes that need each other are a build error naming the chain of classes.

Services are created lazily: when the hook fires, the route is called, the WP-CLI command runs or the block renders – not when WordPress loads.

## The container stays internal

The container is internal: your code cannot inject it or ask it for a service. Aliases and tags are not supported. Your code receives services only through constructor parameters, and in hooks and REST routes also through method parameters (see [Hooks](./hooks.md) and [REST routes](./rest.md#parameters)). This keeps the dependencies of each class visible in its signature, and it lets the build check them.

## Predefined services

A few services are provided by the framework rather than built from a class of yours.

The project object describes the theme or plugin itself and gives you its paths and URLs. Which class it is depends on the kind of project:

| Project | Class | Methods |
|---|---|---|
| Theme | `Saij\WpAppFramework\Theme` | `getTemplateDirectory()`, `getTemplateDirectoryUri()`, `getStylesheetDirectory()`, `getStylesheetDirectoryUri()`, `getFrameworkDirectory()` |
| Plugin | `Saij\WpAppFramework\Plugin` | `getSlug()`, `getFile()`, `getDirectory()`, `getDirectoryUri()`, `getFrameworkDirectory()` |
| Child theme | `Saij\WpAppFramework\ChildTheme` | `getSlug()`, `getParentSlug()`, `getDirectory()`, `getDirectoryUri()`, `getParentDirectory()`, `getParentDirectoryUri()`, `getFrameworkDirectory()` |

The boot method creates the object (its constructor is private) and hands it to the container, which never builds it itself. Injecting the class of another kind – `Plugin` in a theme, for example – is a build error.

`Saij\WpAppFramework\Twig\TemplateRenderer` is predefined as well. The framework creates it on first use (see [Twig templates](./twig.md)).
