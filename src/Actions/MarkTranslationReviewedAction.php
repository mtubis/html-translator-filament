<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use HtmlTranslator\Filament\Contracts\TracksTranslations;
use HtmlTranslator\Filament\Contracts\TranslationStateStore;
use HtmlTranslator\Filament\Support\ActiveLocale;
use HtmlTranslator\Filament\Support\Lang;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks the machine translations of a record (and the configured relations) in the edited locale as reviewed, with
 * the current user as reviewer. Editing a translation by hand does not review it: this action is the explicit step.
 * Works as a page header action and as a table record action.
 */
final class MarkTranslationReviewedAction extends Action
{
    /** @var list<string> */
    private array $relations = [];

    public static function getDefaultName(): string
    {
        return 'markTranslationReviewed';
    }

    /**
     * @param list<string> $relations relations reviewed together with the record
     */
    public function relations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(Lang::get('actions.review.label'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->requiresConfirmation()
            ->modalHeading(Lang::get('actions.review.heading'))
            ->hidden(static fn(mixed $livewire, ActiveLocale $active): bool => $active->isEditingSource($livewire))
            ->action(function (?Model $record, mixed $livewire, ActiveLocale $active, TranslationStateStore $states, AuthFactory $auth): void {
                $locale = $active->targetOrDefault($livewire);
                if (! $record instanceof TracksTranslations || $locale === null) {
                    return;
                }

                $userId = $auth->guard()->id();
                $reviewer = \is_int($userId) ? $userId : (is_numeric($userId) ? (int) $userId : null);

                $count = 0;
                foreach ([$record, ...$this->related($record)] as $model) {
                    $count += $states->markReviewedAll($model, $locale, $reviewer);
                }

                Notification::make()
                    ->title(Lang::choice('actions.review.done', $count))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return list<Model&TracksTranslations>
     */
    private function related(Model $record): array
    {
        $models = [];
        foreach ($this->relations as $name) {
            if (! $record->isRelation($name)) {
                continue;
            }

            $results = $record->unsetRelation($name)->getRelationValue($name);
            foreach ($results instanceof Collection ? $results->all() : [$results] as $related) {
                if ($related instanceof Model && $related instanceof TracksTranslations) {
                    $models[] = $related;
                }
            }
        }

        return $models;
    }
}
