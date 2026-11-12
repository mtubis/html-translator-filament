<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Feature\Tables;

use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Tables\Columns\TranslationStatusColumn;
use HtmlTranslator\Filament\Tests\TestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

#[CoversClass(TranslationStatusColumn::class)]
final class TranslationStatusColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        app()->setLocale('es');
    }

    #[Test]
    public function a_record_with_an_untranslated_text_is_missing(): void
    {
        $post = Post::factory()->create();
        resolve(TranslationStateStore::class)->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);

        $this->assertStatus($post, 'en:missing', 'EN · Sin traducir');
    }

    #[Test]
    public function machine_translations_show_as_machine(): void
    {
        $post = $this->translated('machine');

        $this->assertStatus($post, 'en:machine', 'EN · Automática');
    }

    #[Test]
    public function reviewed_translations_show_as_reviewed(): void
    {
        $post = $this->translated('reviewed');

        $this->assertStatus($post, 'en:reviewed', 'EN · Revisada');
    }

    #[Test]
    public function a_changed_source_shows_as_stale_even_when_reviewed(): void
    {
        $post = $this->translated('reviewed');
        $post->setTranslation('title', 'es', 'Otro título')->save();

        $this->assertStatus($post, 'en:stale', 'EN · Desactualizada');
    }

    private function translated(string $status): Post
    {
        $post = Post::factory()->create();
        $store = resolve(TranslationStateStore::class);
        $store->markMachine($post, 'title', 'en', 'Clases de inglés', null, null);
        $store->markMachine($post, 'body', 'en', '<p>Aprende <strong>inglés</strong> con nosotras.</p>', null, null);
        if ($status === 'reviewed') {
            $store->markReviewed($post, 'title', 'en', null);
            $store->markReviewed($post, 'body', 'en', null);
        }

        return $post;
    }

    private function assertStatus(Post $post, string $state, string $label): void
    {
        Livewire::test(ListPosts::class)
            ->assertTableColumnStateSet('translation_status', [$state], $post)
            ->assertSee($label);
    }
}
