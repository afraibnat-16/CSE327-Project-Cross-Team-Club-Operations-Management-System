<?php

namespace App\Patterns\Facade;

use App\Enums\Office;
use App\Enums\Stage;
use App\Patterns\Adapter\SponsorData;
use App\Patterns\Composite\Checklists;

// FACADE PATTERN
// Behind the scenes there is a stage order, per-stage checklists (Composite)
// and approval rules. Screens should not have to know any of that. They make
// one call, evaluate($sponsor), and get back a simple StageReport.

/**
 * Facade: ONE easy method that hides the stage order, the checklists and the
 * Composite behind it. Screens never touch Checklists directly.
 */
final class StageTracker
{
    // The normal path a sponsor follows. Cancelled is not in it on purpose:
    // it is a dead end you can jump to, not a step on the way.
    private const ORDER = [
        Stage::Negotiation, Stage::Approval, Stage::Logistics,
        Stage::Ready, Stage::OnCampus, Stage::Completed,
    ];

    // The main entry point: where is this sponsor, and what is still missing?
    public function evaluate(SponsorData $sponsor): StageReport
    {
        $current = $sponsor->stage();
        $checklist = Checklists::toLeave($current);   // the rules for leaving this stage

        if ($checklist === null) {            // Completed or Cancelled
            return new StageReport($current, null, false, [], []);
        }

        $missing = $checklist->missing($sponsor);

        return new StageReport(
            $current,
            $this->nextAfter($current),
            $checklist->passes($sponsor),   // can move on only if every rule passes
            $missing,
            $this->warnings($sponsor),
        );
    }

    /** The stage this sponsor should be in now: the next one if allowed, otherwise the same. */
    public function stageAfterChecks(SponsorData $sponsor): Stage
    {
        $report = $this->evaluate($sponsor);

        return $report->canAdvance ? $report->next : $report->current;
    }

    // Finds the stage that comes after $stage in ORDER (null if it is the last one).
    private function nextAfter(Stage $stage): ?Stage
    {
        $position = array_search($stage, self::ORDER, true);

        return self::ORDER[$position + 1] ?? null;
    }

    // Problems worth showing that do NOT stop the sponsor from moving on.
    private function warnings(SponsorData $sponsor): array
    {
        $warnings = [];
        // Electricity needs Ops & Management's go-ahead, so flag it while still in Logistics.
        if ($sponsor->stage() === Stage::Logistics
            && $sponsor->requiresElectricity()
            && $sponsor->approvalStatus(Office::OpsManagement) !== 'approved') {
            $warnings[] = 'Electricity is needed but Operations & Management has not approved yet.';
        }

        return $warnings;
    }
}
