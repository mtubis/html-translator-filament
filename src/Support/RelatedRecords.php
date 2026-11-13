<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Support;

use HtmlTranslator\Filament\Contracts\TracksTranslations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The records of the named relations that are translated (or reviewed) together with a record. Relations are
 * opt-in, never guessed; a name that is not a relation, or a relation to models without translation states, is an
 * error rather than silently ignored.
 */
final class RelatedRecords
{
    /**
     * @param list<string> $relations
     *
     * @return list<Model&TracksTranslations>
     */
    public static function of(Model $record, array $relations): array
    {
        $models = [];
        foreach ($relations as $name) {
            if (! $record->isRelation($name)) {
                throw new InvalidArgumentException(\sprintf('html-translator: "%s" is not a relation of %s.', $name, $record::class));
            }

            // Fresh from the database: a relation loaded earlier may be stale.
            $results = $record->unsetRelation($name)->getRelationValue($name);
            foreach ($results instanceof Collection ? $results->all() : [$results] as $related) {
                if ($related === null) {
                    continue;
                }

                if (! $related instanceof Model || ! $related instanceof TracksTranslations) {
                    throw new InvalidArgumentException(\sprintf(
                        'html-translator: the relation "%s" returns %s, which does not implement TracksTranslations.',
                        $name,
                        get_debug_type($related),
                    ));
                }

                $models[] = $related;
            }
        }

        return $models;
    }
}
