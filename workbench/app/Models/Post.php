<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Models\Concerns\HasTranslationStates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Spatie\Translatable\HasTranslations;
use Workbench\Database\Factories\PostFactory;

/**
 * Test fixture: a plain-text attribute (`title`) and an HTML attribute (`body`), both translatable.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $body
 * @property-read Collection<int, Comment> $comments
 */
final class Post extends Model implements TracksTranslations
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;
    use HasTranslations;
    use HasTranslationStates;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    /** @var list<string> attributes sent to the translator as HTML; the others are plain text */
    protected array $htmlTranslatable = ['body'];

    protected $fillable = ['title', 'body'];

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * A relation to a model that does not track translations: translating it together with a post is an error.
     *
     * @return HasMany<User, $this>
     */
    public function untracked(): HasMany
    {
        return $this->hasMany(User::class, 'id');
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
