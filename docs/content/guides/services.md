# Services

Every concrete class under the consumer's PSR-4 directories (`autoload.psr-4` in its `composer.json`) and under the framework's `src/` is a service candidate. Interfaces, abstract classes, traits, enums, attribute classes and classes marked `#[NoService]` (`Saij\WpAppFramework\DependencyInjection\NoService`) are not. Services are shared and autowired by constructor parameter types; scalar parameters must have a default value.

Only services reachable from entry points (`#[Hook]`, `#[RestRoute]`, `#[TemplateFunction]` and `#[TemplateFilter]` methods, including permission callbacks, and `#[Block]` classes) are built and checked, so value objects that are never injected need no marking. Aliases and tags are not supported. The container is internal; consumer code receives services through constructor and hook parameters only.

## Predefined services

- `Saij\WpAppFramework\Theme` is a predefined instance in themes: `Theme::boot()` creates it (its constructor is private) and hands it to the container, which never builds it. Services can inject it to read the paths via `getTemplateDirectory()`, `getTemplateDirectoryUri()`, `getStylesheetDirectory()`, `getStylesheetDirectoryUri()` and `getFrameworkDirectory()`.
- In plugins, `Saij\WpAppFramework\Plugin` takes its place (`getSlug()`, `getFile()`, `getDirectory()`, `getDirectoryUri()`, `getFrameworkDirectory()`).
- In child themes, `Saij\WpAppFramework\ChildTheme` (`getSlug()`, `getParentSlug()`, `getDirectory()`, `getDirectoryUri()`, `getParentDirectory()`, `getParentDirectoryUri()`, `getFrameworkDirectory()`).
- Injecting the one of another kind is a build error.
- `Saij\WpAppFramework\Twig\TemplateRenderer` is predefined as well; the framework creates it on first use (see [Twig templates](./twig.md)).
