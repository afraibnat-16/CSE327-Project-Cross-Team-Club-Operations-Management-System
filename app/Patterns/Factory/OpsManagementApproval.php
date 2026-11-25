<?php

namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

// The second approval. Operations & Management comes after Student Affairs.
final class OpsManagementApproval extends ApprovalRequest
{
    public function office(): Office
    {
        return Office::OpsManagement;
    }

    // Rule: we can only submit after Student Affairs has approved.
    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->approvalStatus(Office::StudentAffairs) === 'approved';
    }
}
