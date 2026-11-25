<?php

namespace App\Patterns\Singleton;

// SINGLETON PATTERN
// There must be only ONE activity logger in the whole app, so every part of
// the app writes to the same log. We make sure of that by:
//   1. making the constructor private, so nobody can write "new ActivityLogger()";
//   2. keeping the one object in a static variable;
//   3. handing out that same object from getInstance().
final class ActivityLogger   // "final": no child class can break the rules above
{
    // The one and only object. Empty (null) until someone first asks for it.
    private static ?ActivityLogger $instance = null;

    // The log lines collected so far.
    private array $entries = [];

    private function __construct() {}   // nobody outside can call new

    private function __clone() {}       // nobody can make a copy with clone

    // Returns the single logger. Creates it the first time, reuses it after.
    public static function getInstance(): ActivityLogger
    {
        // "??=" means: if $instance is still null, create it; then return it.
        return self::$instance ??= new ActivityLogger;
    }

    // Records one action, e.g. log(3, 1, 'stage_changed', 'Approval to Logistics').
    // Today it is kept in memory only; on Day 3 it will also save to activity_logs.
    public function log(?int $sponsorId, ?int $userId, string $action, string $details = ''): void
    {
        // compact() builds ['sponsorId' => ..., 'userId' => ..., ...] from the variables.
        $this->entries[] = compact('sponsorId', 'userId', 'action', 'details');
    }

    // Everything logged so far.
    public function entries(): array
    {
        return $this->entries;
    }

    // Empties the log (useful in tests).
    public function clear(): void
    {
        $this->entries = [];
    }
}
