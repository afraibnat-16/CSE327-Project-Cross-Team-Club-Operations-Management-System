<?php
namespace App\Patterns\Proxy;

use App\Patterns\Support\Actor;

// PROXY PATTERN - the shared "subject" interface.
// Every action also receives the Actor (who is doing it), so the proxy can check their team.

/**
 * The actions the screens can ask for. Both the real service and the proxy
 * offer exactly these, so a screen cannot tell which one it is talking to.
 */
interface SponsorServiceInterface
{
    /** @return int the new sponsor's id */
    public function createSponsor(array $data, Actor $actor): int;

    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void;

    public function finalizeDeal(int $sponsorId, Actor $actor): void;

    public function cancelDeal(int $sponsorId, Actor $actor): void;

    // $followUpOn is a date like "2026-12-01", or null for no follow-up.
    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void;
}
