<?php

namespace App\Patterns\Observer;

use App\Enums\Stage;
use App\Enums\Team;
use Closure;

/**
 * Observer 2: leaves a notice for each team that must act next.
 * It is given a function that saves a notice, so it works with or without a database.
 */
final class MemberNotifier implements StageObserver
{
    // $store receives: team (string), sponsor id (int), message (string)
    public function __construct(private Closure $store) {}

    // Called by the publisher. Builds one message and stores it for each team to tell.
    public function update(StageChange $change): void
    {
        $message = $change->sponsorName.' moved from '.$change->from->label()
            .' to '.$change->to->label().'.';

        foreach ($this->teamsToTell($change->to) as $team) {
            ($this->store)($team->value, $change->sponsorId, $message);
        }
    }

    // Which teams need to hear about a sponsor arriving in this stage.
    // Moving back to Negotiation (the "default" case) tells nobody.
    /** @return Team[] */
    private function teamsToTell(Stage $stage): array
    {
        return match ($stage) {
            Stage::Approval => [Team::Admin],
            Stage::Logistics => [Team::Provision, Team::Admin],
            Stage::Ready, Stage::OnCampus, Stage::Cancelled => [Team::Coordinator],
            Stage::Completed => [Team::Coordinator, Team::Corporate],
            default => [],
        };
    }
}
