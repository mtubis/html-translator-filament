<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature;

use HtmlTranslator\Filament\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
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
    public function running_it_against_an_existing_table_changes_nothing(): void
    {
        Post::factory()->create();
        $before = DB::table('translation_states')->get()->all();

        $migration = require __DIR__ . '/../../database/migrations/create_translation_states_table.php';
        self::assertInstanceOf(Migration::class, $migration);
        self::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        self::assertEquals($before, DB::table('translation_states')->get()->all());
    }
}
