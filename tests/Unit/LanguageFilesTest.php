<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every shipped language must have exactly the English keys and placeholders, so a new text cannot be forgotten in
 * one language and a translated message cannot lose a value.
 */
#[CoversNothing]
final class LanguageFilesTest extends TestCase
{
    private const string LANG_DIR = __DIR__ . '/../../lang';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        $dirs = glob(self::LANG_DIR . '/*', GLOB_ONLYDIR);
        foreach ($dirs === false ? [] : $dirs as $dir) {
            $locale = basename($dir);
            if ($locale !== 'en') {
                yield $locale => [$locale];
            }
        }
    }

    #[Test]
    #[DataProvider('locales')]
    public function it_has_the_english_keys_and_placeholders(string $locale): void
    {
        $english = self::flatten($this->messages('en'));
        $translated = self::flatten($this->messages($locale));

        self::assertSame(array_keys($english), array_keys($translated), "Keys of lang/{$locale} differ from lang/en.");

        foreach ($english as $key => $text) {
            self::assertSame($this->placeholders($text), $this->placeholders($translated[$key] ?? ''), "Placeholders of {$locale}: {$key}");
        }
    }

    /**
     * @return array<mixed>
     */
    private function messages(string $locale): array
    {
        $messages = require self::LANG_DIR . "/{$locale}/messages.php";
        self::assertIsArray($messages);

        return $messages;
    }

    /**
     * @param array<mixed> $messages
     *
     * @return array<string, string>
     */
    private static function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $value) {
            $path = $prefix . $key;
            if (\is_array($value)) {
                $flat += self::flatten($value, $path . '.');
            } else {
                self::assertIsString($value, $path);
                $flat[$path] = $value;
            }
        }

        ksort($flat);

        return $flat;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $matches);
        $found = $matches[0];
        sort($found);

        return $found;
    }
}
