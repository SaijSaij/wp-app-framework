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
- [classes that build on WordPress](#classes-that-build-on-wordpress), such as a widget extending `WP_Widget`.

The container wires services by the types of their constructor parameters. A parameter with a scalar type (`string`, `int` …) cannot be filled from the container and must have a default value. There is exactly one instance of each service per request; every class that asks for it gets the same object.

## Classes that build on WordPress

Some WordPress features expect a class of your own that extends a class of WordPress: a widget extends `WP_Widget`, a list table in the admin extends `WP_List_Table`. Such a class cannot be loaded without WordPress. The build of a release runs without WordPress, and during development the classes of WordPress that are loaded differ from request to request (`WP_List_Table`, for example, only exists in the admin). The build therefore never loads these classes, so it gives the same result everywhere.

The build reads every class in your PSR-4 directories as text first. A class whose parent class, interfaces or traits come from outside your project is not loaded. *Outside* means: not in your PSR-4 directories or the framework, not found by the Composer autoloader of your project, and not part of PHP. That covers WordPress and other plugins. A class that extends such a class of your own is not loaded either.

These classes are not services. You create them yourself where WordPress needs them, typically in a hook:

```php
final class NoteWidget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('my-theme-note', 'Note');
    }

    public function widget($args, $instance): void
    {
        echo $args['before_widget'] . esc_html('A note') . $args['after_widget'];
    }
}
```

`NoteWidget` extends `WP_Widget`, so the build leaves it alone. A service registers it:

```php
final class Widgets
{
    #[Hook('widgets_init')]
    public function register(): void
    {
        register_widget(new NoteWidget());
    }
}
```

When the hook runs, WordPress is loaded, and Composer's autoloader loads `NoteWidget` like any other class. The [build](../build-and-release/build.md#checking) lists the classes it did not load, with the class each one builds on.

Rules:

- A class is not loaded if a class it extends, an interface it implements or a trait it uses is outside the project: not in the scanned PSR-4 directories, not found by the Composer autoloader in the project's vendor directory and not part of PHP or one of its extensions. This applies even if the class is already loaded, as `WP_Widget` is in WordPress.
- A parameter typed with such a class is a build error: the container cannot create it.
- Attributes of the framework (`#[Hook]`, `#[Block]`, `#[NoService]` …) on such a class or its members are a build error, because the build does not read them. Attributes of PHP, such as `#[\Override]`, are allowed.
- If the build itself needs such a class – as the type of a REST parameter, for example – that is a build error as well.
- Classes of your Composer dependencies are loaded. A dependency whose classes extend WordPress classes therefore fails the build with `Cannot load …`.

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
| Theme | `Saij\WpAppFramework\Theme` | `getSlug()`, `getTemplateDirectory()`, `getTemplateDirectoryUri()`, `getStylesheetDirectory()`, `getStylesheetDirectoryUri()`, `getFrameworkDirectory()` |
| Plugin | `Saij\WpAppFramework\Plugin` | `getSlug()`, `getFile()`, `getDirectory()`, `getDirectoryUri()`, `getFrameworkDirectory()` |
| Child theme | `Saij\WpAppFramework\ChildTheme` | `getSlug()`, `getParentSlug()`, `getDirectory()`, `getDirectoryUri()`, `getParentDirectory()`, `getParentDirectoryUri()`, `getFrameworkDirectory()` |

The boot method creates the object (its constructor is private) and hands it to the container, which never builds it itself. Injecting the class of another kind – `Plugin` in a theme, for example – is a build error.

The framework also provides these services. Inject them like your own; the framework creates them, so you never call their constructors:

| Class | What for |
|---|---|
| `Saij\WpAppFramework\Twig\TemplateRenderer` | Renders Twig templates in your own code, such as mails (see [Twig templates](./twig.md)). |
| `Saij\WpAppFramework\Cache\CacheStore` | Caches values of your own code (see [Cache](./cache.md)). |
| `Saij\WpAppFramework\Cron\CronScheduler` | Plans one-time tasks (see [Cron events and tasks](./cron.md)). |
| `Saij\WpAppFramework\View\ViewData` | Passes data to the front-end scripts (see [Front-end scripts](./frontend.md#data-from-php)). |
| `Saij\WpAppFramework\Asset\Assets` | URLs, paths, images and SVGs of static assets (see [Static assets](./assets.md#in-php)). |
| `Saij\WpAppFramework\Asset\MediaLibrary` | Imports a static asset into the media library (see [Static assets](./assets.md#in-the-media-library)). |

Other classes of the framework that it uses itself, such as the render context of the editor, are internal: injecting them into a class of your theme or plugin is a build error.
