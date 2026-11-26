<?php
namespace App\Patterns\Observer;

use App\Patterns\Singleton\ActivityLogger;

/** Observer 1: writes every stage change to the activity log. */
final class ActivityLogObserver implements StageObserver
{
    // It is given the (Singleton) logger to write to.
    public function __construct(private ActivityLogger $logger) {}

    // Called by the publisher. Logs e.g. "Negotiation to Approval".
    public function update(StageChange $change): void
    {
        $this->logger->log(
            $change->sponsorId,
            $change->actor->id,
            'stage_changed',
            $change->from->label() . ' to ' . $change->to->label()
        );
    }
}
