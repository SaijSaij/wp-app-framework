# Contributing to wp-app-framework

The developer documentation is part of the website: **[Developing the framework](https://saijsaij.github.io/wp-app-framework/next/development/)**. It explains the architecture and the design decisions behind it, the conventions of the code, how the framework is tested and how releases go out, and has a checklist for adding a feature.

In short:

- Clone the repositories next to each other; you only need Git and Docker ([Setting up](https://saijsaij.github.io/wp-app-framework/next/development/#setting-up)). Every check runs in the tools image of [wp-test-env](https://github.com/saijsaij/wp-test-env).
- Read the [design decisions](https://saijsaij.github.io/wp-app-framework/next/development/design.html) before you propose a larger change.
- Every check passes without errors and warnings: `composer test`, `composer lint`, `npm test`, `npm run lint`, the documentation build, and before a push the browser suites of the test consumers ([Testing](https://saijsaij.github.io/wp-app-framework/next/development/testing.html)).
- A change of the consumer API or of observable behavior updates the documentation in the same commit; a breaking change before 1.0 gets a section in [Upgrading](https://saijsaij.github.io/wp-app-framework/next/reference/upgrading.html).
