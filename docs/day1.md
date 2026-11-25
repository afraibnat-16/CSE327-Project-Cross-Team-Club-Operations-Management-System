Day 1: database, roles, and the first two patterns
By the end of Day 1 you can log in as each of the four teams, the database holds eight demo sponsors, and the Singleton and Builder patterns pass their tests. Follow the steps in order and commit after each one.
Step 1: Start a branch
cd ~/Documents/captrack
git checkout main
git pull
git checkout -b feat/database
code .
Open the Claude panel. Keep php artisan serve out of the way for now; you only need it at the end.
Step 2: Add the two enums
Create the folder app/Enums/ and two files. An enum is a fixed list of allowed values, which stops typos like "aproval" from ever reaching the database.
app/Enums/Stage.php:
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

    public function label(): string
    {
        return match ($this) {
            self::OnCampus => 'On campus',
            default        => ucfirst($this->value),
        };
    }
}
app/Enums/Team.php:
<?php
namespace App\Enums;

enum Team: string
{
    case Corporate   = 'corporate';
    case Admin       = 'admin';
    case Provision   = 'provision';
    case Coordinator = 'coordinator';
}
Commit: git add . && git commit -m "Add Stage and Team enums"
Step 3: Create the tables, models and demo data with Claude
In your design, a Member is a person who logs in. Laravel already has a users table and login screens, so Member = the users table. Do not create a second members table. Note this for the final report.
Switch the Claude panel to Plan mode and paste the prompt below. The table after it is what the plan must contain, so you can check Claude's plan against it.
Read CLAUDE.md. Plan, then build, the database layer for CAPTrack.
Use Laravel migrations, Eloquent models, and one seeder. Keep it simple.
Create migrations in this order so foreign keys work: sponsors, representatives,
approvals, assignments, notes, event_days, activity_logs, then add columns to users.
Use the enums app/Enums/Stage.php and Team.php (cast them on the models).
Give every model $fillable and its relationships (hasMany / belongsTo).
Seeder: 4 users (one per team, password "password") and 8 sponsors, one or two per stage,
plus approvals and a few notes for them.
Do not write any design-pattern code yet. Show me the plan before writing files.
Table
Columns to expect
users (add)
team (Team enum), phone (nullable)
sponsors
organization_name, type, contact_name, contact_phone, contact_email, agreed_amount (decimal, nullable), items (text), setup_needs (text), requires_electricity (bool, default false), setup_arranged (bool, default false), stage (Stage enum, default negotiation), deal_status (negotiating / finalized / cancelled), claimed_by (user id, nullable)
representatives
sponsor_id, name, phone
approvals
sponsor_id, office (student_affairs / ops_management / security_gate_pass), status (pending / submitted / approved), submitted_on (date, nullable)
assignments
sponsor_id, user_id, task, status (open / done), due_on (nullable)
notes
sponsor_id, user_id, body, follow_up_on (date, nullable)
event_days
sponsor_id, event_date, location (nullable), entry_pass, exit_pass (bool), arrived_at, left_at (nullable)
activity_logs
sponsor_id (nullable), user_id (nullable), action, details (text, nullable)
Read the plan. If a column above is missing, or Claude added extra tables, tell it to fix the plan. Then approve it, switch to the mode that asks before editing, and review each file diff before accepting.
When it finishes, run:
php artisan migrate:fresh --seed
It must finish with no red errors. If a foreign-key error appears, paste the whole error into Claude and say: "The migrations run in the wrong order. Fix only the file names."
Check the data:
php artisan tinker
>>> App\Models\Sponsor::count()
>>> App\Models\User::pluck('team', 'email')
>>> exit
You should see 8 sponsors and four users with four different teams. Commit: git add . && git commit -m "Add database tables, models and seeder"
Step 4: Show the team after login
Start the app with php artisan serve and open http://127.0.0.1:8000. Log in with each of the four seeded emails (ask Claude or open database/seeders for them).
Then ask Claude, in a fresh conversation: "On the dashboard page, show the logged-in member's name and team, for example 'Tasnim Rahman, Admin team'. Do not build anything else." That is all the role work you need today. Menus and permissions per team come on Days 3 to 6.
Commit: git commit -am "Show member name and team on dashboard"
Step 5: Singleton pattern, ActivityLogger
A Singleton guarantees there is only one object of a class, so every part of the app writes to the same activity log. Create app/Patterns/Singleton/ActivityLogger.php:
<?php
namespace App\Patterns\Singleton;

final class ActivityLogger
{
    private static ?ActivityLogger $instance = null;
    private array $entries = [];

    private function __construct() {}   // nobody outside can call new
    private function __clone() {}

    public static function getInstance(): ActivityLogger
    {
        return self::$instance ??= new ActivityLogger();
    }

    public function log(?int $sponsorId, ?int $userId, string $action, string $details = ''): void
    {
        $this->entries[] = compact('sponsorId', 'userId', 'action', 'details');
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
Today it only keeps entries in memory. On Day 3, when the Observer pattern is added, you will make log() also save a row to activity_logs.
Step 6: Builder pattern, SponsorBuilder
A Builder creates a complex object step by step, so a sponsor with many optional fields is easy to read and cannot be built half-empty. Create app/Patterns/Builder/SponsorBuilder.php:
<?php
namespace App\Patterns\Builder;

use InvalidArgumentException;

class SponsorBuilder
{
    private array $data = [
        'type' => 'Company',
        'requires_electricity' => false,
        'setup_arranged' => false,
    ];

    public function name(string $name): static { $this->data['organization_name'] = $name; return $this; }
    public function type(string $type): static { $this->data['type'] = $type; return $this; }

    public function contact(string $name, string $phone, string $email = ''): static
    {
        $this->data['contact_name'] = $name;
        $this->data['contact_phone'] = $phone;
        $this->data['contact_email'] = $email;
        return $this;
    }

    public function amount(float $amount): static { $this->data['agreed_amount'] = $amount; return $this; }
    public function items(string $items): static { $this->data['items'] = $items; return $this; }
    public function needsElectricity(bool $yes = true): static { $this->data['requires_electricity'] = $yes; return $this; }

    public function build(): array
    {
        if (empty($this->data['organization_name'])) {
            throw new InvalidArgumentException('A sponsor needs an organization name.');
        }
        return $this->data;
    }
}
You use it like this, and later the Corporate entry form will do exactly this:
$data = (new SponsorBuilder())
    ->name('Kite Coffee')
    ->contact('Rafi Ahmed', '01700000000')
    ->needsElectricity()
    ->build();

Sponsor::create($data);
Step 7: Test both patterns
Create tests/Unit/Patterns/PatternsTest.php:
<?php
namespace Tests\Unit\Patterns;

use App\Patterns\Builder\SponsorBuilder;
use App\Patterns\Singleton\ActivityLogger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PatternsTest extends TestCase
{
    public function test_logger_is_one_shared_instance(): void
    {
        $this->assertSame(ActivityLogger::getInstance(), ActivityLogger::getInstance());
    }

    public function test_builder_builds_and_validates(): void
    {
        $data = (new SponsorBuilder())->name('Kite Coffee')->needsElectricity()->build();
        $this->assertSame('Kite Coffee', $data['organization_name']);
        $this->assertTrue($data['requires_electricity']);

        $this->expectException(InvalidArgumentException::class);
        (new SponsorBuilder())->build();
    }
}
php artisan test --filter=PatternsTest
Both tests should pass. I ran this same code outside Laravel to confirm it works: the logger returns one shared instance, the builder returns the data, and an empty builder throws the error.
Commit and open the pull request:
git add .
git commit -m "Add ActivityLogger singleton and SponsorBuilder with tests"
git push -u origin feat/database
On GitHub click Compare & pull request, merge it, then git checkout main and git pull.
Day 1 is done when
[ ] php artisan migrate:fresh --seed runs with no errors
[ ] Eight demo sponsors and four team users exist
[ ] You can log in and see your name and team on the dashboard
[ ] php artisan test --filter=PatternsTest passes
[ ] Everything is merged into main on GitHub
If Day 1 runs late, the one thing you can safely move to Day 2 is Step 4 (the team shown on the dashboard). Do not skip the tests: they are what let you change code later without breaking it.