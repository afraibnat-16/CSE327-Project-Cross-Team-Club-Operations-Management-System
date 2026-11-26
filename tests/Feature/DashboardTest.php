<?php

namespace Tests\Feature;

use App\Enums\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_shows_member_name_and_team(): void
    {
        $user = User::factory()->create(['name' => 'Tasnim Rahman', 'team' => Team::Admin]);
        $this->actingAs($user);

        $this->get('/dashboard')->assertSee('Tasnim Rahman, Admin team');
    }
}
