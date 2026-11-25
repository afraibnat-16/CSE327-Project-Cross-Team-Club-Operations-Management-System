<?php
namespace App\Patterns\Composite;

use App\Enums\Office;
use App\Enums\Stage;
use App\Patterns\Adapter\SponsorData;

// The real CAPTrack rules, built from CheckItems and CheckGroups.
// Ask for a stage, get back everything needed to move on to the next stage.

/** The checklist a sponsor must complete to LEAVE each stage. */
final class Checklists
{
    // Returns null for Completed and Cancelled, because there is no next stage.
    public static function toLeave(Stage $stage): ?Check
    {
        return match ($stage) {
            // Negotiation -> Approval: the deal is done and someone will come.
            Stage::Negotiation => new CheckGroup('Ready for approval', [
                new CheckItem('Deal marked as finalized', fn (SponsorData $s) => $s->dealFinalized()),
                new CheckItem('Agreed amount entered', fn (SponsorData $s) => $s->hasAgreedAmount()),
                new CheckItem('Representative added', fn (SponsorData $s) => $s->hasRepresentative()),
            ]),

            // Approval -> Logistics: only Student Affairs has to approve.
            Stage::Approval => new CheckGroup('Ready for logistics', [
                new CheckItem('Student Affairs approval received',
                    fn (SponsorData $s) => $s->approvalStatus(Office::StudentAffairs) === 'approved'),
            ]),

            // Logistics -> Ready: setup done, plus the two remaining approvals.
            // A group inside a group: this is what makes it a Composite.
            Stage::Logistics => new CheckGroup('Ready for event day', [
                new CheckItem('Booth setup arranged', fn (SponsorData $s) => $s->setupArranged()),
                new CheckGroup('Remaining approvals', [
                    new CheckItem('Operations & Management approval received',
                        fn (SponsorData $s) => $s->approvalStatus(Office::OpsManagement) === 'approved'),
                    new CheckItem('Gate passes received',
                        fn (SponsorData $s) => $s->approvalStatus(Office::GatePass) === 'approved'),
                ]),
            ]),

            // Ready -> On Campus: they have arrived.
            Stage::Ready => new CheckGroup('Ready to arrive', [
                new CheckItem('Arrived on campus', fn (SponsorData $s) => $s->arrived()),
            ]),

            // On Campus -> Completed: they have left on every event day.
            Stage::OnCampus => new CheckGroup('Ready to complete', [
                new CheckItem('Left the campus', fn (SponsorData $s) => $s->left()),
            ]),

            // The end of the line: nothing left to check.
            Stage::Completed, Stage::Cancelled => null,
        };
    }
}
