<?php

namespace App\Patterns\Proxy;

use App\Enums\Team;
use App\Patterns\Singleton\ActivityLogger;
use App\Patterns\Support\Actor;

// PROXY PATTERN - the "proxy" (a protection proxy).
// It has the same methods as the real service. Each method first checks the
// team, and only then hands the call to the real service ($real).

/**
 * Proxy: stands in front of the real service. It checks that the member's team
 * is allowed to do the action, then passes the call on. This replaces Laravel Policies.
 */
final class SponsorServiceProxy implements SponsorServiceInterface
{
    // Which teams may do each action.
    private const RULES = [
        'createSponsor' => [Team::Corporate, Team::Coordinator],
        'updateSponsor' => [Team::Corporate, Team::Coordinator],
        'finalizeDeal' => [Team::Corporate, Team::Coordinator],
        'cancelDeal' => [Team::Corporate, Team::Coordinator],
        'addNote' => [Team::Corporate, Team::Admin, Team::Provision, Team::Coordinator],
    ];

    // $real is the service that does the work; $logger records blocked attempts.
    public function __construct(
        private SponsorServiceInterface $real,
        private ActivityLogger $logger,
    ) {}

    public function createSponsor(array $data, Actor $actor): int
    {
        $this->check('createSponsor', $actor);

        return $this->real->createSponsor($data, $actor);
    }

    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void
    {
        $this->check('updateSponsor', $actor, $sponsorId);
        $this->real->updateSponsor($sponsorId, $data, $actor);
    }

    public function finalizeDeal(int $sponsorId, Actor $actor): void
    {
        $this->check('finalizeDeal', $actor, $sponsorId);
        $this->real->finalizeDeal($sponsorId, $actor);
    }

    public function cancelDeal(int $sponsorId, Actor $actor): void
    {
        $this->check('cancelDeal', $actor, $sponsorId);
        $this->real->cancelDeal($sponsorId, $actor);
    }

    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void
    {
        $this->check('addNote', $actor, $sponsorId);
        $this->real->addNote($sponsorId, $body, $followUpOn, $actor);
    }

    // Stop the call, and write it to the log, if this team is not allowed.
    // "return" lets an allowed call carry on; "throw" stops a blocked one,
    // so the real service is never reached.
    private function check(string $action, Actor $actor, ?int $sponsorId = null): void
    {
        if (in_array($actor->team, self::RULES[$action], true)) {
            return;
        }

        $this->logger->log($sponsorId, $actor->id, 'access_denied', $actor->team->value.' tried: '.$action);

        throw new AccessDenied('The '.$actor->team->value.' team cannot do this.');
    }
}
