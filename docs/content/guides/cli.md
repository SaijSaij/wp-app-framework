# WP-CLI commands

Some tasks don't belong in a web request: importing a feed with thousands of entries, cleaning up old data, migrating content after a change of its structure. [WP-CLI](https://wp-cli.org/) runs such tasks from the command line, without a time limit and without a browser.

A plain WP-CLI command is a class or closure that you register with `WP_CLI::add_command()`. It receives its arguments as an array of strings, and its help text and the rules for its arguments live in a doc comment that WP-CLI parses. WP-CLI only checks the form of the arguments: `--limit=many` reaches your code as the string `"many"`.

With the framework, a command is a method with `#[CliSubcommand]`. The build derives the command line from the method signature, and the framework converts every value to the declared type before your method runs. Like a hook or a REST route, the class is a [service](./services.md): it gets its dependencies injected and is created only when the command runs.

## Commands, groups and the namespace

Every command has three parts: `wp {namespace} {command} {subcommand}`. The namespace is the one of your theme or plugin (`extra.wp-app-framework.namespace`, see [Concepts](../concepts.md#consumers-and-instances)). The command is a group named by `#[CliCommand]` on a class, the subcommand a method of that class.

The namespace comes first, so commands of different themes and plugins never collide with each other. WP-CLI itself would not prevent that. A second command with an existing name is silently merged with the first one, and one of them loses its description or subcommands.

```sh
wp sections                         # lists the groups of the theme
wp sections events                  # lists the subcommands of the group
wp sections events import <url>     # runs a subcommand
wp help sections events import      # shows its help
```

## A first command

Start with a class that imports events. `#[CliCommand]` gives the group its name and a one-line description:

```php
use WebCodingCat\WpAppFramework\WpCli\CliCommand;

#[CliCommand('events', 'Manage the events of the theme.')]
final class EventCommands
{
    public function __construct(private readonly EventImporter $importer) {}
}
```

Each subcommand is a public method that returns `void`. `#[CliSubcommand]` names it and describes it in one line; `longDescription` adds text that the help shows after the options, typically examples:

```php
use WebCodingCat\WpAppFramework\WpCli\Argument;
use WebCodingCat\WpAppFramework\WpCli\CliException;
use WebCodingCat\WpAppFramework\WpCli\CliOutput;
use WebCodingCat\WpAppFramework\WpCli\CliSubcommand;
use WebCodingCat\WpAppFramework\WpCli\Option;

#[CliSubcommand('import', 'Import events from a feed.', longDescription: "## EXAMPLES\n\n    wp sections events import https://example.com/feed --limit=10")]
public function import(
    CliOutput $output,
    #[Argument('URL of the feed.')]
    string $url,
    #[Option('Maximum number of events.')]
    int $limit = 50,
    #[Option('How existing events are treated.')]
    ImportMode $mode = ImportMode::Merge,
    #[Option('Only show what would be imported.')]
    bool $dryRun = false,
): void {
    $count = $this->importer->import($url, $limit, $mode, $dryRun);
    if ($count === 0) {
        throw new CliException('The feed contained no events.', 2);
    }
    $output->success(sprintf('%d events imported.', $count));
}
```

With the namespace `sections`, this is the command `wp sections events import <url> [--limit=<limit>] [--mode=<mode>] [--dry-run]`. Read the signature from left to right:

- `CliOutput $output` receives the output of the command; the framework passes it in.
- `#[Argument] string $url` is a positional argument, `<url>`. It has no default, so it is required.
- `#[Option] int $limit = 50` is an option, `--limit=<limit>`. Without it, the method receives the default 50; with `--limit=many`, the command stops with `Error: Invalid value for --limit: limit is not of type integer.` before your method runs.
- `#[Option] ImportMode $mode` is a backed enum. The help lists its values, and only these are accepted; the method receives the enum case.
- `#[Option] bool $dryRun = false` is a flag, `--dry-run`. WP-CLI also understands `--no-dry-run`.

A parameter typed with a service, such as a repository, is injected like in a hook. Each parameter must be one of these kinds; a parameter that is neither the output nor a service nor marked with `#[Argument]` or `#[Option]` is a build error. The marker is required so that a typo in a type can't turn a service into an argument without you noticing.

## Arguments and options

Parameter names become kebab-case: `$dryRun` and `$dry_run` both become `--dry-run`, so they can't appear in the same method. The names can't be changed otherwise; rename the parameter instead.

A positional argument with a default value is optional, `[<count>]`. A variadic argument takes all remaining values and needs at least one:

```php
#[CliSubcommand('delete', 'Delete events.')]
public function delete(CliOutput $output, #[Argument('IDs of the events.')] int ...$ids): void
{
    // wp sections events delete 12 13 14
}
```

An option without default value is required: WP-CLI stops with `missing --mode parameter` when it is left out. A nullable option or argument needs a default value, usually `null`; the method receives the default when the value is not given.

The values are checked and converted by the same rules as [REST arguments](./rest.md#parameters): `"5"` becomes the integer 5, `"0.5"` the float 0.5, and a backed enum accepts exactly its values. WP-CLI itself would also accept a comma-separated list of enum values such as `--mode=merge,replace`; the framework rejects it, because the parameter takes one value.

## Output and errors

Write output through `CliOutput`, never with `echo`, so that WP-CLI can control it:

| Method | Output |
|---|---|
| `log(string $message)` | the message on stdout |
| `success(string $message)` | `Success: {message}` on stdout |
| `warning(string $message)` | `Warning: {message}` on stderr |
| `table(array $rows, array $fields)` | the rows as a table with the given columns |

`--quiet` suppresses the messages. `CliOutput` is passed to the method; it is no service, so a constructor can't ask for it.

To end a command with an error, throw a `CliException`. WP-CLI prints `Error: {message}` and exits with the exit code of the exception, 1 by default. Scripts that call your command can tell different failures apart by the exit code; it must be between 1 and 255.

Any other exception is unexpected. The framework writes it to the PHP error log with the command and the method. With `WP_DEBUG` enabled, it rethrows the exception, so PHP reports it with its stack trace; WordPress then ends the command with its message about a critical error. Without `WP_DEBUG`, the command ends with `Error: Command failed: {exception class}: {message}`. In both cases the exit code is 1, unless the site disables the fatal error handler of WordPress.

:::warning
- **Development mode writes a cache.** The framework builds the manifest on the first run and caches it in `wp-content/cache/{slug}/`. Run `wp` as the user of the web server (e.g. `docker compose exec -u www-data …`), not with `--allow-root`: files that root creates there can't be updated by the web server afterwards.
- **Commands come with their theme or plugin.** WP-CLI loads WordPress with the active theme and plugins, which register their commands. With `--skip-themes` or `--skip-plugins`, the commands of the skipped consumers don't exist.
- **The namespace must be free.** If another plugin or package already registers a command with the name of your namespace, the framework doesn't register your commands and prints a warning instead. The build rejects a namespace that is a command of WP-CLI itself, such as `post` or `cache`.
:::

## Rules

| Attribute | Arguments | Target |
|---|---|---|
| `#[CliCommand(name, description)]` | name of the group, one-line description | a service class |
| `#[CliSubcommand(name, description, longDescription: '')]` | name, one-line description, text after the options in the help; repeatable | a public method of that class |
| `#[Argument(description: '')]` | description in the help | a parameter: positional argument `<name>` |
| `#[Option(description: '')]` | description in the help | a parameter: option `--name=<name>`, or a flag `--name` for `bool` |

| Type | As argument | As option |
|---|---|---|
| `string`, `int`, `float`, backed enum | yes, also variadic | yes |
| nullable (`?int` …) | with a default value | with a default value |
| `bool` | no | a flag, declared as `bool $name = false` |
| `array`, value classes, references, union types, `mixed` | no | no |

The build reports, with class, method and parameter:

- `#[CliSubcommand]` methods in a class without `#[CliCommand]`, or `#[CliCommand]` without `#[CliSubcommand]` methods;
- a class that is no service candidate; a method that is not public, is static or doesn't return `void`;
- a command or subcommand name that doesn't start with a lowercase letter followed by lowercase letters, digits and `-`; a group name used twice in the project, a subcommand name used twice in a group;
- a description that is empty, has a line break or more than 80 characters;
- a parameter that is neither `CliOutput`, nor a service, nor marked; a parameter with both markers; a marker on the output or a service;
- a type from the table that is not supported; `#[Param]`, `#[PostReference]`, `#[TermReference]` or `#[UserReference]`; a by-reference parameter; a variadic option;
- a nullable argument or option without default value; a flag that is not `bool $name = false`; a variadic argument after an optional one;
- two parameters with the same name in kebab-case; an option named like a global parameter of WP-CLI (`--url`, `--path`, `--user`, `--debug`, `--quiet` …) or `--help`; an option starting with `no-`, which WP-CLI reads as negated flag;
- a project namespace that is a command of WP-CLI, such as `post`, `cache` or `option`, as soon as the project has commands.
