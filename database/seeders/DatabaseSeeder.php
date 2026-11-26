<?php

namespace Database\Seeders;

use App\Enums\Stage;
use App\Enums\Team;
use App\Models\Approval;
use App\Models\Note;
use App\Models\Representative;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Database\Seeder;

// Fills the database with demo data. Run: php artisan migrate:fresh --seed
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- 1. Four members, one per team. Every password is "password". ----
        // The factory sets the password and marks the email as verified.
        $corporate = User::factory()->create([
            'name' => 'Nusrat Jahan',
            'email' => 'corporate@captrack.test',
            'team' => Team::Corporate,
            'phone' => '01711000001',
        ]);
        $admin = User::factory()->create([
            'name' => 'Tasnim Rahman',
            'email' => 'admin@captrack.test',
            'team' => Team::Admin,
            'phone' => '01711000002',
        ]);
        $provision = User::factory()->create([
            'name' => 'Arif Hossain',
            'email' => 'provision@captrack.test',
            'team' => Team::Provision,
            'phone' => '01711000003',
        ]);
        $coordinator = User::factory()->create([
            'name' => 'Sadia Islam',
            'email' => 'coordinator@captrack.test',
            'team' => Team::Coordinator,
            'phone' => '01711000004',
        ]);

        // ---- 2. Eight sponsors, spread over every stage. ----
        // Columns that are the same for every sponsor are not repeated here.
        $sponsorRows = [
            ['Kite Coffee', 'Food stall', 'Rafi Ahmed', Stage::Negotiation, null, 'Free coffee samples', true],
            ['Meghna Bank', 'Bank', 'Farhan Kabir', Stage::Approval, 50000, 'Banners and student account desk', true],
            ['Pathao', 'Company', 'Mim Chowdhury', Stage::Approval, 30000, 'Ride promo codes', false],
            ['Shwapno', 'Company', 'Imran Hasan', Stage::Logistics, 25000, 'Snack packs for volunteers', false],
            ['Grameenphone', 'Company', 'Laila Akter', Stage::Ready, 80000, 'SIM booth and goodies', true],
            ['Daraz', 'Company', 'Tanvir Alam', Stage::OnCampus, 40000, 'Discount vouchers', true],
            ['Bengal Books', 'Bookshop', 'Rumana Sultana', Stage::Completed, 10000, 'Book stall', false],
            ['Fresh Juice Co.', 'Food stall', 'Sabbir Rahman', Stage::Cancelled, null, 'Juice stall', true],
        ];

        $sponsors = [];
        foreach ($sponsorRows as $i => [$name, $type, $contact, $stage, $amount, $items, $electricity]) {
            $sponsors[] = Sponsor::create([
                'organization_name' => $name,
                'type' => $type,
                'contact_name' => $contact,
                'contact_phone' => '0181200000'.$i,
                'contact_email' => 'contact'.$i.'@example.com',
                'agreed_amount' => $amount,
                'items' => $items,
                'setup_needs' => 'One table and two chairs',
                'requires_electricity' => $electricity,
                // Setup is only arranged once the sponsor is ready or later.
                'setup_arranged' => in_array($stage, [Stage::Ready, Stage::OnCampus, Stage::Completed]),
                'stage' => $stage,
                'deal_status' => $this->dealStatusFor($stage),
                // The last sponsor is nobody's yet; Corporate handles the rest.
                'claimed_by' => $i === 7 ? null : $corporate->id,
            ]);
        }

        // ---- 3. Approvals from the three offices, for sponsors past negotiation. ----
        foreach ($sponsors as $sponsor) {
            if ($sponsor->stage === Stage::Negotiation) {
                continue;   // no paperwork yet while still negotiating
            }

            foreach (['student_affairs', 'ops_management', 'security_gate_pass'] as $n => $office) {
                $status = $this->approvalStatusFor($sponsor->stage, $n);

                Approval::create([
                    'sponsor_id' => $sponsor->id,
                    'office' => $office,
                    'status' => $status,
                    'submitted_on' => $status === 'pending' ? null : now()->subDays(10 - $n),
                ]);
            }
        }

        // ---- 4. Representatives: the people each sponsor sends to campus. ----
        // Kite Coffee (index 0) is still negotiating, so it has none yet;
        // that is one of the things it still needs before Approval.
        $representatives = [
            1 => [['Nadia Karim', '01911000001'], ['Shafiq Uddin', '01911000002']],
            2 => [['Rakib Hasan', '01911000003']],
            3 => [['Mehedi Hasan', '01911000004']],
            4 => [['Farzana Yasmin', '01911000005'], ['Jubayer Ahmed', '01911000006']],
            5 => [['Sharmin Akter', '01911000007']],
            6 => [['Kamrul Islam', '01911000008']],
            7 => [['Anika Tabassum', '01911000009']],
        ];

        foreach ($representatives as $sponsorIndex => $people) {
            foreach ($people as [$name, $phone]) {
                Representative::create([
                    'sponsor_id' => $sponsors[$sponsorIndex]->id,
                    'name' => $name,
                    'phone' => $phone,
                ]);
            }
        }

        // ---- 5. A few notes from different members. ----
        $notes = [
            [0, $corporate, 'They want a spot near the main gate.', now()->addDays(2)],
            [1, $admin, 'Student Affairs asked for the bank\'s trade licence.', now()->addDay()],
            [3, $provision, 'Snack packs will arrive one day early.', null],
            [4, $coordinator, 'Confirmed the booth size with the event lead.', null],
            [7, $corporate, 'They cancelled because of a budget cut.', null],
        ];

        foreach ($notes as [$sponsorIndex, $author, $body, $followUp]) {
            Note::create([
                'sponsor_id' => $sponsors[$sponsorIndex]->id,
                'user_id' => $author->id,
                'body' => $body,
                'follow_up_on' => $followUp,
            ]);
        }
    }

    // Which deal status fits a stage.
    private function dealStatusFor(Stage $stage): string
    {
        return match ($stage) {
            Stage::Negotiation => 'negotiating',
            Stage::Cancelled => 'cancelled',
            default => 'finalized',
        };
    }

    // Which approval status fits a stage. $n is the office number (0, 1 or 2).
    private function approvalStatusFor(Stage $stage, int $n): string
    {
        if ($stage === Stage::Approval) {
            // Still waiting: the first office has the papers, the others are pending.
            return $n === 0 ? 'submitted' : 'pending';
        }

        if ($stage === Stage::Cancelled) {
            return 'pending';
        }

        return 'approved';   // logistics and later: everything was approved
    }
}
