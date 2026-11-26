<?php

namespace Tests\Feature;

use App\Enums\Office;
use App\Enums\Stage;
use App\Models\Sponsor;
use App\Patterns\Adapter\EloquentSponsorAdapter;
use App\Patterns\Facade\StageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Checks the database Adapter against real seeded data.
// RefreshDatabase gives this test its own empty test database.
class EloquentAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_reads_a_seeded_sponsor(): void
    {
        // Fill the test database with the demo data from DatabaseSeeder.
        $this->seed();

        // Load a sponsor in the Approval stage, with its related rows loaded too.
        $sponsor = Sponsor::with(['approvals', 'representatives', 'eventDays'])
            ->where('stage', Stage::Approval->value)
            ->firstOrFail();

        $adapter = new EloquentSponsorAdapter($sponsor);

        // The adapter reads the stage and approvals correctly...
        $this->assertSame(Stage::Approval, $adapter->stage());
        $this->assertContains(
            $adapter->approvalStatus(Office::StudentAffairs),
            ['pending', 'submitted', 'approved']
        );
        // ...and the Facade accepts it just like an array-based sponsor.
        $this->assertSame(Stage::Approval, (new StageTracker)->evaluate($adapter)->current);
    }
}
