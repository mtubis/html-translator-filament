<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Unit;

use HtmlTranslator\Filament\Enums\OverwritePolicy;
use HtmlTranslator\Filament\Enums\TranslationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OverwritePolicy::class)]
final class OverwritePolicyTest extends TestCase
{
    #[Test]
    public function each_policy_allows_exactly_its_states(): void
    {
        $matrix = [];
        foreach (OverwritePolicy::cases() as $policy) {
            foreach ([null, ...TranslationStatus::cases()] as $status) {
                $matrix[$policy->value][$status->value ?? 'none'] = $policy->allows($status);
            }
        }

        self::assertSame([
            'only_missing' => ['none' => true, 'missing' => true, 'machine' => false, 'reviewed' => false],
            'overwrite_machine' => ['none' => true, 'missing' => true, 'machine' => true, 'reviewed' => false],
            'force' => ['none' => true, 'missing' => true, 'machine' => true, 'reviewed' => true],
        ], $matrix);
    }
}
