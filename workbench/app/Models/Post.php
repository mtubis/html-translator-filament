<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Models\Concerns\HasTranslationStates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Workbench\Database\Factories\PostFactory;

/**
 * Test fixture: a plain-text attribute (`title`) and an HTML attribute (`body`), both translatable.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $body
 */
final class Post extends Model implements TracksTranslations
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;
    use HasTranslations;
    use HasTranslationStates;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $fillable = ['title', 'body'];

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
