<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a submission counts for. Mirrors CompetenceResult in the Android app,
 * but the app's copy is only ever a local display of what it last read from
 * here — the app computes a provisional result to show the learner, and the
 * backend ignores it. Competence is decided by an assessor, in Phase 4.
 */
enum CompetenceResult: string
{
    case NOT_YET_ASSESSED = 'not_yet_assessed';
    case REQUIRES_IMPROVEMENT = 'requires_improvement';
    case COMPETENT = 'competent';

    public function label(): string
    {
        return match ($this) {
            self::NOT_YET_ASSESSED => 'Not yet assessed',
            self::REQUIRES_IMPROVEMENT => 'Requires improvement',
            self::COMPETENT => 'Competent',
        };
    }
}
