<?php

namespace Tests\Unit\Patterns;

use App\Enums\Office;
use App\Enums\Stage;
use App\Patterns\Adapter\ArraySponsorData;
use App\Patterns\Composite\CheckItem;
use App\Patterns\Composite\Checklists;
use App\Patterns\Facade\StageTracker;
use App\Patterns\Factory\ApprovalFactory;
use App\Patterns\Factory\GatePassApproval;
use PHPUnit\Framework\TestCase;

// Tests for the Day 2 patterns. Every sponsor here is a plain array wrapped in
// ArraySponsorData (the Adapter), so no database is needed and they run fast.
class PatternsDay2Test extends TestCase
{
    // ---------- Composite ----------

    // A single rule that fails should report its own label.
    public function test_check_item_reports_what_is_missing(): void
    {
        $item = new CheckItem('Booth setup arranged', fn ($s) => $s->setupArranged());
        $sponsor = new ArraySponsorData([]);

        $this->assertFalse($item->passes($sponsor));
        $this->assertSame(['Booth setup arranged'], $item->missing($sponsor));
    }

    // The Logistics list has 1 rule plus a group of 2, so 3 items are missing in total.
    public function test_group_lists_missing_items_including_nested_ones(): void
    {
        $sponsor = new ArraySponsorData(['stage' => Stage::Logistics]);
        $checklist = Checklists::toLeave(Stage::Logistics);

        $this->assertFalse($checklist->passes($sponsor));
        $this->assertCount(3, $checklist->missing($sponsor));
    }

    // When every rule (nested ones too) is met, the whole group passes.
    public function test_group_passes_when_every_child_passes(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Logistics,
            'setup_arranged' => true,
            'approvals' => ['ops_management' => 'approved', 'security_gate_pass' => 'approved'],
        ]);

        $this->assertTrue(Checklists::toLeave(Stage::Logistics)->passes($sponsor));
    }

    // ---------- Facade ----------

    // Only the amount is missing, so that is the only thing reported.
    public function test_negotiation_is_blocked_until_amount_is_entered(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Negotiation,
            'deal_finalized' => true,
            'has_representative' => true,
        ]);

        $report = (new StageTracker)->evaluate($sponsor);

        $this->assertFalse($report->canAdvance);
        $this->assertSame(['Agreed amount entered'], $report->missing);
    }

    // Everything is in place, so the sponsor moves from Negotiation to Approval.
    public function test_complete_negotiation_moves_to_approval(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Negotiation,
            'deal_finalized' => true,
            'agreed_amount' => 50000,
            'has_representative' => true,
        ]);

        $tracker = new StageTracker;

        $this->assertTrue($tracker->evaluate($sponsor)->canAdvance);
        $this->assertSame(Stage::Approval, $tracker->stageAfterChecks($sponsor));
    }

    // Student Affairs approval alone is enough to reach Logistics.
    public function test_student_affairs_approval_opens_logistics(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Approval,
            'approvals' => ['student_affairs' => 'approved'],
        ]);

        $this->assertSame(Stage::Logistics, (new StageTracker)->stageAfterChecks($sponsor));
    }

    // Nothing approved yet, so the sponsor stays where it is.
    public function test_blocked_sponsor_stays_in_the_same_stage(): void
    {
        $sponsor = new ArraySponsorData(['stage' => Stage::Approval]);

        $this->assertSame(Stage::Approval, (new StageTracker)->stageAfterChecks($sponsor));
    }

    // Needs electricity but Ops & Management has not approved: one warning.
    public function test_logistics_warns_when_electricity_is_not_approved(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Logistics,
            'requires_electricity' => true,
        ]);

        $this->assertCount(1, (new StageTracker)->evaluate($sponsor)->warnings);
    }

    // Completed is the end: no next stage and nothing to advance to.
    public function test_completed_sponsor_has_no_next_stage(): void
    {
        $report = (new StageTracker)->evaluate(new ArraySponsorData(['stage' => Stage::Completed]));

        $this->assertNull($report->next);
        $this->assertFalse($report->canAdvance);
    }

    // ---------- Factory ----------

    // Asking for the gate pass office gives back a GatePassApproval object.
    public function test_factory_returns_the_right_kind_of_approval(): void
    {
        $this->assertInstanceOf(GatePassApproval::class, ApprovalFactory::make(Office::GatePass));
    }

    // "submitted" is not enough; Ops & Management must have "approved".
    public function test_gate_pass_is_locked_until_ops_and_management_approves(): void
    {
        $locked = new ArraySponsorData(['approvals' => ['ops_management' => 'submitted']]);
        $open = new ArraySponsorData(['approvals' => ['ops_management' => 'approved']]);
        $gate = ApprovalFactory::make(Office::GatePass);

        $this->assertFalse($gate->canSubmit($locked));
        $this->assertTrue($gate->canSubmit($open));
    }

    // makeAll() gives all three approvals in the order they happen.
    public function test_make_all_returns_three_approvals_in_order(): void
    {
        $offices = array_map(fn ($a) => $a->office(), ApprovalFactory::makeAll());

        $this->assertSame(
            [Office::StudentAffairs, Office::OpsManagement, Office::GatePass],
            $offices
        );
    }
}
