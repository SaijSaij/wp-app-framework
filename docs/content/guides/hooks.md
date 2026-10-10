# Hooks

```php
use Saij\WpAppFramework\Hook\Hook;

final class TitleFilter
{
    #[Hook('the_title', priority: 20)]
    public function filter(string $title, Formatter $formatter, int $postId = 0): string
    {
        return $formatter->format($title, $postId);
    }
}
```

- The method must be public and non-static and declare a return type: `void` registers an action, any other type a filter. `#[Hook]` is repeatable.
- Parameters typed with a service candidate are injected (`Formatter`, see [Services](./services.md)). All other parameters receive the WordPress arguments in their order (`$title`, `$postId`); `accepted_args` is derived from them. Missing optional arguments use the parameter default.
- Services are created when the hook fires. Exceptions are rethrown if `WP_DEBUG` is enabled; otherwise they are logged with `error_log()`, and filters return their original value.
