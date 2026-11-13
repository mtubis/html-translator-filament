# Releasing

Versions follow [Semantic Versioning](https://semver.org/). While the version is 0.x, a minor version may break the
public API, and the CHANGELOG says how. Releases are cut from `main`.

## Checklist
1. **Green build**: `main` is up to date and `composer check` passes locally and in CI (PHP 8.4/8.5, lowest and
   highest dependencies).
2. **Dogfooding**: the application that uses the plugin through its path repository passes its own suite against
   this `main`.
3. **CHANGELOG**: move `[Unreleased]` into `## [X.Y.Z] - YYYY-MM-DD` with a one-line summary, and update the link
   references at the bottom.
4. **Constraints**: `mtubis/html-translator-laravel` stays `^0.2` until the plugin uses a newer minor.
5. **Commit** (`docs: release X.Y.Z`), push, wait for CI.
6. **Tag and publish** (maintainer):

```bash
git switch main && git pull
git tag -a vX.Y.Z -m "X.Y.Z"
git push origin vX.Y.Z
gh release create vX.Y.Z --title "X.Y.Z" \
    --notes "$(awk '/^## \[X.Y.Z\]/{f=1;next} f&&/^(## \[|\[[^]]+\]: )/{exit} f' CHANGELOG.md)"
```

## First release only: Packagist
1. Sign in at [packagist.org](https://packagist.org) with the GitHub account that owns the repository.
2. **Submit** `https://github.com/mtubis/html-translator-filament`. The package name comes from `composer.json`.
3. Enable automatic updates (the Packagist GitHub app or the webhook), so later tags appear on their own.
4. Check that `composer require mtubis/html-translator-filament` resolves `v0.1.0` in a fresh Laravel 13 + Filament 5
   application.
