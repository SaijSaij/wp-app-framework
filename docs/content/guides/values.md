# Values

Block attribute classes, nested value classes and `#[ListOf]` REST arguments share one schema derivation (build) and one value factory (runtime, without reflection).

- **Value class:** a `readonly` class whose values are all public promoted constructor parameters and that declares no other properties. Value classes are the exception to the rule that properties are never public.
- **Types:** `int`, `float`, `string`, `bool`, backed enums (schema `enum` with the case values), value classes (schema `object` with `properties`) and `array` with `#[ListOf(Enum::class)]` or `#[ListOf(ValueClass::class)]` (schema `array` with `items`), each optionally nullable (`type: [..., 'null']`). Lists of scalars are not supported yet. Enums and value classes must be in the scanned PSR-4 directories; recursive value classes are build errors.
- **Defaults** become the schema `default` (enums as their value, value objects as arrays). Inside a nested value class a parameter without default is `required`. Unknown keys in nested objects are ignored, like WordPress ignores unknown block attributes.
- **`#[Url]`** on a string adds `format: uri` (sanitized with `sanitize_url()`).
- **Conversion:** missing values use the constructor default; a value that does not match its type throws an `InvalidArgumentException` naming the path, e.g. `buttons[1].url` – values are never coerced silently.

See [Blocks](./blocks.md#attribute-class) and [REST routes](./rest.md#parameters) for how blocks and routes use them.
