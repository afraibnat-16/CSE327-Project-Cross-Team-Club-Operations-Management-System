<?php
namespace App\Patterns\Proxy;

use App\Enums\Stage;
use App\Models\Note;
use App\Models\Sponsor;
use App\Patterns\Adapter\EloquentSponsorAdapter;
use App\Patterns\Facade\StageTracker;
use App\Patterns\Factory\ApprovalFactory;
use App\Patterns\Observer\StageChange;
use App\Patterns\Observer\StageChangePublisher;
use App\Patterns\Singleton\ActivityLogger;
use App\Patterns\Support\Actor;
use DomainException;
use Illuminate\Support\Facades\DB;   // Laravel's database helper, used here only for a transaction

// PROXY PATTERN - the "real subject".
// This class does the actual saving. It also brings the other patterns together:
// the Factory (approval rows), the Adapter + Facade (can the stage move on?),
// the Observer (tell everyone) and the Singleton logger.

/**
 * The real service: does the work. It has no permission checks, because the
 * proxy in front of it handles those.
 */
final class SponsorService implements SponsorServiceInterface
{
    public function __construct(
        private StageTracker $tracker,
        private StageChangePublisher $publisher,
        private ActivityLogger $logger,
    ) {}

    // Saves a new sponsor in Negotiation, with three pending approvals and,
    // if given, its first representative. Returns the new sponsor's id.
    public function createSponsor(array $data, Actor $actor): int
    {
        // The representative goes in a different table, so take it out of $data.
        $representative = $data['representative'] ?? null;
        unset($data['representative']);

        // A transaction: either every row below is saved, or none of them is.
        $sponsor = DB::transaction(function () use ($data, $representative) {
            // "+" adds these two values (a new sponsor always starts here).
            $sponsor = Sponsor::create($data + [
                'stage'       => Stage::Negotiation,
                'deal_status' => 'negotiating',
            ]);

            // The factory gives us one pending row for each office.
            foreach (ApprovalFactory::makeAll() as $approval) {
                $sponsor->approvals()->create($approval->newRow());
            }

            if ($representative) {
                $sponsor->representatives()->create($representative);
            }

            return $sponsor;
        });

        $this->logger->log($sponsor->id, $actor->id, 'sponsor_created', $sponsor->organization_name);

        return $sponsor->id;
    }

    // Saves the edited fields, then checks whether the sponsor may now move on.
    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);
        $sponsor->update($data);

        $this->logger->log($sponsorId, $actor->id, 'sponsor_updated', $sponsor->organization_name);
        $this->refreshStage($sponsor, $actor);
    }

    // Marks the deal as finalized. If nothing else is missing, the sponsor
    // moves from Negotiation to Approval straight away (see refreshStage).
    public function finalizeDeal(int $sponsorId, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);
        $sponsor->update(['deal_status' => 'finalized']);

        $this->logger->log($sponsorId, $actor->id, 'deal_finalized', $sponsor->organization_name);
        $this->refreshStage($sponsor, $actor);
    }

    // Cancels the deal, unless the sponsor is already on campus or finished.
    public function cancelDeal(int $sponsorId, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);

        if (in_array($sponsor->stage, [Stage::OnCampus, Stage::Completed], true)) {
            throw new DomainException('A sponsor already on campus cannot be cancelled.');
        }

        $from = $sponsor->stage;   // remember the old stage for the message
        $sponsor->update(['deal_status' => 'cancelled', 'stage' => Stage::Cancelled]);

        $this->logger->log($sponsorId, $actor->id, 'deal_cancelled', $sponsor->organization_name);
        $this->publisher->notify(
            new StageChange($sponsor->id, $sponsor->organization_name, $from, Stage::Cancelled, $actor)
        );
    }

    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void
    {
        Note::create([
            'sponsor_id'   => $sponsorId,
            'user_id'      => $actor->id,
            'body'         => $body,
            'follow_up_on' => $followUpOn,
        ]);

        // Only the first 80 characters of the note go into the log.
        $this->logger->log($sponsorId, $actor->id, 'note_added', mb_substr($body, 0, 80));
    }

    // Ask the Facade if the sponsor may move on. Keep moving while the checks keep passing.
    // The loop stops as soon as the Facade says "stay in the same stage".
    private function refreshStage(Sponsor $sponsor, Actor $actor): void
    {
        do {
            // Reload the related rows, so the checks see the latest data.
            $sponsor->load(['approvals', 'representatives', 'eventDays']);

            $from = $sponsor->stage;
            $to   = $this->tracker->stageAfterChecks(new EloquentSponsorAdapter($sponsor));

            if ($to === $from) {
                return;
            }

            $sponsor->update(['stage' => $to]);

            // Tell every observer: the log, and the notices for the next team.
            $this->publisher->notify(
                new StageChange($sponsor->id, $sponsor->organization_name, $from, $to, $actor)
            );
        } while (true);
    }
}
