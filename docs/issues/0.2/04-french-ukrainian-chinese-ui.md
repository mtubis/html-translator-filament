# French, Ukrainian and Chinese (Mandarin) interface translations

GitHub: https://github.com/mtubis/html-translator-filament/issues/5

## Context
0.2 ships English, Spanish, German and Polish. Filament itself is translated into French (`fr`), Ukrainian (`uk`) and
Simplified Chinese (`zh_CN`), so panels in these languages show the plugin's texts in English today. Reviewers
available now are non-native speakers who studied the languages; native input is welcome later.

## Scope
- `lang/fr`, `lang/uk`, `lang/zh_CN` with every key of `lang/en`, in the terminology of Filament's own files for these
  languages and the formal address Filament uses (vous, ви, 您). Same concepts as the reviewed German and Polish
  files: a machine translation is a *draft*, a stale one is *not current*.
- Plural forms by Laravel's rules: French (0 and 1 singular), Ukrainian (three forms), Chinese (one form). Neutral
  wording where a count would make a sentence awkward.
- README "Interface languages": a review status per language (native speaker / fluent speaker / non-native speaker,
  native input welcome) and how to contribute corrections. Spanish moves to "non-native speaker, native input
  welcome" until the site's founders review it.
- A review sheet per language (English source, translation, where the text appears) for the reviewers.

## Acceptance criteria
- [ ] The language-file parity test passes for every locale; plural tests cover fr (0, 1, 2), uk (1, 2, 5, 11, 21,
      22, 25) and zh_CN.
- [ ] Each new file's header and the README state the review status.
- [ ] Reviewers' corrections applied before the 0.2.0 release.
- [ ] `composer check` green.
