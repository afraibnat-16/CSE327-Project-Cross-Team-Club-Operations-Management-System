# DAY 3: Proxy, Observer, and the Corporate screens

> **Instructions for Claude Code.** Read `CLAUDE.md` first. Work on ONE step at a time. When a step says "create" or "replace", use exactly the files and contents shown (do not rename, restructure or add features). Add short plain-language comments to the code in each step, but only comments, never changing what the code does (code shown without comments should get a short comment above each class and each non-obvious method). After each step: run `php -l` on every PHP file you touched, tell me what you did, and STOP. Wait for me to say "next". Do not commit or push unless I ask. Run `vendor/bin/pint` only in Step 11. Do not touch any file not named in the step except where the step says to check or fix something specific.

## Goal

Stage changes can now be saved safely, logged, and announced to the next team, and the Corporate team has working screens. Two more patterns are built, hand-written in `app/Patterns/` (no Laravel Events, Policies, or `app()->singleton()` for pattern parts).

| Pattern | Classes | Purpose |
| --- | --- | --- |
| Proxy | `SponsorServiceInterface`, `SponsorService`, `SponsorServiceProxy`, `AccessDenied` | The proxy checks the member's team before the real service acts. It replaces Laravel Policies. |
| Observer | `StageChangePublisher`, `StageObserver`, `ActivityLogObserver`, `MemberNotifier` | When a stage changes, every observer is told: one logs it, one leaves notices for the next team |
| Singleton (update) | `ActivityLogger` | Now also saves each entry to the `activity_logs` table |

Rules built in:
- Corporate (and Coordinator) can create sponsors, edit them, finalize and cancel deals. Every team can add notes. Anyone else gets `AccessDenied`.
- Finalizing a complete deal moves the sponsor Negotiation to Approval automatically and tells the Admin team.
- A sponsor already on campus cannot be cancelled.
- Every stage change and every blocked attempt is written to the activity log.

## Part A: Patterns (Steps 1 to 6)

## Step 1: New table and model checks

1. Create a `notices` table, migration and `App\Models\Notice` model: `id`, `team` (string), `sponsor_id` (nullable foreign key to sponsors), `message` (string), `read_at` (nullable timestamp), timestamps. `$fillable` = `team`, `sponsor_id`, `message`, `read_at`. Give `Notice` a `sponsor()` relationship.
2. Check these `$fillable` lists and add ONLY what is missing, reporting each change:
   - `Sponsor`: all sponsor columns, including `stage` and `deal_status`.
   - `Approval`: `sponsor_id`, `office`, `status`, `submitted_on`.
   - `Representative`: `sponsor_id`, `name`, `phone`.
   - `Note`: `sponsor_id`, `user_id`, `body`, `follow_up_on`.
   - `ActivityLog`: `sponsor_id`, `user_id`, `action`, `details`.
3. Check `Sponsor` has a `notes()` relationship and a `representatives()` relationship; add if missing.
4. Run `php artisan migrate:fresh --seed` and show me it finishes without errors.

Suggested commit message when I ask: `Add notices table and check model fillables`

## Step 2: Actor, stage owner, and the updated logger

Create `app/Patterns/Support/Actor.php` (a small object saying who is acting), replace `app/Enums/Stage.php` (adds `owner()`: which team acts in each stage), and replace `app/Patterns/Singleton/ActivityLogger.php` (adds `setWriter()` so entries can be saved to the database).


`app/Patterns/Support/Actor.php`

```php
<?php
namespace App\Patterns\Support;

use App\Enums\Team;
use App\Models\User;

/**
 * Who is doing something. A small plain object, so the pattern classes
 * never need to know about the User database model.
 */
final class Actor
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Team $team,
    ) {}

    // Build an Actor from the logged-in member (the users table).
    public static function fromUser(User $user): self
    {
        $team = $user->team instanceof Team ? $user->team : Team::from($user->team);

        return new self($user->id, $user->name, $team);
    }
}
```

`app/Enums/Stage.php`

```php
<?php
namespace App\Enums;

enum Stage: string
{
    case Negotiation = 'negotiation';
    case Approval    = 'approval';
    case Logistics   = 'logistics';
    case Ready       = 'ready';
    case OnCampus    = 'on_campus';
    case Completed   = 'completed';
    case Cancelled   = 'cancelled';

    // The name shown on screens.
    public function label(): string
    {
        return match ($this) {
            self::OnCampus => 'On campus',
            default        => ucfirst($this->value),
        };
    }

    // The team that has to act while a sponsor is in this stage.
    public function owner(): ?Team
    {
        return match ($this) {
            self::Negotiation                       => Team::Corporate,
            self::Approval                          => Team::Admin,
            self::Logistics, self::Ready, self::OnCampus => Team::Provision,
            self::Completed, self::Cancelled        => null,
        };
    }
}
```

`app/Patterns/Singleton/ActivityLogger.php`

```php
<?php
namespace App\Patterns\Singleton;

use Closure;

/**
 * Singleton: only ONE logger exists, so every part of the app writes to the same log.
 */
final class ActivityLogger
{
    private static ?ActivityLogger $instance = null;
    private array $entries = [];
    private ?Closure $writer = null;   // optional: saves each entry somewhere (the database)

    private function __construct() {}   // nobody outside can call new
    private function __clone() {}

    public static function getInstance(): ActivityLogger
    {
        return self::$instance ??= new ActivityLogger();
    }

    // Tell the logger where to save entries. Pass null to stop saving.
    public function setWriter(?Closure $writer): void
    {
        $this->writer = $writer;
    }

    public function log(?int $sponsorId, ?int $userId, string $action, string $details = ''): void
    {
        $entry = compact('sponsorId', 'userId', 'action', 'details');
        $this->entries[] = $entry;

        if ($this->writer !== null) {
            ($this->writer)($entry);
        }
    }

    public function entries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
```

Then run `php artisan test` and show me the result. The earlier tests must still pass.

Suggested commit message when I ask: `Add Actor, Stage owner and logger writer`

## Step 3: Observer pattern

Create these five files exactly.


`app/Patterns/Observer/StageChange.php`

```php
<?php
namespace App\Patterns\Observer;

use App\Enums\Stage;
use App\Patterns\Support\Actor;

/** What happened: a sponsor moved from one stage to another. */
final class StageChange
{
    public function __construct(
        public readonly int $sponsorId,
        public readonly string $sponsorName,
        public readonly Stage $from,
        public readonly Stage $to,
        public readonly Actor $actor,
    ) {}
}
```

`app/Patterns/Observer/StageObserver.php`

```php
<?php
namespace App\Patterns\Observer;

/** Anything that wants to be told when a sponsor changes stage. */
interface StageObserver
{
    public function update(StageChange $change): void;
}
```

`app/Patterns/Observer/StageChangePublisher.php`

```php
<?php
namespace App\Patterns\Observer;

/**
 * The subject. Observers sign up with attach(); when a stage changes,
 * notify() tells every one of them. The publisher knows nothing about what they do.
 */
final class StageChangePublisher
{
    /** @var StageObserver[] */
    private array $observers = [];

    public function attach(StageObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(StageObserver $observer): void
    {
        $this->observers = array_values(array_filter(
            $this->observers,
            fn (StageObserver $o) => $o !== $observer
        ));
    }

    public function notify(StageChange $change): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($change);
        }
    }
}
```

`app/Patterns/Observer/ActivityLogObserver.php`

```php
<?php
namespace App\Patterns\Observer;

use App\Patterns\Singleton\ActivityLogger;

/** Observer 1: writes every stage change to the activity log. */
final class ActivityLogObserver implements StageObserver
{
    public function __construct(private ActivityLogger $logger) {}

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
```

`app/Patterns/Observer/MemberNotifier.php`

```php
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

    public function update(StageChange $change): void
    {
        $message = $change->sponsorName . ' moved from ' . $change->from->label()
            . ' to ' . $change->to->label() . '.';

        foreach ($this->teamsToTell($change->to) as $team) {
            ($this->store)($team->value, $change->sponsorId, $message);
        }
    }

    /** @return Team[] */
    private function teamsToTell(Stage $stage): array
    {
        return match ($stage) {
            Stage::Approval  => [Team::Admin],
            Stage::Logistics => [Team::Provision, Team::Admin],
            Stage::Ready, Stage::OnCampus, Stage::Cancelled => [Team::Coordinator],
            Stage::Completed => [Team::Coordinator, Team::Corporate],
            default          => [],
        };
    }
}
```

Suggested commit message when I ask: `Add Observer pattern for stage changes`

## Step 4: Proxy pattern

Create these four files exactly. `SponsorService` is the real worker; it has no permission checks on purpose, because `SponsorServiceProxy` does them.


`app/Patterns/Proxy/SponsorServiceInterface.php`

```php
<?php
namespace App\Patterns\Proxy;

use App\Patterns\Support\Actor;

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

    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void;
}
```

`app/Patterns/Proxy/AccessDenied.php`

```php
<?php
namespace App\Patterns\Proxy;

use RuntimeException;

/** Thrown by the proxy when a team is not allowed to do something. */
final class AccessDenied extends RuntimeException
{
}
```

`app/Patterns/Proxy/SponsorServiceProxy.php`

```php
<?php
namespace App\Patterns\Proxy;

use App\Enums\Team;
use App\Patterns\Singleton\ActivityLogger;
use App\Patterns\Support\Actor;

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
        'finalizeDeal'  => [Team::Corporate, Team::Coordinator],
        'cancelDeal'    => [Team::Corporate, Team::Coordinator],
        'addNote'       => [Team::Corporate, Team::Admin, Team::Provision, Team::Coordinator],
    ];

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
    private function check(string $action, Actor $actor, ?int $sponsorId = null): void
    {
        if (in_array($actor->team, self::RULES[$action], true)) {
            return;
        }

        $this->logger->log($sponsorId, $actor->id, 'access_denied', $actor->team->value . ' tried: ' . $action);

        throw new AccessDenied('The ' . $actor->team->value . ' team cannot do this.');
    }
}
```

`app/Patterns/Proxy/SponsorService.php`

```php
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
use Illuminate\Support\Facades\DB;

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

    public function createSponsor(array $data, Actor $actor): int
    {
        $representative = $data['representative'] ?? null;
        unset($data['representative']);

        $sponsor = DB::transaction(function () use ($data, $representative) {
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

    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);
        $sponsor->update($data);

        $this->logger->log($sponsorId, $actor->id, 'sponsor_updated', $sponsor->organization_name);
        $this->refreshStage($sponsor, $actor);
    }

    public function finalizeDeal(int $sponsorId, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);
        $sponsor->update(['deal_status' => 'finalized']);

        $this->logger->log($sponsorId, $actor->id, 'deal_finalized', $sponsor->organization_name);
        $this->refreshStage($sponsor, $actor);
    }

    public function cancelDeal(int $sponsorId, Actor $actor): void
    {
        $sponsor = Sponsor::findOrFail($sponsorId);

        if (in_array($sponsor->stage, [Stage::OnCampus, Stage::Completed], true)) {
            throw new DomainException('A sponsor already on campus cannot be cancelled.');
        }

        $from = $sponsor->stage;
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

        $this->logger->log($sponsorId, $actor->id, 'note_added', mb_substr($body, 0, 80));
    }

    // Ask the Facade if the sponsor may move on. Keep moving while the checks keep passing.
    private function refreshStage(Sponsor $sponsor, Actor $actor): void
    {
        do {
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
```

If `SponsorService` fails because a model relationship or column has a different name from the one used, fix the name in `SponsorService` and tell me which one.

Suggested commit message when I ask: `Add Proxy pattern and sponsor service`

## Step 5: Connect the pieces

Create `app/Services/Services.php`. This is the only place that builds the logger, observers, service and proxy together. Screens will call `Services::sponsors()`.


`app/Services/Services.php`

```php
<?php
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notice;
use App\Patterns\Facade\StageTracker;
use App\Patterns\Observer\ActivityLogObserver;
use App\Patterns\Observer\MemberNotifier;
use App\Patterns\Observer\StageChangePublisher;
use App\Patterns\Proxy\SponsorService;
use App\Patterns\Proxy\SponsorServiceInterface;
use App\Patterns\Proxy\SponsorServiceProxy;
use App\Patterns\Singleton\ActivityLogger;

/**
 * The one place that connects all the pieces. Screens call Services::sponsors()
 * and never build the pieces themselves.
 */
final class Services
{
    public static function sponsors(): SponsorServiceInterface
    {
        // The Singleton logger saves each entry to the activity_logs table.
        $logger = ActivityLogger::getInstance();
        $logger->setWriter(fn (array $e) => ActivityLog::create([
            'sponsor_id' => $e['sponsorId'],
            'user_id'    => $e['userId'],
            'action'     => $e['action'],
            'details'    => $e['details'],
        ]));

        // The Observer publisher, with its two observers.
        $publisher = new StageChangePublisher();
        $publisher->attach(new ActivityLogObserver($logger));
        $publisher->attach(new MemberNotifier(
            fn (string $team, int $sponsorId, string $message) => Notice::create([
                'team'       => $team,
                'sponsor_id' => $sponsorId,
                'message'    => $message,
            ])
        ));

        // The real service, wrapped in the permission-checking proxy.
        $real = new SponsorService(new StageTracker(), $publisher, $logger);

        return new SponsorServiceProxy($real, $logger);
    }
}
```

Suggested commit message when I ask: `Wire the sponsor service together`

## Step 6: Tests

Create these two test files exactly. The unit tests need no database (11 tests). The feature tests use the database and the seeder (4 tests).


`tests/Unit/Patterns/PatternsDay3Test.php`

```php
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

    public function createSponsor(array $data, Actor $actor): int { $this->calls[] = 'createSponsor'; return 1; }
    public function updateSponsor(int $sponsorId, array $data, Actor $actor): void { $this->calls[] = 'updateSponsor'; }
    public function finalizeDeal(int $sponsorId, Actor $actor): void { $this->calls[] = 'finalizeDeal'; }
    public function cancelDeal(int $sponsorId, Actor $actor): void { $this->calls[] = 'cancelDeal'; }
    public function addNote(int $sponsorId, string $body, ?string $followUpOn, Actor $actor): void { $this->calls[] = 'addNote'; }
}

/** An observer that just counts how often it was told. */
class CountingObserver implements StageObserver
{
    public int $told = 0;

    public function update(StageChange $change): void { $this->told++; }
}

class PatternsDay3Test extends TestCase
{
    protected function setUp(): void
    {
        // The logger is a Singleton, so start every test with it empty.
        ActivityLogger::getInstance()->clear();
        ActivityLogger::getInstance()->setWriter(null);
    }

    private function change(Stage $from, Stage $to): StageChange
    {
        return new StageChange(7, 'Kite Coffee', $from, $to, new Actor(1, 'Rafi', Team::Corporate));
    }

    // ---------- Singleton (updated) ----------

    public function test_logger_hands_every_entry_to_its_writer(): void
    {
        $saved = [];
        $logger = ActivityLogger::getInstance();
        $logger->setWriter(function (array $entry) use (&$saved) { $saved[] = $entry; });

        $logger->log(7, 1, 'note_added', 'Called the sponsor');

        $this->assertCount(1, $saved);
        $this->assertSame('note_added', $saved[0]['action']);
    }

    // ---------- Observer ----------

    public function test_publisher_tells_every_observer(): void
    {
        $a = new CountingObserver();
        $b = new CountingObserver();
        $publisher = new StageChangePublisher();
        $publisher->attach($a);
        $publisher->attach($b);

        $publisher->notify($this->change(Stage::Negotiation, Stage::Approval));

        $this->assertSame(1, $a->told);
        $this->assertSame(1, $b->told);
    }

    public function test_a_detached_observer_is_not_told(): void
    {
        $a = new CountingObserver();
        $publisher = new StageChangePublisher();
        $publisher->attach($a);
        $publisher->detach($a);

        $publisher->notify($this->change(Stage::Negotiation, Stage::Approval));

        $this->assertSame(0, $a->told);
    }

    public function test_activity_log_observer_writes_a_stage_change(): void
    {
        $logger = ActivityLogger::getInstance();
        (new ActivityLogObserver($logger))->update($this->change(Stage::Negotiation, Stage::Approval));

        $entry = $logger->entries()[0];
        $this->assertSame('stage_changed', $entry['action']);
        $this->assertSame('Negotiation to Approval', $entry['details']);
    }

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

    public function test_notifier_tells_provision_and_admin_at_logistics(): void
    {
        $teams = [];
        $notifier = new MemberNotifier(function (string $team) use (&$teams) { $teams[] = $team; });

        $notifier->update($this->change(Stage::Approval, Stage::Logistics));

        $this->assertSame(['provision', 'admin'], $teams);
    }

    // ---------- Proxy ----------

    public function test_proxy_passes_an_allowed_call_through(): void
    {
        $fake  = new FakeSponsorService();
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        $proxy->finalizeDeal(7, new Actor(1, 'Rafi', Team::Corporate));

        $this->assertSame(['finalizeDeal'], $fake->calls);
    }

    public function test_proxy_blocks_a_team_that_is_not_allowed(): void
    {
        $fake  = new FakeSponsorService();
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        try {
            $proxy->finalizeDeal(7, new Actor(2, 'Tasnim', Team::Admin));
            $this->fail('The proxy should have blocked this.');
        } catch (AccessDenied $e) {
            $this->assertSame([], $fake->calls);   // the real service was never reached
        }
    }

    public function test_proxy_logs_a_blocked_attempt(): void
    {
        $logger = ActivityLogger::getInstance();
        $proxy  = new SponsorServiceProxy(new FakeSponsorService(), $logger);

        try {
            $proxy->cancelDeal(7, new Actor(3, 'Nila', Team::Provision));
        } catch (AccessDenied $e) {
        }

        $this->assertSame('access_denied', $logger->entries()[0]['action']);
    }

    public function test_every_team_may_add_a_note(): void
    {
        $fake  = new FakeSponsorService();
        $proxy = new SponsorServiceProxy($fake, ActivityLogger::getInstance());

        foreach (Team::cases() as $team) {
            $proxy->addNote(7, 'Called them', null, new Actor(1, 'Someone', $team));
        }

        $this->assertCount(4, $fake->calls);
    }

    // ---------- Stage owner ----------

    public function test_each_stage_names_the_team_that_acts(): void
    {
        $this->assertSame(Team::Corporate, Stage::Negotiation->owner());
        $this->assertSame(Team::Admin, Stage::Approval->owner());
        $this->assertSame(Team::Provision, Stage::Logistics->owner());
        $this->assertNull(Stage::Completed->owner());
    }
}
```

`tests/Feature/SponsorServiceTest.php`

```php
<?php
namespace Tests\Feature;

use App\Enums\Stage;
use App\Enums\Team;
use App\Models\Sponsor;
use App\Models\User;
use App\Patterns\Builder\SponsorBuilder;
use App\Patterns\Proxy\AccessDenied;
use App\Patterns\Support\Actor;
use App\Services\Services;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function newSponsorData(): array
    {
        $data = (new SponsorBuilder())
            ->name('Test Co')
            ->contact('Rafi Ahmed', '01700000000')
            ->amount(50000)
            ->build();

        $data['representative'] = ['name' => 'Rafi Ahmed', 'phone' => '01700000000'];

        return $data;
    }

    public function test_finalizing_a_complete_deal_moves_it_to_approval(): void
    {
        $this->seed();
        $user    = User::firstOrFail();
        $actor   = new Actor($user->id, $user->name, Team::Corporate);
        $service = Services::sponsors();

        $id = $service->createSponsor($this->newSponsorData(), $actor);
        $service->finalizeDeal($id, $actor);

        $this->assertSame(Stage::Approval, Sponsor::findOrFail($id)->stage);
        $this->assertDatabaseHas('activity_logs', ['sponsor_id' => $id, 'action' => 'stage_changed']);
        $this->assertDatabaseHas('notices', ['sponsor_id' => $id, 'team' => 'admin']);
    }

    public function test_creating_a_sponsor_adds_three_pending_approvals(): void
    {
        $this->seed();
        $user  = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Corporate);

        $id = Services::sponsors()->createSponsor($this->newSponsorData(), $actor);

        $this->assertSame(3, Sponsor::findOrFail($id)->approvals()->where('status', 'pending')->count());
    }

    public function test_admin_cannot_finalize_a_deal(): void
    {
        $this->seed();
        $user  = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Admin);

        $this->expectException(AccessDenied::class);

        Services::sponsors()->finalizeDeal(Sponsor::firstOrFail()->id, $actor);
    }

    public function test_cancelling_a_sponsor_sets_the_cancelled_stage(): void
    {
        $this->seed();
        $user    = User::firstOrFail();
        $actor   = new Actor($user->id, $user->name, Team::Corporate);
        $service = Services::sponsors();

        $id = $service->createSponsor($this->newSponsorData(), $actor);
        $service->cancelDeal($id, $actor);

        $this->assertSame(Stage::Cancelled, Sponsor::findOrFail($id)->stage);
    }
}
```

Then run `php artisan test` and show me the full result. Expected: everything from Days 1 and 2 plus these 15 tests, all green. If a feature test fails because of a name or column that differs from my code, fix the name in the real code, NOT in the test, and tell me what you changed. If anything else fails, show me the error and propose the smallest fix before changing code.

Suggested commit message when I ask: `Add tests for Proxy and Observer`

## Part B: Corporate screens (Steps 7 to 10)

> **Before Step 7:** the design PDF below is not in the repository yet (checked after Step 5). Add it at exactly this path, or the screens cannot be matched to the design.

The design is in `docs/ui-prototype/CAPTrack_UI_Prototype_v2.pdf`: PDF pages 2 to 4 are the Corporate screens (page 1 is the cover). Read the page for each step before building it. Match the layout, colours and wording as closely as is reasonable. Use Livewire components and Tailwind only. Do not call the database for changes directly from a screen: every change goes through `Services::sponsors()` with `Actor::fromUser(auth()->user())`, and if it throws `AccessDenied` (or `DomainException`), show the message in a red banner on the page.

## Step 7: Shared layout and Corporate navigation

1. Add the design colours to the Tailwind theme and use them everywhere: blue as the base (buttons, links, sidebar), teal for done or approved, amber for waiting or needs action, red only for cancelled. Take the exact shades from the PDF.
2. Create one shared layout with a sidebar and top bar (page 2 of the PDF). The sidebar menu depends on the logged-in member's team. For Corporate: Dashboard, Sponsor tracker, Add sponsor. Other teams can have a placeholder "Dashboard" link for now.
3. After login, send each member to their team home: Corporate to `/corporate`, others to their own placeholder for now.
4. Add a route group `/corporate` limited to the Corporate and Coordinator teams (a simple middleware that redirects everyone else). Routes: `/corporate` (dashboard), `/corporate/sponsors` (tracker), `/corporate/sponsors/create`, `/corporate/sponsors/{sponsor}`.

Show me the page in plain text (the route list and files you made). Do not build the screens yet.

## Step 8: Corporate dashboard (PDF page 2)

Build the dashboard as a Livewire component:
- Four summary cards: sponsors secured this month, secured this year, in negotiation, cancelled. "Secured" means deal status is finalized.
- A "Follow-ups due" card: notes whose `follow_up_on` is today or earlier, or within the next 7 days, newest first, each linking to its sponsor.
- A "Needs action" card: unread notices for the Corporate team (from the `notices` table), with a "Mark as read" button that sets `read_at`.
- A simple bar chart of sponsors secured per month, made with plain Tailwind-sized `div` bars (no chart library).
Keep the page uncluttered: no more than four blocks.

## Step 9: Sponsor tracker (PDF page 3)

Build the list as a Livewire component:
- A table with: organization, type, agreed amount, a deal-status chip (In negotiation = amber, Finalized = teal, Cancelled = red), stage name (`Stage::label()`), and the team that has it now (`Stage::owner()`, or a dash).
- A search box (organization name) and a deal-status filter, both updating live.
- Each row links to `/corporate/sponsors/{sponsor}`.

## Step 10: Sponsor entry and notes (PDF page 4)

Build create and edit as one Livewire component:
- Left side, the form: organization name, type, contact name and phone and email, agreed amount, items, setup needs, "requires electricity" checkbox, representative name and phone. Create uses `SponsorBuilder` to build the data (put the representative under the `representative` key) and then `createSponsor()`. Edit uses `updateSponsor()`.
- A deal-status control with three choices (In negotiation / Finalized / Cancelled). Choosing Finalized calls `finalizeDeal()`; choosing Cancelled asks for confirmation and calls `cancelDeal()`.
- Right side, a notes panel: a text box, an optional follow-up date, an "Add note" button calling `addNote()`, and the list of earlier notes (author, date, text, follow-up date).
- Show what is still missing for the next stage, using `StageTracker` through `EloquentSponsorAdapter` (`$report->missing`), as a short amber list, e.g. "Agreed amount entered".

## Step 11: Check, tidy, finish

1. Run `php artisan test` and show me the result. Everything must be green.
2. Run `vendor/bin/pint` once, then `php artisan test` again.
3. Tell me how to click through this check in the browser, then I will do it myself: log in as the Corporate member, add a sponsor with an amount and a representative, mark it Finalized, and see it move to Approval in the tracker. Then log in as the Admin member and confirm the dashboard placeholder loads.

Suggested commit message when I ask: `Add Corporate screens`

## Done when

- `php artisan test` is fully green (Day 1 and 2 tests, plus 15 new ones).
- A Corporate member can add a sponsor, add a note with a follow-up date, finalize the deal, and watch it move to Approval.
- An Admin member trying a Corporate-only action gets a clear message, and the blocked attempt appears in `activity_logs`.
- A notice for the Admin team appears in the `notices` table after finalizing.
- Pint has been run and nothing else was changed.

Do NOT build the Admin, Provision or Coordinator screens. Those are Days 4 to 6.
