<?php

namespace App\Patterns\Factory;

use App\Enums\Office;
use App\Patterns\Adapter\SponsorData;

// The first approval. Student Affairs is asked once the deal is finalized.
final class StudentAffairsApproval extends ApprovalRequest
{
    public function office(): Office
    {
        return Office::StudentAffairs;
    }

    // Rule: we can only submit after the deal with the sponsor is finalized.
    public function canSubmit(SponsorData $sponsor): bool
    {
        return $sponsor->dealFinalized();
    }
}
