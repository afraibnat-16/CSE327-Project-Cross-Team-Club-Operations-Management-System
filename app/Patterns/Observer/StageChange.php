<?php
namespace App\Patterns\Observer;

use App\Enums\Stage;
use App\Patterns\Support\Actor;

// The message every observer receives. It is read-only: observers can look
// at what happened, but cannot change it for the observers after them.

/** What happened: a sponsor moved from one stage to another. */
final class StageChange
{
    public function __construct(
        public readonly int $sponsorId,
        public readonly string $sponsorName,
        public readonly Stage $from,      // the stage it left
        public readonly Stage $to,        // the stage it moved into
        public readonly Actor $actor,     // who caused the change
    ) {}
}
