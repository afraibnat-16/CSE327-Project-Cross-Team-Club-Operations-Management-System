<?php

namespace Tests\Unit\Patterns;

use App\Enums\Stage;
use App\Enums\Team;
use App\Patterns\Observer\ActivityLogObserver;
use App\Patterns\Observer\MemberNotifier;
use App\Patterns\Observer\StageChange;
use App\Patterns\Observer\StageChangePublisher;
use App\Patterns\Observer\StageObserver;
use App\Patterns\Proxy\AccessDenied;
use App\Patterns\Proxy\SponsorServiceInterface;
use App\Patterns\Proxy\SponsorServiceProxy;
use App\Patterns\Singleton\ActivityLogger;
use App\Patterns\Support\Actor;
use PHPUnit\Framework\TestCase;

/** A stand-in for the real service that just remembers which actions were called. */
class FakeSponsorService implements SponsorServiceInterface
{
    public array $calls = [];

    public function createSponsor(array $data, Actor $actor): int
    {
        $this->calls[] = 'createSponsor';

        return 1;
    }

    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void
    {
        $this->calls[] = 'updateSponsor';
    }

    public function finalizeDeal(int $sponsorId, Actor $actor): void
    {
        $this->calls[] = 'finalizeDeal';
    }

    public function cancelDeal(int $sponsorId, Actor $actor): void
    {
        $this->calls[] = 'cancelDeal';
    }

    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void
    {
        $this->calls[] = 'addNote';
    }
}

/** An observer that just counts how often it was told. */
class CountingObserver implements StageObserver
{
    public int $told = 0;

    public function update(StageChange $change): void
    {
        $this->told++;
    }
}

// Tests for the Day 3 patterns. No database is needed: the proxy is tested with a
// fake service, and the observers with simple functions that collect what they get.
class PatternsDay3Test extends TestCase
{
    protected function setUp(): void
    {
        // The logger is a Singleton, so start every test with it empty.
        ActivityLogger::getInstance()->clear();
        ActivityLogger::getInstance()->setWriter(null);
    }

    // A ready-made "Kite Coffee moved from X to Y" message for the tests below.
    private function change(Stage $from, Stage $to): StageChange
    {
        return new StageChange(7, 'Kite Coffee', $from, $to, new Actor(1, 'Rafi', Team::Corporate));
    }

    // ---------- Singleton (updated) ----------

    // Every logged entry is also handed to the writer function (which saves it).
    public function test_logger_hands_every_entry_to_its_writer(): void
    {
        $saved = [];
        $logger = ActivityLogger::getInstance();
        $logger->setWriter(function (array $entry) use (&$saved) {
            $saved[] = $entry;
        });

        $logger->log(7, 1, 'note_added', 'Called the sponsor');

        $this->assertCount(1, $saved);
        $this->assertSame('note_added', $saved[0]['action']);
    }

    // ---------- Observer ----------

    // Two attached observers are both told once.
    public function test_publisher_tells_every_observer(): void
    {
        $a = new CountingObserver;
        $b = new CountingObserver;
        $publisher = new StageChangePublisher;
        $publisher->attach($a);
        $publisher->attach($b);

        $publisher->notify($this->change(Stage::Negotiation, Stage::Approval));

        $this->assertSame(1, $a->told);
        $this->assertSame(1, $b->told);
    }

    // After detach(), the observer hears nothing.
    public function test_a_detached_observer_is_not_told(): void
    {
        $a = new CountingObserver;
        $publisher = new StageChangePublisher;
        $publisher->attach($a);
        $publisher->detach($a);

        $publisher->notify($this->change(Stage::Negotiation, Stage::Approval));

        $this->assertSame(0, $a->told);
    }

    // The log observer writes "Negotiation to Approval" as a stage_changed entry.
    public function test_activity_log_observer_writes_a_stage_change(): void
    {
        $logger = ActivityLogger::getInstance();
        (new ActivityLogObserver($logger))->update($this->change(Stage::Negotiation, Stage::Approval));

        $entry = $logger->entries()[0];
        $this->assertSame('stage_changed', $entry['action']);
        $this->assertSame('Negotiation to Approval', $entry['details']);
    }

    // Reaching Approval leaves exactly one notice, for the Admin team.
    public function test_notifier_tells_admin_when_a_sponsor_reaches_approval(): void
    {
        $notices = [];
        $notifier = new MemberNotifier(function (string $team, int $id, string $msg) use (&$notices) {
            $notices[] = [$team, $id, $msg];
        });

        $notifier->update($this->change(Stage::Negotiation, Stage::Approval));

        $this->assertCount(1, $notices);
        $this->assertSame('admin', $notices[0][0]);
        $this->assertSame('Kite Coffee moved from Negotiation to Approval.', $notices[0][2]);
    }

    // Reaching Logistics tells Provision first, then Admin.
    public function test_notifier_tells_provision_and_admin_at_logistics(): void
    {
        $teams = [];
        $notifier = new MemberNotifier(function (string $team) use (&$teams) {
            $teams[] = $team;
        });

        $notifier->update($this->change(Stage::Approval, Stage::Logistics));

        $this->assertSame(['provision', 'admin'], $teams);
    }

    // ---------- Proxy ----------

    // Corporate is allowed to finalize, so the call reaches the (fake) real service.
    public function test_proxy_passes_an_allowed_call_through(): void
    {
        $fake = new FakeSponsorService;
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        $proxy->finalizeDeal(7, new Actor(1, 'Rafi', Team::Corporate));

        $this->assertSame(['finalizeDeal'], $fake->calls);
    }

    // Admin is not allowed, so AccessDenied is thrown and the real service is never called.
    public function test_proxy_blocks_a_team_that_is_not_allowed(): void
    {
        $fake = new FakeSponsorService;
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        try {
            $proxy->finalizeDeal(7, new Actor(2, 'Tasnim', Team::Admin));
            $this->fail('The proxy should have blocked this.');
        } catch (AccessDenied $e) {
            $this->assertSame([], $fake->calls);   // the real service was never reached
        }
    }

    // A blocked attempt leaves an "access_denied" entry in the log.
    public function test_proxy_logs_a_blocked_attempt(): void
    {
        $logger = ActivityLogger::getInstance();
        $proxy = new SponsorServiceProxy(new FakeSponsorService, $logger);

        try {
            $proxy->cancelDeal(7, new Actor(3, 'Nila', Team::Provision));
        } catch (AccessDenied $e) {
        }

        $this->assertSame('access_denied', $logger->entries()[0]['action']);
    }

    // Notes are open to all four teams.
    public function test_every_team_may_add_a_note(): void
    {
        $fake = new FakeSponsorService;
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        foreach (Team::cases() as $team) {
            $proxy->addNote(7, 'Called them', null, new Actor(1, 'Someone', $team));
        }

        $this->assertCount(4, $fake->calls);
    }

    // ---------- Stage owner ----------

    // Each stage names the team that acts in it; finished stages have none.
    public function test_each_stage_names_the_team_that_acts(): void
    {
        $this->assertSame(Team::Corporate, Stage::Negotiation->owner());
        $this->assertSame(Team::Admin, Stage::Approval->owner());
        $this->assertSame(Team::Provision, Stage::Logistics->owner());
        $this->assertNull(Stage::Completed->owner());
    }
}
