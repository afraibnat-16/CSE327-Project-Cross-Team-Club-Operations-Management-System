<?php
namespace App\Patterns\Adapter;

use App\Enums\Office;
use App\Enums\Stage;

// ADAPTER PATTERN - adapter for a plain array.
// Lets tests describe a sponsor with a simple array, with no database needed.
// Any key left out gets a safe default ("no", 0, "pending", Negotiation).
// Example: new ArraySponsorData(['stage' => Stage::Approval,
//                                'approvals' => ['student_affairs' => 'approved']])

/** Adapter 2: makes a plain PHP array look like SponsorData (used in tests and demos). */
final class ArraySponsorData implements SponsorData
{
    // Keep the array we are adapting.
    public function __construct(private array $data) {}

    // Each method reads one key from the array; "??" gives the default when the key is missing.
    public function stage(): Stage { return $this->data['stage'] ?? Stage::Negotiation; }
    public function dealFinalized(): bool { return (bool) ($this->data['deal_finalized'] ?? false); }
    public function hasAgreedAmount(): bool { return ($this->data['agreed_amount'] ?? 0) > 0; }
    public function hasRepresentative(): bool { return (bool) ($this->data['has_representative'] ?? false); }
    public function requiresElectricity(): bool { return (bool) ($this->data['requires_electricity'] ?? false); }
    public function setupArranged(): bool { return (bool) ($this->data['setup_arranged'] ?? false); }
    // 'approvals' is a list like ['ops_management' => 'approved'].
    public function approvalStatus(Office $office): string { return $this->data['approvals'][$office->value] ?? 'pending'; }
    public function arrived(): bool { return (bool) ($this->data['arrived'] ?? false); }
    public function left(): bool { return (bool) ($this->data['left'] ?? false); }
}
