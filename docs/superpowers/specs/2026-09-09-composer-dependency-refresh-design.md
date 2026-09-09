# Composer dependency refresh design

## Scope

Refresh the Composer dependency graph for issue #412 from `origin/main`. Keep
the existing PHP 8.5 and Symfony 7.4 compatibility boundaries, and do not
introduce beta-only internal bundle versions. Update direct dependencies to
the latest compatible patch or minor releases and allow Composer to refresh
their compatible transitive dependencies.

## Approach

Use Composer as the dependency resolver rather than editing `composer.lock`
manually. Run the update against the existing stable constraints with all
dependencies enabled, inspect the resulting package changes for unintended
major or runtime changes, and keep the change limited to Composer manifests
unless a dependency exposes a directly related compatibility problem.

## Validation

Validate `composer.json` and `composer.lock`, confirm no compatible direct
dependencies remain outdated, and run the repository's QA, static-analysis,
unit-test, and Behat commands. A failed check must remain visible and be
resolved or reported rather than being suppressed.
