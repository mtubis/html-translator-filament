<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Models\Concerns\HasTranslationStates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;
use Workbench\Database\Factories\CommentFactory;

/**
 * Test fixture for relations translated together with their parent.
 *
 * @property int $id
 * @property int $post_id
 * @property string|null $text
 */
final class Comment extends Model implements TracksTranslations
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;
    use HasTranslations;
    use HasTranslationStates;

    /** @var list<string> */
    public array $translatable = ['text'];

    protected $fillable = ['post_id', 'text'];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
