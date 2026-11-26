# DAY 2: Adapter, Composite, Factory and Facade

> **Instructions for Claude Code.** Read `CLAUDE.md` first. Work on ONE step at a time. When a step says "create", create exactly the files shown with exactly the contents shown (do not rename, restructure, add features or add extra files). After each step: run `php -l` on every new file, tell me what you created, and STOP. Wait for me to say "next" before starting the following step. Do not commit or push unless I ask. Do not touch any file not named in the step, except where a step says to check or fix something specific.

## Goal

The stage logic works with no screens. Given a sponsor, the code says which stage comes next and what is still missing. Four patterns are built, all hand-written in `app/Patterns/` (no Laravel Events, Policies or built-in pattern helpers).

| Pattern | Classes | Purpose |
| --- | --- | --- |
| Adapter | `EloquentSponsorAdapter`, `ArraySponsorData` | Rules read a sponsor through one standard shape, whether it comes from the database or a test array |
| Composite | `Check`, `CheckItem`, `CheckGroup`, `Checklists` | Stage checklists built from single rules and nested groups |
| Factory Method | `ApprovalFactory` + three approval classes | Right approval object per office, each with its own "can submit yet?" rule |
| Facade | `StageTracker`, `StageReport` | One call: next stage and what is missing |

Business rules built in:
- Negotiation to Approval needs: deal finalized, agreed amount, a representative.
- Approval to Logistics needs only Student Affairs approval.
- Logistics to Ready needs: setup arranged, Operations & Management approval, gate passes.
- Gate passes stay locked until Operations & Management approves. Operations & Management can only be submitted after Student Affairs approves.
- Ready to On Campus needs an arrival; On Campus to Completed needs every event day marked as left.

## Step 1: Check the models

Do NOT create any pattern files in this step. Open `app/Models/Sponsor.php` and confirm:
1. It has relationships named exactly `approvals()`, `representatives()` and `eventDays()`.
2. It casts `stage` to `App\Enums\Stage`.

Also confirm `App\Models\EventDay` has `arrived_at` and `left_at` columns, and `App\Models\Approval` has `office` and `status` columns.

Add or fix ONLY what is missing, and report what you changed. Then stop.

## Step 2: Adapter pattern

Create these four files exactly.


`app/Enums/Office.php`

```php
<?php
namespace App\Enums;

enum Office: string
{
    case StudentAffairs = 'student_affairs';
    case OpsManagement  = 'ops_management';
    case GatePass       = 'security_gate_pass';

    public function label(): string
    {
        return match ($this) {
            self::StudentAffairs => 'Student Affairs',
            self::OpsManagement  => 'Operations & Management',
            self::GatePass       => 'Security Office gate passes',
        };
    }
}
```

`app/Patterns/Adapter/SponsorData.php`

```php
<?php
namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;

/**
 * The ONE shape of sponsor information that the pattern classes understand.
 * Anything that can answer these questions can be checked, whether it comes
 * from the database or from a plain array in a test.
 */
interface SponsorData
{
    public function stage(): Stage;
    public function dealFinalized(): bool;
    public function hasAgreedAmount(): bool;
    public function hasRepresentative(): bool;
    public function requiresElectricity(): bool;
    public function setupArranged(): bool;
    /** 'pending', 'submitted' or 'approved' */
    public function approvalStatus(Office $office): string;
    public function arrived(): bool;
    public function left(): bool;
}
```

`app/Patterns/Adapter/EloquentSponsorAdapter.php`

```php
<?php
namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;
use App\Models\Sponsor;

/** Adapter 1: makes a database Sponsor model look like SponsorData. */
final class EloquentSponsorAdapter implements SponsorData
{
    public function __construct(private Sponsor $sponsor) {}

    public function stage(): Stage
    {
        return $this->sponsor->stage;
    }

    public function dealFinalized(): bool
    {
        return $this->sponsor->deal_status === 'finalized';
    }

    public function hasAgreedAmount(): bool
    {
        return (float) $this->sponsor->agreed_amount > 0;
    }

    public function hasRepresentative(): bool
    {
        return $this->sponsor->representatives->isNotEmpty();
    }

    public function requiresElectricity(): bool
    {
        return (bool) $this->sponsor->requires_electricity;
    }

    public function setupArranged(): bool
    {
        return (bool) $this->sponsor->setup_arranged;
    }

    public function approvalStatus(Office $office): string
    {
        $approval = $this->sponsor->approvals->firstWhere('office', $office->value);

        return $approval?->status ?? 'pending';
    }

    public function arrived(): bool
    {
        return $this->sponsor->eventDays->contains(fn ($day) => $day->arrived_at !== null);
    }

    public function left(): bool
    {
        $days = $this->sponsor->eventDays;

        return $days->isNotEmpty() && $days->every(fn ($day) => $day->left_at !== null);
    }
}
```

`app/Patterns/Adapter/ArraySponsorData.php`

```php
<?php
namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;

/** Adapter 2: makes a plain PHP array look like SponsorData (used in tests and demos). */
final class ArraySponsorData implements SponsorData
{
    public function __construct(private array $data) {}

    public function stage(): Stage { return $this->data['stage'] ?? Stage::Negotiation; }
    public function dealFinalized(): bool { return (bool) ($this->data['deal_finalized'] ?? false); }
    public function hasAgreedAmount(): bool { return ($this->data['agreed_amount'] ?? 0) > 0; }
    public function hasRepresentative(): bool { return (bool) ($this->data['has_representative'] ?? false); }
    public function requiresElectricity(): bool { return (bool) ($this->data['requires_electricity'] ?? false); }
    public function setupArranged(): bool { return (bool) ($this->data['setup_arranged'] ?? false); }
    public function approvalStatus(Office $office): string { return $this->data['approvals'][$office->value] ?? 'pending'; }
    public function arrived(): bool { return (bool) ($this->data['arrived'] ?? false); }
    public function left(): bool { return (bool) ($this->data['left'] ?? false); }
}
```

Suggested commit message when I ask: `Add Adapter pattern for sponsor data`

## Step 3: Composite pattern

Create these four files exactly. `Checklists.php` holds the real CAPTrack rules; note the group inside a group under Logistics (that nesting is what makes it a Composite).


`app/Patterns/Composite/Check.php`

```php
<?php
namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;

/** A single rule OR a group of rules. Callers treat both the same way. */
interface Check
{
    public function passes(SponsorData $sponsor): bool;

    /** @return string[] plain-language list of what is still missing */
    public function missing(SponsorData $sponsor): array;
}
```

`app/Patterns/Composite/CheckItem.php`

```php
<?php
namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;
use Closure;

/** The "leaf": one yes/no rule. */
final class CheckItem implements Check
{
    public function __construct(private string $label, private Closure $test) {}

    public function passes(SponsorData $sponsor): bool
    {
        return (bool) ($this->test)($sponsor);
    }

    public function missing(SponsorData $sponsor): array
    {
        return $this->passes($sponsor) ? [] : [$this->label];
    }
}
```

`app/Patterns/Composite/CheckGroup.php`

```php
<?php
namespace App\Patterns\Composite;

use App\Patterns\Adapter\SponsorData;

/** The "composite": a group that passes only when every child passes. Children can be groups too. */
final class CheckGroup implements Check
{
    /** @param Check[] $children */
    public function __construct(private string $name, private array $children) {}

    public function passes(SponsorData $sponsor): bool
    {
        foreach ($this->children as $child) {
            if (! $child->passes($sponsor)) {
                return false;
            }
        }

        return true;
    }

    public function missing(SponsorData $sponsor): array
    {
        $missing = [];
        foreach ($this->children as $child) {
            $missing = array_merge($missing, $child->missing($sponsor));
        }

        return $missing;
    }
}
```

`app/Patterns/Composite/Checklists.php`

```php
<?php
namespace App\Patterns\Composite;

use App\Enums\Office;
use App\Enums\Stage;
use App\Patterns\Adapter\SponsorData;

/** The checklist a sponsor must complete to LEAVE each stage. */
final class Checklists
{
    public static function toLeave(Stage $stage): ?Check
    {
        return match ($stage) {
            Stage::Negotiation => new CheckGroup('Ready for approval', [
                new CheckItem('Deal marked as finalized', fn (SponsorData $s) => $s->dealFinalized()),
                new CheckItem('Agreed amount entered', fn (SponsorData $s) => $s->hasAgreedAmount()),
                new CheckItem('Representative added', fn (SponsorData $s) => $s->hasRepresentative()),
            ]),

            Stage::Approval => new CheckGroup('Ready for logistics', [
                new CheckItem('Student Affairs approval received',
                    fn (SponsorData $s) => $s->approvalStatus(Office::StudentAffairs) === 'approved'),
            ]),

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

            Stage::Ready => new CheckGroup('Ready to arrive', [
                new CheckItem('Arrived on campus', fn (SponsorData $s) => $s->arrived()),
            ]),

            Stage::OnCampus => new CheckGroup('Ready to complete', [
                new CheckItem('Left the campus', fn (SponsorData $s) => $s->left()),
            ]),

            Stage::Completed, Stage::Cancelled => null,
        };
    }
}
```

Suggested commit message when I ask: `Add Composite checklists`

## Step 4: Factory Method pattern

Create these five files exactly.


`app/Patterns/Factory/ApprovalRequest.php`

```php
<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

/** The "product": what every kind of approval can do. */
abstract class ApprovalRequest
{
    abstract public function office(): Office;

    /** Can this approval be logged as submitted yet? */
    abstract public function canSubmit(SponsorData $sponsor): bool;

    public function label(): string
    {
        return $this->office()->label();
    }

    /** The values for a new row in the approvals table. */
    public function newRow(): array
    {
        return ['office' => $this->office()->value, 'status' => 'pending', 'submitted_on' => null];
    }
}
```

`app/Patterns/Factory/StudentAffairsApproval.php`

```php
<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

final class StudentAffairsApproval extends ApprovalRequest
{
    public function office(): Office { return Office::StudentAffairs; }

    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->dealFinalized();
    }
}
```

`app/Patterns/Factory/OpsManagementApproval.php`

```php
<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

final class OpsManagementApproval extends ApprovalRequest
{
    public function office(): Office { return Office::OpsManagement; }

    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->approvalStatus(Office::StudentAffairs) === 'approved';
    }
}
```

`app/Patterns/Factory/GatePassApproval.php`

```php
<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

final class GatePassApproval extends ApprovalRequest
{
    public function office(): Office { return Office::GatePass; }

    /** Gate passes stay locked until Operations & Management approves. */
    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->approvalStatus(Office::OpsManagement) === 'approved';
    }
}
```

`app/Patterns/Factory/ApprovalFactory.php`

```php
<?php
namespace App\Patterns\Factory;

use App\Enums\Office;

/** Factory Method: callers ask for an office and get the right kind of approval back. */
final class ApprovalFactory
{
    public static function make(Office $office): ApprovalRequest
    {
        return match ($office) {
            Office::StudentAffairs => new StudentAffairsApproval(),
            Office::OpsManagement  => new OpsManagementApproval(),
            Office::GatePass       => new GatePassApproval(),
        };
    }

    /** @return ApprovalRequest[] all three, in the order they happen */
    public static function makeAll(): array
    {
        return array_map(fn (Office $office) => self::make($office), Office::cases());
    }
}
```

Suggested commit message when I ask: `Add Factory Method for approvals`

## Step 5: Facade pattern

Create these two files exactly.


`app/Patterns/Facade/StageReport.php`

```php
<?php
namespace App\Patterns\Facade;

use App\Enums\Stage;

/** The simple answer the Facade hands back. */
final class StageReport
{
    /**
     * @param string[] $missing  what still blocks the next stage
     * @param string[] $warnings things to watch, but that do not block
     */
    public function __construct(
        public readonly Stage $current,
        public readonly ?Stage $next,
        public readonly bool $canAdvance,
        public readonly array $missing,
        public readonly array $warnings,
    ) {}
}
```

`app/Patterns/Facade/StageTracker.php`

```php
<?php
namespace App\Patterns\Facade;

use App\Enums\Office;
use App\Enums\Stage;
use App\Patterns\Adapter\SponsorData;
use App\Patterns\Composite\Checklists;

/**
 * Facade: ONE easy method that hides the stage order, the checklists and the
 * Composite behind it. Screens never touch Checklists directly.
 */
final class StageTracker
{
    private const ORDER = [
        Stage::Negotiation, Stage::Approval, Stage::Logistics,
        Stage::Ready, Stage::OnCampus, Stage::Completed,
    ];

    public function evaluate(SponsorData $sponsor): StageReport
    {
        $current   = $sponsor->stage();
        $checklist = Checklists::toLeave($current);

        if ($checklist === null) {            // Completed or Cancelled
            return new StageReport($current, null, false, [], []);
        }

        $missing = $checklist->missing($sponsor);

        return new StageReport(
            $current,
            $this->nextAfter($current),
            $checklist->passes($sponsor),
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

    private function nextAfter(Stage $stage): ?Stage
    {
        $position = array_search($stage, self::ORDER, true);

        return self::ORDER[$position + 1] ?? null;
    }

    private function warnings(SponsorData $sponsor): array
    {
        $warnings = [];
        if ($sponsor->stage() === Stage::Logistics
            && $sponsor->requiresElectricity()
            && $sponsor->approvalStatus(Office::OpsManagement) !== 'approved') {
            $warnings[] = 'Electricity is needed but Operations & Management has not approved yet.';
        }

        return $warnings;
    }
}
```

Suggested commit message when I ask: `Add StageTracker facade`

## Step 6: Tests

Create these two test files exactly. The unit tests use `ArraySponsorData`, so they need no database.


`tests/Unit/Patterns/PatternsDay2Test.php`

```php
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

class PatternsDay2Test extends TestCase
{
    // ---------- Composite ----------

    public function test_check_item_reports_what_is_missing(): void
    {
        $item = new CheckItem('Booth setup arranged', fn ($s) => $s->setupArranged());
        $sponsor = new ArraySponsorData([]);

        $this->assertFalse($item->passes($sponsor));
        $this->assertSame(['Booth setup arranged'], $item->missing($sponsor));
    }

    public function test_group_lists_missing_items_including_nested_ones(): void
    {
        $sponsor = new ArraySponsorData(['stage' => Stage::Logistics]);
        $checklist = Checklists::toLeave(Stage::Logistics);

        $this->assertFalse($checklist->passes($sponsor));
        $this->assertCount(3, $checklist->missing($sponsor));
    }

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

    public function test_negotiation_is_blocked_until_amount_is_entered(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Negotiation,
            'deal_finalized' => true,
            'has_representative' => true,
        ]);

        $report = (new StageTracker())->evaluate($sponsor);

        $this->assertFalse($report->canAdvance);
        $this->assertSame(['Agreed amount entered'], $report->missing);
    }

    public function test_complete_negotiation_moves_to_approval(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Negotiation,
            'deal_finalized' => true,
            'agreed_amount' => 50000,
            'has_representative' => true,
        ]);

        $tracker = new StageTracker();

        $this->assertTrue($tracker->evaluate($sponsor)->canAdvance);
        $this->assertSame(Stage::Approval, $tracker->stageAfterChecks($sponsor));
    }

    public function test_student_affairs_approval_opens_logistics(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Approval,
            'approvals' => ['student_affairs' => 'approved'],
        ]);

        $this->assertSame(Stage::Logistics, (new StageTracker())->stageAfterChecks($sponsor));
    }

    public function test_blocked_sponsor_stays_in_the_same_stage(): void
    {
        $sponsor = new ArraySponsorData(['stage' => Stage::Approval]);

        $this->assertSame(Stage::Approval, (new StageTracker())->stageAfterChecks($sponsor));
    }

    public function test_logistics_warns_when_electricity_is_not_approved(): void
    {
        $sponsor = new ArraySponsorData([
            'stage' => Stage::Logistics,
            'requires_electricity' => true,
        ]);

        $this->assertCount(1, (new StageTracker())->evaluate($sponsor)->warnings);
    }

    public function test_completed_sponsor_has_no_next_stage(): void
    {
        $report = (new StageTracker())->evaluate(new ArraySponsorData(['stage' => Stage::Completed]));

        $this->assertNull($report->next);
        $this->assertFalse($report->canAdvance);
    }

    // ---------- Factory ----------

    public function test_factory_returns_the_right_kind_of_approval(): void
    {
        $this->assertInstanceOf(GatePassApproval::class, ApprovalFactory::make(Office::GatePass));
    }

    public function test_gate_pass_is_locked_until_ops_and_management_approves(): void
    {
        $locked = new ArraySponsorData(['approvals' => ['ops_management' => 'submitted']]);
        $open   = new ArraySponsorData(['approvals' => ['ops_management' => 'approved']]);
        $gate   = ApprovalFactory::make(Office::GatePass);

        $this->assertFalse($gate->canSubmit($locked));
        $this->assertTrue($gate->canSubmit($open));
    }

    public function test_make_all_returns_three_approvals_in_order(): void
    {
        $offices = array_map(fn ($a) => $a->office(), ApprovalFactory::makeAll());

        $this->assertSame(
            [Office::StudentAffairs, Office::OpsManagement, Office::GatePass],
            $offices
        );
    }
}
```

`tests/Feature/EloquentAdapterTest.php`

```php
<?php
namespace Tests\Feature;

use App\Enums\Office;
use App\Enums\Stage;
use App\Models\Sponsor;
use App\Patterns\Adapter\EloquentSponsorAdapter;
use App\Patterns\Facade\StageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_reads_a_seeded_sponsor(): void
    {
        $this->seed();

        $sponsor = Sponsor::with(['approvals', 'representatives', 'eventDays'])
            ->where('stage', Stage::Approval->value)
            ->firstOrFail();

        $adapter = new EloquentSponsorAdapter($sponsor);

        $this->assertSame(Stage::Approval, $adapter->stage());
        $this->assertContains(
            $adapter->approvalStatus(Office::StudentAffairs),
            ['pending', 'submitted', 'approved']
        );
        $this->assertSame(Stage::Approval, (new StageTracker())->evaluate($adapter)->current);
    }
}
```

`$this->seed()` runs `DatabaseSeeder`. If the demo sponsors are seeded by a different seeder class, make `DatabaseSeeder` call it.

Then run `php artisan test` and show me the full result. Expected: all earlier tests plus these 13 are green (12 unit tests, 1 feature test). If the feature test fails because a relationship or column has a different name, fix the name inside `EloquentSponsorAdapter`, NOT in the test. If anything else fails, show me the error and propose the smallest fix before changing code.

Suggested commit message when I ask: `Add tests for Adapter, Composite, Factory and Facade`

## Step 7: Try it on real data

Run this and show me the output (adapt it into a short `php artisan tinker` session or a temporary script; do not leave temporary files behind):

```php
$tracker = new App\Patterns\Facade\StageTracker();
App\Models\Sponsor::with(['approvals', 'representatives', 'eventDays'])->get()->each(function ($s) use ($tracker) {
    $r = $tracker->evaluate(new App\Patterns\Adapter\EloquentSponsorAdapter($s));
    echo $s->organization_name . ' | ' . $r->current->value . ' | can advance: ' . ($r->canAdvance ? 'yes' : 'no')
        . ' | missing: ' . implode(', ', $r->missing) . PHP_EOL;
});
```

Expected: one line per demo sponsor with different stages and sensible missing items.

## Done when

- `php artisan test` is fully green.
- The Step 7 output looks sensible for every demo sponsor.
- All files are in the folders shown above and nothing else was changed.

Do NOT build the Proxy, Observer, saving stage changes to the database, or any screens. Those are Day 3.
