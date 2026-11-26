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

// End-to-end tests: the real chain (proxy, service, observers, logger) on a test database.
class SponsorServiceTest extends TestCase
{
    use RefreshDatabase;

    // A complete new sponsor, built with the Builder pattern, plus one representative.
    private function newSponsorData(): array
    {
        $data = (new SponsorBuilder)
            ->name('Test Co')
            ->contact('Rafi Ahmed', '01700000000')
            ->amount(50000)
            ->build();

        $data['representative'] = ['name' => 'Rafi Ahmed', 'phone' => '01700000000'];

        return $data;
    }

    // Finalizing moves the sponsor on, logs the change, and leaves an Admin notice.
    public function test_finalizing_a_complete_deal_moves_it_to_approval(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Corporate);
        $service = Services::sponsors();

        $id = $service->createSponsor($this->newSponsorData(), $actor);
        $service->finalizeDeal($id, $actor);

        $this->assertSame(Stage::Approval, Sponsor::findOrFail($id)->stage);
        $this->assertDatabaseHas('activity_logs', ['sponsor_id' => $id, 'action' => 'stage_changed']);
        $this->assertDatabaseHas('notices', ['sponsor_id' => $id, 'team' => 'admin']);
    }

    // The Factory gives every new sponsor one pending row per office.
    public function test_creating_a_sponsor_adds_three_pending_approvals(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Corporate);

        $id = Services::sponsors()->createSponsor($this->newSponsorData(), $actor);

        $this->assertSame(3, Sponsor::findOrFail($id)->approvals()->where('status', 'pending')->count());
    }

    // The proxy blocks Admin from a Corporate-only action.
    public function test_admin_cannot_finalize_a_deal(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Admin);

        $this->expectException(AccessDenied::class);

        Services::sponsors()->finalizeDeal(Sponsor::firstOrFail()->id, $actor);
    }

    // Cancelling puts the sponsor in the Cancelled stage.
    public function test_cancelling_a_sponsor_sets_the_cancelled_stage(): void
    {
        $this->seed();
        $user = User::firstOrFail();
        $actor = new Actor($user->id, $user->name, Team::Corporate);
        $service = Services::sponsors();

        $id = $service->createSponsor($this->newSponsorData(), $actor);
        $service->cancelDeal($id, $actor);

        $this->assertSame(Stage::Cancelled, Sponsor::findOrFail($id)->stage);
    }
}
