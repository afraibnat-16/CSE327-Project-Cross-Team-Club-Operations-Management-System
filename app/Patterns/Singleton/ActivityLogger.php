<?php

namespace App\Patterns\Singleton;

use Closure;

// SINGLETON PATTERN
// There must be only ONE activity logger in the whole app, so every part of
// the app writes to the same log. We make sure of that by:
//   1. making the constructor private, so nobody can write "new ActivityLogger()";
//   2. keeping the one object in a static variable;
//   3. handing out that same object from getInstance().
// New on Day 3: an optional "writer" function that also saves each entry
// somewhere, e.g. to the activity_logs table. The logger itself never talks
// to the database, so it still works in tests without one.

/**
 * Singleton: only ONE logger exists, so every part of the app writes to the same log.
 */
final class ActivityLogger
{
    // The one and only object. Empty (null) until someone first asks for it.
    private static ?ActivityLogger $instance = null;

    // The log lines collected so far (in memory).
    private array $entries = [];

    private ?Closure $writer = null;   // optional: saves each entry somewhere (the database)

    private function __construct() {}   // nobody outside can call new

    private function __clone() {}       // nobody can make a copy with clone

    // Returns the single logger. Creates it the first time, reuses it after.
    public static function getInstance(): ActivityLogger
    {
        // "??=" means: if $instance is still null, create it; then return it.
        return self::$instance ??= new ActivityLogger;
    }

    // Tell the logger where to save entries. Pass null to stop saving.
    public function setWriter(?Closure $writer): void
    {
        $this->writer = $writer;
    }

    // Records one action, e.g. log(3, 1, 'stage_changed', 'Approval to Logistics').
    public function log(?int $sponsorId, ?int $userId, string $action, string $details = ''): void
    {
        // compact() builds ['sponsorId' => ..., 'userId' => ..., ...] from the variables.
        $entry = compact('sponsorId', 'userId', 'action', 'details');
        $this->entries[] = $entry;

        // If a writer was given, hand it the entry so it can be saved too.
        if ($this->writer !== null) {
            ($this->writer)($entry);
        }
    }

    // Everything logged so far (in memory).
    public function entries(): array
    {
        return $this->entries;
    }

    // Empties the in-memory log (useful in tests). Saved rows are not touched.
    public function clear(): void
    {
        $this->entries = [];
    }
}
