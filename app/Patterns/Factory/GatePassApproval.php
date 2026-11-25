<?php
namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

// The last approval. The Security Office issues gate passes after Ops & Management.
final class GatePassApproval extends ApprovalRequest
{
    public function office(): Office { return Office::GatePass; }

    /** Gate passes stay locked until Operations & Management approves. */
    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->approvalStatus(Office::OpsManagement) === 'approved';
    }
}
