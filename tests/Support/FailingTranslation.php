<?php

declare(strict_types=1);

namespace HtmlTranslator\Filament\Tests\Support;

use HtmlTranslator\Exception\TranslatorException;
use RuntimeException;

/**
 * Thrown from a canned fake response to make a single translation fail (the fake's failWith() fails all of them).
 */
final class FailingTranslation extends RuntimeException implements TranslatorException {}
