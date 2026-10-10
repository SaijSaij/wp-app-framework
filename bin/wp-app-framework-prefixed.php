<?php

declare(strict_types=1);

// Runs a framework command with an explicit autoloader – in a release build copy the prefixed one
// (vendor-prefixed/autoload.php), so the manifest and the compiled templates use the prefixed classes.
// Strauss rewrites the class name below in the prefixed copy of this file.
// Usage: php wp-app-framework-prefixed.php <autoload.php> <project directory> <command> [arguments]
$arguments = is_array($_SERVER['argv'] ?? null) ? array_values(array_filter($_SERVER['argv'], 'is_string')) : [];
if (count($arguments) < 4) {
    fwrite(STDERR, "Usage: php wp-app-framework-prefixed.php <autoload.php> <project directory> <command> [arguments]\n");
    exit(2);
}

require $arguments[1];

exit(\Saij\WpAppFramework\Console\Cli::run(array_slice($arguments, 3), $arguments[2], STDOUT, STDERR));
