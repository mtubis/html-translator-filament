<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One row per (record, attribute, target locale). Publish it with
| `php artisan vendor:publish --tag=html-translator-filament-migrations`.
| An application that already has a `translation_states` table with this schema keeps it: the migration is a no-op.
*/
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('translation_states')) {
            return;
        }

        Schema::create('translation_states', static function (Blueprint $table): void {
            $table->id();
            $table->morphs('translatable');
            $table->string('attribute');
            $table->string('locale', 10);
            $table->string('status')->default('missing');
            // sha256 of the source text a translation was made from; compared with the current source to detect
            // stale translations without storing a separate flag.
            $table->char('source_hash', 64)->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('profile')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'attribute', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_states');
    }
};
