<?php

namespace App\Patterns\Facade;

use App\Enums\Stage;

// The result that StageTracker hands back: a small, read-only bundle of answers.
// "readonly" means the values are set once here and can never be changed later.

/** The simple answer the Facade hands back. */
final class StageReport
{
    /**
     * @param  string[]  $missing  what still blocks the next stage
     * @param  string[]  $warnings  things to watch, but that do not block
     */
    public function __construct(
        public readonly Stage $current,     // the stage the sponsor is in now
        public readonly ?Stage $next,       // the stage after it (null at the end)
        public readonly bool $canAdvance,   // true when nothing is missing
        public readonly array $missing,
        public readonly array $warnings,
    ) {}
}
