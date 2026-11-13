<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\HtmlTranslatorFilamentServiceProvider;
use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\Post;

#[CoversNothing]
final class MigrationTest extends TestCase
{
    #[Test]
    public function it_creates_the_documented_columns(): void
    {
        self::assertSame(
            [
                'attribute', 'created_at', 'id', 'locale', 'profile', 'provider', 'reviewed_at', 'reviewed_by',
                'source_hash', 'status', 'translatable_id', 'translatable_type', 'translated_at', 'updated_at',
            ],
            collect(Schema::getColumnListing('translation_states'))->sort()->values()->all(),
        );
    }

    #[Test]
    public function publishing_gives_it_the_current_timestamp(): void
    {
        $target = database_path('migrations');
        $before = $this->published($target);

        try {
            self::assertSame(0, Artisan::call('vendor:publish', ['--tag' => HtmlTranslatorFilamentServiceProvider::MIGRATIONS_TAG]));

            $published = array_values(array_diff($this->published($target), $before));
            self::assertCount(1, $published);
            self::assertMatchesRegularExpression(
                '/^' . date('Y_m_d') . '_\d{6}_create_translation_states_table\.php$/',
                basename($published[0]),
            );
        } finally {
            foreach (array_diff($this->published($target), $before) as $file) {
                unlink($file);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function published(string $directory): array
    {
        $files = glob($directory . '/*_create_translation_states_table.php');

        return $files === false ? [] : $files;
    }

    #[Test]
    public function running_it_against_an_existing_table_changes_nothing(): void
    {
        Post::factory()->create();
        $before = DB::table('translation_states')->get()->all();

        $migration = require __DIR__ . '/../../database/migrations/2026_11_13_000000_create_translation_states_table.php';
        self::assertInstanceOf(Migration::class, $migration);
        self::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        self::assertEquals($before, DB::table('translation_states')->get()->all());
    }
}
