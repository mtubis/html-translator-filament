# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Package skeleton: `HtmlTranslatorPlugin` (panel configuration: source and target locales, profile, provider,
  queue), service provider with `en`/`es` translations, `InvalidPluginConfigurationException`.
- Testbench workbench with a Filament panel and a translatable `Post` fixture; Pint, Rector, PHPStan (max) and
  PHPUnit via `composer check`; GitHub Actions CI.
- Mutation testing (Infection, as in html-translator-laravel) is planned once there is logic worth mutating.
