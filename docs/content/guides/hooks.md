# Hooks

Hooks are how your code talks to WordPress. In a plain theme you would call `add_filter()` in `functions.php`, create the objects your callback needs, and count the arguments it accepts. With the framework, you put `#[Hook]` on a method instead. The build finds the method, and the framework registers the hook and creates the class only when WordPress actually fires it.

## A first hook

This filter changes post titles with the help of a `Formatter` service:

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

Several things happen here without extra code:

- **Action or filter?** The method returns a `string`, so the framework registers a filter. A method that returns `void` becomes an action.
- **Services in the signature.** `Formatter` is a service (see [Services](./services.md)), so the framework passes it in. You can ask for services in the constructor too; in the method signature they are handy when only this hook needs them.
- **WordPress arguments.** All other parameters receive the arguments WordPress passes to the hook, in their order: `$title` gets the first, `$postId` the second. The framework derives `accepted_args` from them, so you never count by hand.
- **Optional arguments.** If WordPress passes fewer arguments than the method declares, the missing ones use their default value. That is why `$postId` has `= 0`.

## Several hooks on one method

`#[Hook]` is repeatable. The same method can listen to several hooks, each with its own priority:

```php
#[Hook('the_title')]
#[Hook('single_post_title', priority: 5)]
public function filter(string $title): string { /* … */ }
```

## When things go wrong

The class is created only when the hook fires for the first time, so a theme with many hooks loads only what a request uses.

If the method throws an exception, the outcome depends on `WP_DEBUG`. With `WP_DEBUG` enabled, the exception is rethrown and you see it right away. Without it, the exception is logged with `error_log()` and the site keeps running. A filter then returns the value it received, as if it had not run.

## Rules

- The method is public and non-static and declares a return type: `void` for an action, any other type for a filter.
- Parameters typed with a service candidate are injected. All other parameters receive the WordPress arguments in their order; `accepted_args` is derived from them.
- `#[Hook(name, priority: 10)]` is repeatable.
- Services are created when the hook fires. Exceptions are rethrown with `WP_DEBUG`; otherwise they are logged with `error_log()`, and filters return their original value.
