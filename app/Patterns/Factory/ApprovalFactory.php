<?php

namespace App\Patterns\Factory;

use App\Enums\Office;

// FACTORY METHOD PATTERN - the "creator".
// Callers never write "new GatePassApproval()" themselves. They ask the factory
// for an office, and it decides which class to create. To add a new office later,
// only this file and a new approval class need to change.

/** Factory Method: callers ask for an office and get the right kind of approval back. */
final class ApprovalFactory
{
    // Give it an office, get back the matching approval object.
    public static function make(Office $office): ApprovalRequest
    {
        return match ($office) {
            Office::StudentAffairs => new StudentAffairsApproval,
            Office::OpsManagement => new OpsManagementApproval,
            Office::GatePass => new GatePassApproval,
        };
    }

    // Office::cases() lists the offices in the order they are written in the enum,
    // and we make one approval object for each.
    /** @return ApprovalRequest[] all three, in the order they happen */
    public static function makeAll(): array
    {
        return array_map(fn (Office $office) => self::make($office), Office::cases());
    }
}
