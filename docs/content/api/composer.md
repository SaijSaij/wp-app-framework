# composer.json

The framework reads these keys of the `composer.json` of your theme or plugin. Nothing else is configured there; everything else is code.

| Key | Required | Meaning |
|---|---|---|
| `type` | yes | `wordpress-theme` for themes and child themes, `wordpress-plugin` for plugins. See [Boot](../guides/boot.md#the-kind-of-project). |
| `extra.wp-app-framework.namespace` | yes, for a project with a build | The namespace of the content and data of the project: lowercase letters, digits and `-`, starting with a letter; not `core`, `wp` or `wp-app-framework`. See [Concepts](../concepts.md#consumers-and-instances). |
| `extra.wp-app-framework.template-plugins` | no | Slugs of the plugins whose public Twig functions and filters the templates call by name. See [Plugins](../guides/plugins.md#public-template-functions-and-filters). |
| `extra.strauss` | yes, for a release | The settings of Strauss, which prefixes the dependencies in the release. The release command checks `target_directory` (`vendor-prefixed`), `namespace_prefix`, `include_root_autoload`, `update_call_sites` and `delete_vendor_packages`. See [Release](../build-and-release/release.md#strauss-settings). |

A minimal theme:

```json
{
    "name": "vendor/my-theme",
    "type": "wordpress-theme",
    "require": { "webcodingcat/wp-app-framework": "^0.15" },
    "extra": {
        "wp-app-framework": { "namespace": "my-theme" }
    }
}
```

[Getting started](../getting-started.md) shows the complete file with the Strauss settings and scripts.
